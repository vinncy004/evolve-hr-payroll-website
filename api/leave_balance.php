<?php
/**
 * Leave balance API with RBAC
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$db = (new Database())->getConnection();
$session = RbacMiddleware::requireAuth();

$employeeId = (int)($_GET['employee_id'] ?? RbacMiddleware::employeeId($session));
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit();
}

RbacMiddleware::requireEmployeeAccess($db, $session, $employeeId);

try {
    $year = (int)($_GET['year'] ?? date('Y'));

    $empStmt = $db->prepare('SELECT organization_id FROM employees WHERE id = :id LIMIT 1');
    $empStmt->execute([':id' => $employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit();
    }

    $typesStmt = $db->prepare('
        SELECT lt.id, lt.code, lt.name, lt.days_per_year,
               COALESCE(lb.allocated_days, lt.days_per_year) AS allocated,
               COALESCE(lb.used_days, 0) AS used_from_balance,
               COALESCE(lb.remaining_days, lt.days_per_year) AS remaining_from_balance
        FROM leave_types lt
        LEFT JOIN leave_balances lb
            ON lb.leave_type_id = lt.id AND lb.employee_id = :eid AND lb.year = :yr
        WHERE lt.organization_id = :org
        ORDER BY lt.name
    ');
    $typesStmt->execute([':eid' => $employeeId, ':yr' => $year, ':org' => (int)$emp['organization_id']]);
    $types = $typesStmt->fetchAll(PDO::FETCH_ASSOC);

    $usedStmt = $db->prepare("
        SELECT lt.code, COALESCE(SUM(lr.days_requested), 0) AS used_days
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = :eid
          AND lr.status = 'approved'
          AND YEAR(lr.start_date) = :yr
        GROUP BY lt.code
    ");
    $usedStmt->execute([':eid' => $employeeId, ':yr' => $year]);
    $usedMap = [];
    foreach ($usedStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $usedMap[$u['code']] = (float)$u['used_days'];
    }

    $balance = [];
    foreach ($types as $t) {
        $allocated = (float)$t['allocated'];
        $used = (float)($usedMap[$t['code']] ?? $t['used_from_balance'] ?? 0);
        $balance[$t['code']] = [
            'leave_type' => $t['name'],
            'total' => $allocated,
            'used' => $used,
            'remaining' => max(0, $allocated - $used),
        ];
    }

    echo json_encode([
        'success' => true,
        'employee_id' => $employeeId,
        'year' => $year,
        'balance' => $balance,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
