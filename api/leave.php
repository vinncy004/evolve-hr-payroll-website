<?php
/**
 * Leave management API with RBAC
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../middleware/RbacMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = (new Database())->getConnection();
$session = RbacMiddleware::requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

function leaveSelectSql(): string
{
    return "
        SELECT lr.*,
               CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
               e.employee_number,
               lt.code AS leave_type_code,
               lt.name AS leave_type_name,
               d.name AS department_name,
               p.title AS position_title
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
    ";
}

function resolveLeaveTypeId(PDO $db, int $orgId, $input): ?int
{
    if (isset($input['leave_type_id'])) {
        return (int)$input['leave_type_id'];
    }
    if (!isset($input['leave_type'])) {
        return null;
    }
    $value = trim((string)$input['leave_type']);
    $stmt = $db->prepare("
        SELECT id FROM leave_types
        WHERE organization_id = :org AND (code = :v OR name = :v2)
        LIMIT 1
    ");
    $stmt->execute([':org' => $orgId, ':v' => strtoupper($value), ':v2' => $value]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $db->prepare(leaveSelectSql() . ' WHERE lr.id = :id LIMIT 1');
                $stmt->execute([':id' => (int)$_GET['id']]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$record) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Leave request not found']);
                    exit();
                }
                RbacMiddleware::requireEmployeeAccess($db, $session, (int)$record['employee_id']);
                echo json_encode(['success' => true, 'record' => $record]);
                exit();
            }

            $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
            if ($employeeId > 0) {
                RbacMiddleware::requireEmployeeAccess($db, $session, $employeeId);
                $stmt = $db->prepare(leaveSelectSql() . ' WHERE lr.employee_id = :eid ORDER BY lr.created_at DESC');
                $stmt->execute([':eid' => $employeeId]);
            } elseif (RbacMiddleware::isHrAdmin($session)) {
                $orgId = RbacMiddleware::organizationId($session);
                $stmt = $db->prepare(leaveSelectSql() . ' WHERE e.organization_id = :org ORDER BY lr.created_at DESC');
                $stmt->execute([':org' => $orgId]);
            } elseif (RbacMiddleware::isManager($session)) {
                $stmt = $db->prepare(leaveSelectSql() . ' WHERE e.manager_id = :mgr ORDER BY lr.created_at DESC');
                $stmt->execute([':mgr' => RbacMiddleware::employeeId($session)]);
            } else {
                $stmt = $db->prepare(leaveSelectSql() . ' WHERE lr.employee_id = :eid ORDER BY lr.created_at DESC');
                $stmt->execute([':eid' => RbacMiddleware::employeeId($session)]);
            }

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'records' => $records, 'count' => count($records)]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $employeeId = (int)($data['employee_id'] ?? RbacMiddleware::employeeId($session));
            if ($employeeId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'employee_id is required']);
                exit();
            }

            if (!RbacMiddleware::isHrAdmin($session) && RbacMiddleware::employeeId($session) !== $employeeId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Cannot create leave for another employee']);
                exit();
            }

            $emp = $db->prepare('SELECT organization_id FROM employees WHERE id = :id LIMIT 1');
            $emp->execute([':id' => $employeeId]);
            $empRow = $emp->fetch(PDO::FETCH_ASSOC);
            if (!$empRow) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit();
            }

            $leaveTypeId = resolveLeaveTypeId($db, (int)$empRow['organization_id'], $data);
            if (!$leaveTypeId || empty($data['start_date']) || empty($data['end_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'leave_type, start_date and end_date are required']);
                exit();
            }

            $days = $data['days_requested'] ?? $data['days'] ?? null;
            if ($days === null) {
                $start = new DateTime($data['start_date']);
                $end = new DateTime($data['end_date']);
                $days = max(1, $start->diff($end)->days + 1);
            }

            $stmt = $db->prepare("
                INSERT INTO leave_requests
                    (employee_id, leave_type_id, start_date, end_date, days_requested, reason, status)
                VALUES (:eid, :lt, :start, :end, :days, :reason, 'pending')
            ");
            $stmt->execute([
                ':eid' => $employeeId,
                ':lt' => $leaveTypeId,
                ':start' => $data['start_date'],
                ':end' => $data['end_date'],
                ':days' => $days,
                ':reason' => $data['reason'] ?? null,
            ]);

            echo json_encode(['success' => true, 'message' => 'Leave request created', 'id' => (int)$db->lastInsertId()]);
            break;

        case 'PUT':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Leave request ID is required']);
                exit();
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $existing = $db->prepare('SELECT * FROM leave_requests WHERE id = :id LIMIT 1');
            $existing->execute([':id' => $id]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Leave request not found']);
                exit();
            }

            RbacMiddleware::requireEmployeeAccess($db, $session, (int)$row['employee_id']);

            if (isset($data['status']) && in_array($data['status'], ['approved', 'rejected'], true)) {
                RbacMiddleware::requireAnyRole(RbacMiddleware::LEAVE_APPROVER_ROLES, $session);
            }

            $fields = [];
            $params = [':id' => $id];
            foreach (['status', 'start_date', 'end_date', 'reason'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            if (isset($data['days']) || isset($data['days_requested'])) {
                $fields[] = 'days_requested = :days_requested';
                $params[':days_requested'] = $data['days_requested'] ?? $data['days'];
            }
            if (isset($data['status']) && $data['status'] === 'approved') {
                $fields[] = 'approved_by = :approved_by';
                $params[':approved_by'] = (int)$session['user_id'];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }

            $sql = 'UPDATE leave_requests SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $db->prepare($sql)->execute($params);
            echo json_encode(['success' => true, 'message' => 'Leave request updated']);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Leave request ID is required']);
                exit();
            }
            $existing = $db->prepare('SELECT employee_id, status FROM leave_requests WHERE id = :id LIMIT 1');
            $existing->execute([':id' => $id]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Leave request not found']);
                exit();
            }
            RbacMiddleware::requireEmployeeAccess($db, $session, (int)$row['employee_id']);
            if ($row['status'] !== 'pending' && !RbacMiddleware::isHrAdmin($session)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only pending requests can be deleted']);
                exit();
            }
            $db->prepare('DELETE FROM leave_requests WHERE id = :id')->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Leave request deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
