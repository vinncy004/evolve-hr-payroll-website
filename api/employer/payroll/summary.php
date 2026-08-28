<?php
/**
 * backend/api/employer/payroll/summary.php
 * Rewritten: stable GET payroll summary for dashboard.
 */

require_once '../../../config/database.php';
require_once '../../../middleware/SecurityMiddleware.php';

// CORS + headers
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('payroll_summary', 100, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Authenticate
try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_id = $session['user_id'] ?? null;
$user_type = $session['user_type'] ?? null;

if ($user_type !== 'employer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get organization
    $org_stmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = :user_id");
    $org_stmt->execute([':user_id' => $user_id]);
    $org_data = $org_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$org_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }
    $organization_id = (int)$org_data['organization_id'];

    // Query params
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

    if ($month < 1 || $month > 12) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid month']);
        exit();
    }
    if ($year < 2000 || $year > 2100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid year']);
        exit();
    }

    // total active employees
    $emp_count_q = "SELECT COUNT(*) as total_employees FROM employees WHERE organization_id = :org_id AND employment_status = 'active'";
    $emp_count_stmt = $db->prepare($emp_count_q);
    $emp_count_stmt->execute([':org_id' => $organization_id]);
    $emp_count = (int)$emp_count_stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];

    // payroll aggregate — try to read from payroll_records/payroll table
    // If you have a dedicated payroll_records table this will return aggregates else fall back to 0s
    $payroll_q = "SELECT
                    COUNT(DISTINCT p.employee_id) as employees_paid,
                    SUM(p.gross_salary) as total_gross,
                    SUM(p.basic_salary) as total_basic,
                    SUM(p.total_allowances) as total_allowances,
                    SUM(p.total_deductions) as total_deductions,
                    SUM(p.net_salary) as total_net,
                    SUM(p.paye) as total_paye,
                    SUM(p.nssf_employee) as total_nssf_employee,
                    SUM(p.nssf_employer) as total_nssf_employer,
                    SUM(p.nhif) as total_nhif,
                    SUM(p.housing_levy) as total_housing_levy
                  FROM payroll_records p
                  WHERE p.organization_id = :org_id AND p.period_month = :month AND p.period_year = :year";
    $payroll_stmt = $db->prepare($payroll_q);
    $payroll_stmt->execute([':org_id' => $organization_id, ':month' => $month, ':year' => $year]);
    $payroll_data = $payroll_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payroll_data) $payroll_data = [];

    // departments breakdown (top departments by paid employees)
    $dept_q = "SELECT d.id, d.name, COUNT(pr.employee_id) as employees_paid
               FROM departments d
               LEFT JOIN payroll_records pr ON pr.department_id = d.id AND pr.period_month = :month AND pr.period_year = :year
               WHERE d.organization_id = :org_id
               GROUP BY d.id ORDER BY employees_paid DESC LIMIT 10";
    $dept_stmt = $db->prepare($dept_q);
    $dept_stmt->execute([':org_id' => $organization_id, ':month' => $month, ':year' => $year]);
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    // recent payroll runs (latest 6)
    $recent_q = "SELECT id, period_month, period_year, created_at, processed_by, status FROM payroll_runs WHERE organization_id = :org_id ORDER BY created_at DESC LIMIT 6";
    $recent_stmt = $db->prepare($recent_q);
    $recent_stmt->execute([':org_id' => $organization_id]);
    $recent_payrolls = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

    // build summary
    $summary = [
        'period' => [
            'month' => $month,
            'year' => $year,
            'month_name' => date('F', mktime(0,0,0,$month,1,$year))
        ],
        'employees' => [
            'total' => $emp_count,
            'paid' => (int)($payroll_data['employees_paid'] ?? 0)
        ],
        'payroll' => [
            'gross_salary' => (float)($payroll_data['total_gross'] ?? 0),
            'basic_salary' => (float)($payroll_data['total_basic'] ?? 0),
            'allowances' => (float)($payroll_data['total_allowances'] ?? 0),
            'deductions' => (float)($payroll_data['total_deductions'] ?? 0),
            'net_salary' => (float)($payroll_data['total_net'] ?? 0)
        ],
        'statutory' => [
            'paye' => (float)($payroll_data['total_paye'] ?? 0),
            'nssf_employee' => (float)($payroll_data['total_nssf_employee'] ?? 0),
            'nssf_employer' => (float)($payroll_data['total_nssf_employer'] ?? 0),
            'total_nssf' => (float)(($payroll_data['total_nssf_employee'] ?? 0) + ($payroll_data['total_nssf_employer'] ?? 0)),
            'nhif' => (float)($payroll_data['total_nhif'] ?? 0),
            'housing_levy' => (float)($payroll_data['total_housing_levy'] ?? 0)
        ],
        'departments' => $departments,
        'recent_payrolls' => $recent_payrolls
    ];

    echo json_encode(['success' => true, 'data' => $summary]);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    $err = (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Database error';
    echo json_encode(['success' => false, 'message' => 'Failed to fetch payroll summary', 'error' => $err]);
    exit();
}
?>
