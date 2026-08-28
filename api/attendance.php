<?php
/**
 * Attendance API with RBAC
 * GET  ?employee_id=&month=&year=
 * POST { employee_id, attendance_date, status, overtime_hours }
 * PUT  ?id=  { status, overtime_hours }
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

try {
    if ($method === 'GET') {
        $employeeId = (int)($_GET['employee_id'] ?? RbacMiddleware::employeeId($session));
        if ($employeeId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'employee_id is required']);
            exit();
        }
        RbacMiddleware::requireEmployeeAccess($db, $session, $employeeId);

        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));

        $stmt = $db->prepare("
            SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.employee_number
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            WHERE a.employee_id = :eid
              AND MONTH(a.attendance_date) = :m
              AND YEAR(a.attendance_date) = :y
            ORDER BY a.attendance_date DESC
        ");
        $stmt->execute([':eid' => $employeeId, ':m' => $month, ':y' => $year]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'total_days' => count($records),
            'present_days' => 0,
            'absent_days' => 0,
            'leave_days' => 0,
            'total_overtime_hours' => 0,
        ];
        foreach ($records as $r) {
            if ($r['status'] === 'present') $summary['present_days']++;
            if ($r['status'] === 'absent') $summary['absent_days']++;
            if ($r['status'] === 'leave') $summary['leave_days']++;
            $summary['total_overtime_hours'] += (float)$r['overtime_hours'];
        }

        echo json_encode(['success' => true, 'records' => $records, 'summary' => $summary]);
        exit();
    }

    if ($method === 'POST') {
        RbacMiddleware::requireAnyRole(['admin', 'hr', 'employer', 'manager'], $session);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $employeeId = (int)($data['employee_id'] ?? 0);
        $date = $data['attendance_date'] ?? date('Y-m-d');
        $status = $data['status'] ?? 'present';
        $overtime = (float)($data['overtime_hours'] ?? 0);

        if ($employeeId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'employee_id is required']);
            exit();
        }
        RbacMiddleware::requireEmployeeAccess($db, $session, $employeeId);

        $stmt = $db->prepare("
            INSERT INTO attendance (employee_id, attendance_date, status, overtime_hours)
            VALUES (:eid, :dt, :status, :ot)
            ON DUPLICATE KEY UPDATE status = VALUES(status), overtime_hours = VALUES(overtime_hours)
        ");
        $stmt->execute([':eid' => $employeeId, ':dt' => $date, ':status' => $status, ':ot' => $overtime]);
        echo json_encode(['success' => true, 'message' => 'Attendance recorded']);
        exit();
    }

    if ($method === 'PUT') {
        RbacMiddleware::requireAnyRole(['admin', 'hr', 'employer', 'manager'], $session);
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id is required']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [':id' => $id];
        foreach (['status', 'overtime_hours'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit();
        }
        $db->prepare('UPDATE attendance SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        echo json_encode(['success' => true, 'message' => 'Attendance updated']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
