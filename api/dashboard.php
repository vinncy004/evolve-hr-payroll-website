<?php
/**
 * Role-aware dashboard API
 * GET /api/dashboard.php?action=overview
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('dashboard', 100, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = (new Database())->getConnection();

try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_type = strtolower($session['user_type'] ?? 'employee');
$user_role = strtolower($session['role'] ?? $user_type);
$employee_id = (int)($session['employee_id'] ?? 0);
$organization_id = (int)($session['organization_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_GET['action'] ?? 'overview';

if ($action !== 'overview') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    if (in_array($user_role, ['employee'], true) && $employee_id > 0) {
        $summary = getEmployeeDashboard($db, $employee_id);
    } else if (in_array($user_role, ['manager'], true) && $employee_id > 0) {
        $summary = getManagerDashboard($db, $employee_id, $organization_id);
    } else if (in_array($user_role, ['hr', 'admin', 'employer'], true) && $organization_id > 0) {
        $summary = getOrgDashboard($db, $organization_id);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Role not authorized for dashboard']);
        exit();
    }

    echo json_encode(['success' => true, 'data' => $summary]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Dashboard error', 'error' => $e->getMessage()]);
}

function getEmployeeDashboard(PDO $db, int $employee_id): array {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS payslip_count,
            COALESCE(SUM(gross_pay), 0) AS total_gross,
            COALESCE(SUM(total_deductions), 0) AS total_deductions,
            COALESCE(SUM(net_pay), 0) AS total_net
        FROM payroll
        WHERE employee_id = :employee_id
    ");
    $stmt->execute([':employee_id' => $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'role' => 'employee',
        'employee_id' => $employee_id,
        'payslip_count' => (int)($row['payslip_count'] ?? 0),
        'total_gross' => (float)($row['total_gross'] ?? 0),
        'total_deductions' => (float)($row['total_deductions'] ?? 0),
        'total_net' => (float)($row['total_net'] ?? 0),
        'last_period' => getLastPayrollPeriod($db, $employee_id)
    ];
}

function getManagerDashboard(PDO $db, int $employee_id, int $organization_id): array {
    $reportStmt = $db->prepare("
        SELECT e.id
        FROM employees e
        WHERE e.organization_id = :org_id
          AND e.manager_id = :manager_id
    ");
    $reportStmt->execute([':org_id' => $organization_id, ':manager_id' => $employee_id]);
    $team = $reportStmt->fetchAll(PDO::FETCH_COLUMN);

    $ids = array_map('intval', $team);
    $teamCount = count($ids);

    $gross = 0; $deductions = 0; $net = 0;
    if ($teamCount > 0) {
        $placeholders = implode(',', array_fill(0, $teamCount, '?'));
        $sumStmt = $db->prepare("
            SELECT
                COALESCE(SUM(gross_pay), 0) AS gross,
                COALESCE(SUM(total_deductions), 0) AS deductions,
                COALESCE(SUM(net_pay), 0) AS net
            FROM payroll
            WHERE employee_id IN ($placeholders)
        ");
        $sumStmt->execute($ids);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        $gross = (float)($sumRow['gross'] ?? 0);
        $deductions = (float)($sumRow['deductions'] ?? 0);
        $net = (float)($sumRow['net'] ?? 0);
    }

    return [
        'role' => 'manager',
        'team_size' => $teamCount,
        'total_gross' => $gross,
        'total_deductions' => $deductions,
        'total_net' => $net,
        'direct_reports' => $ids
    ];
}

function getOrgDashboard(PDO $db, int $organization_id): array {
    $empStmt = $db->prepare("SELECT COUNT(*) AS total FROM employees WHERE organization_id = :org_id AND employment_status = 'active'");
    $empStmt->execute([':org_id' => $organization_id]);
    $employeeCount = (int)($empStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $payStmt = $db->prepare("
        SELECT
            COUNT(DISTINCT employee_id) AS processed_employees,
            COALESCE(SUM(gross_pay), 0) AS gross,
            COALESCE(SUM(total_deductions), 0) AS deductions,
            COALESCE(SUM(net_pay), 0) AS net,
            COALESCE(SUM(paye), 0) AS paye,
            COALESCE(SUM(nssf_employee), 0) AS nssf,
            COALESCE(SUM(shif), 0) AS shif,
            COALESCE(SUM(housing_levy), 0) AS housing_levy
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id
        WHERE e.organization_id = :org_id
    ");
    $payStmt->execute([':org_id' => $organization_id]);
    $payRow = $payStmt->fetch(PDO::FETCH_ASSOC);

    return [
        'role' => 'organization',
        'organization_id' => $organization_id,
        'employee_count' => $employeeCount,
        'processed_employees' => (int)($payRow['processed_employees'] ?? 0),
        'total_gross' => (float)($payRow['gross'] ?? 0),
        'total_deductions' => (float)($payRow['deductions'] ?? 0),
        'total_net' => (float)($payRow['net'] ?? 0),
        'statutory' => [
            'paye' => (float)($payRow['paye'] ?? 0),
            'nssf' => (float)($payRow['nssf'] ?? 0),
            'shif' => (float)($payRow['shif'] ?? 0),
            'housing_levy' => (float)($payRow['housing_levy'] ?? 0)
        ]
    ];
}

function getLastPayrollPeriod(PDO $db, int $employee_id): ?array {
    $stmt = $db->prepare("
        SELECT period_month, period_year, net_pay
        FROM payroll
        WHERE employee_id = :employee_id
        ORDER BY period_year DESC, period_month DESC
        LIMIT 1
    ");
    $stmt->execute([':employee_id' => $employee_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        'month' => (int)$row['period_month'],
        'year' => (int)$row['period_year'],
        'net_pay' => (float)$row['net_pay']
    ];
}
?>
