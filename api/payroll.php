<?php
/**
 * backend/api/payroll.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';

// === EXACT SAME SECURITY SETUP AS employee/profile.php ===
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('payroll', 200, 60);

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

$user_id     = $session['user_id'] ?? null;
$user_type   = $session['user_type'] ?? null;
$employee_id = $session['employee_id'] ?? null;  // This is already set correctly by verifyToken() for employees

/* ========================================
   NEW: MY PAYSLIPS (Employee Self-Service)
   ======================================== */
if (isset($_GET['action']) && $_GET['action'] === 'my_payslips') {
    if ($user_type !== 'employee' || !$employee_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.period_month,
            p.period_year,
            p.gross_pay,
            p.total_deductions,
            p.net_pay,
            p.status,
            e.employee_no,
            e.first_name,
            e.last_name
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        WHERE p.employee_id = ? 
          AND p.status IN ('finalized', 'paid')
        ORDER BY p.period_year DESC, p.period_month DESC
    ");
    $stmt->execute([$employee_id]);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $payslips
    ]);
    exit();
}



require_once __DIR__ . '/../controllers/PayrollController.php';
require_once __DIR__ . '/../utils/PayslipGenerator.php';
require_once __DIR__ . '/../utils/SimplePdfGenerator.php';
require_once __DIR__ . '/../utils/PayrollReportGenerator.php';

$payrollController = new PayrollController($db);
$method = $_SERVER['REQUEST_METHOD'];

// Get the request URI
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/', trim($request_uri, '/'));

try {
    switch ($method) {
        case 'GET':
            handleGet($payrollController, $db, $session);
            break;

        case 'POST':
            handlePost($payrollController, $db, $session);
            break;

        case 'PUT':
            handlePut($payrollController, $session);
            break;

        case 'DELETE':
            handleDelete();
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Handle GET requests
 */
function handleGet($payrollController, $db, $session) {
    $action = $_GET['action'] ?? '';

    switch ($action) {

        case 'payslip_by_id':
            $pid = $_GET['id'] ?? null;

            if (!$pid) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing payroll ID']);
                return;
            }

            $payslip = $payrollController->getPayrollById($pid);

            if (!$payslip) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Payslip not found']);
                return;
            }

            echo json_encode([
                'success' => true,
                'data'    => $payslip
            ]);
            return;


        case 'get_payroll':
            // Get payroll for a specific period
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $payroll = $payrollController->getPayrollByPeriod($month, $year);

            echo json_encode($payroll);

            break;

        case 'get_payslip':
            // Get individual payslip
            $employee_id = (int)($_GET['employee_id'] ?? 0);
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');

            if (!$employee_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing employee_id']);
                return;
            }

            if (!authorizePayrollAccess($db, $session, $employee_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }

            $payslip = $payrollController->getPayslip($employee_id, $month, $year);

            if ($payslip) {
                echo json_encode([
                    'success' => true,
                    'data' => $payslip
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Payslip not found'
                ]);
            }
            break;

        case 'get_summary':
            // Get payroll summary
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $summary = $payrollController->getPayrollSummary($month, $year);

            echo json_encode($summary);

            break;

        case 'request_payroll_view':
            $employee_id = (int)($_GET['employee_id'] ?? $session['employee_id'] ?? 0);
            $month = (int)($_GET['month'] ?? date('m'));
            $year = (int)($_GET['year'] ?? date('Y'));

            if (!$employee_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing employee_id']);
                return;
            }

            if (!authorizePayrollAccess($db, $session, $employee_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }

            $status = $payrollController->getPayrollRequestStatus($employee_id, $month, $year);
            echo json_encode([
                'success' => true,
                'is_due' => $status['is_due'] ?? false,
                'message' => $status['message'] ?? 'Payroll status loaded',
                'data' => $status['payroll'] ?? null,
                'due_date' => $status['due_date'] ?? null,
            ]);
            return;

        case 'download_monthly_payroll':
            $employee_id = (int)($_GET['employee_id'] ?? $session['employee_id'] ?? 0);
            $month = (int)($_GET['month'] ?? date('m'));
            $year = (int)($_GET['year'] ?? date('Y'));

            if (!$employee_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing employee_id']);
                return;
            }

            if (!authorizePayrollAccess($db, $session, $employee_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }

            $pdf = $payrollController->generatePayrollDownloadPdf($employee_id, $month, $year);
            if (!$pdf) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Payroll is not available for download yet']);
                return;
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="payroll_' . $employee_id . '_' . $year . '_' . str_pad((int)$month, 2, '0', STR_PAD_LEFT) . '.pdf"');
            echo $pdf;
            return;

        case 'download_payslip':
            // Generate and download payslip as PDF
            $employee_id = (int)($_GET['employee_id'] ?? 0);
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');

            if (!$employee_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing employee_id']);
                return;
            }

            if (!authorizePayrollAccess($db, $session, $employee_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }

            $payslip = $payrollController->getPayslip($employee_id, $month, $year);

            if ($payslip) {
                $generator = new PayslipGenerator($payslip);
                $pdf = $generator->generatePDF();

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="payslip_' . $employee_id . '_' . $year . '_' . str_pad((int)$month, 2, '0', STR_PAD_LEFT) . '.pdf"');
                echo $pdf;
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Payslip not found'
                ]);
            }
            break;

        case 'generate_report':
            // Generate payroll report
            $report_type = $_GET['report_type'] ?? 'summary';
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');

            $reportGenerator = new PayrollReportGenerator($db);

            switch ($report_type) {
                case 'summary':
                    $html = $reportGenerator->generateMonthlySummary($month, $year);
                    break;
                case 'detailed':
                    $html = $reportGenerator->generateDetailedReport($month, $year);
                    break;
                case 'tax':
                    $html = $reportGenerator->generateTaxReport($month, $year);
                    break;
                default:
                    $html = $reportGenerator->generateMonthlySummary($month, $year);
            }

            header('Content-Type: text/html');
            header('Content-Disposition: inline; filename="payroll_report.html"');
            echo $html;
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePost($payrollController, $db, $session) {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    switch ($action) {

        case 'generate_payroll':
            // Generate payroll for a single employee
            $employee_id = $data['employee_id'] ?? 0;
            $month = $data['month'] ?? date('m');
            $year = $data['year'] ?? date('Y');

            $result = $payrollController->generateEmployeePayroll($employee_id, $month, $year);
            echo json_encode($result);
            break;

        case 'generate_bulk_payroll':
            // Generate payroll for all active employees
            $month = $data['month'] ?? date('m');
            $year = $data['year'] ?? date('Y');

            $result = $payrollController->generateBulkPayroll($month, $year);
            echo json_encode($result);
            break;

        case 'send_payslip':
            require_once __DIR__ . '/../utils/EmailService.php';

            $employee_id = $data['employee_id'] ?? 0;
            $month = $data['month'] ?? date('m');
            $year = $data['year'] ?? date('Y');

            // Load payslip
            $payslip = $payrollController->getPayslip($employee_id, $month, $year);
            if (!$payslip) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Payslip not found'
                ]);
                break;
            }

            // Load employee email + details
            $query = "SELECT e.work_email, e.personal_email,
                        CONCAT(e.first_name, ' ', e.last_name) as full_name,
                        o.organization_name
                      FROM employees e
                      JOIN organizations o ON e.organization_id = o.id
                      WHERE e.id = :employee_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->execute();
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Employee not found'
                ]);
                break;
            }

            // Select best email
            $to_email = $employee['work_email'] ?: $employee['personal_email'];
            if (!$to_email) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No email address found for employee'
                ]);
                break;
            }

            // Prepare payslip summary for email
            $payroll_data = [
                'period' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
                'gross_pay' => $payslip['gross_pay'],
                'total_deductions' => $payslip['total_deductions'],
                'net_pay' => $payslip['net_pay']
            ];

            // Send email
            try {
                $emailService = new EmailService();
                $result = $emailService->sendPayslip($to_email, $employee['full_name'], $payroll_data);

                if ($result['success']) {
                    // Log email in audit log
                    $audit_query = "INSERT INTO audit_log (
                        user_id, user_type, action, table_name, record_id,
                        new_values, ip_address, user_agent
                    ) VALUES (
                        :user_id, 'system', 'payslip_emailed', 'payroll_records', :record_id,
                        :new_values, :ip_address, :user_agent
                    )";

                    $audit_stmt = $db->prepare($audit_query);
                    $audit_stmt->execute([
                        ':user_id' => 0,
                        ':record_id' => $payslip['id'],
                        ':new_values' => json_encode([
                            'employee_id' => $employee_id,
                            'email' => $to_email,
                            'period' => $payroll_data['period']
                        ]),
                        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                }

                echo json_encode($result);

            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to send email: ' . $e->getMessage()
                ]);
            }

            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
}


/**
 * Handle PUT requests
 */
function handlePut($payrollController, $session) {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'approve_payroll':
            // Approve payroll
            $payroll_id = $data['payroll_id'] ?? 0;
            $result = $payrollController->approvePayroll($payroll_id);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Payroll approved successfully' : 'Failed to approve payroll'
            ]);
            break;

        case 'process_payment':
            // Process payment
            $payroll_id = $data['payroll_id'] ?? 0;
            $payment_method = $data['payment_method'] ?? 'bank_transfer';
            $result = $payrollController->processPayment($payroll_id, $payment_method);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Payment processed successfully' : 'Failed to process payment'
            ]);
            break;

        case 'approve_bulk':
            $ids = $data['payroll_ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No payroll IDs provided']);
                return;
            }

            $successCount = 0;

            foreach ($ids as $id) {
                if ($payrollController->approvePayroll($id)) {
                    $successCount++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Approved {$successCount} payroll records"
            ]);
            break;


        case 'process_bulk_payment':
            $ids = $data['payroll_ids'] ?? [];
            $method = $data['payment_method'] ?? 'bank_transfer';

            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No payroll IDs provided']);
                return;
            }

            $successCount = 0;

            foreach ($ids as $id) {
                if ($payrollController->processPayment($id, $method)) {
                    $successCount++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Processed payment for {$successCount} payroll records"
            ]);
            break;

        case 'send_bulk_payslips':
            $ids = $data['payroll_ids'] ?? [];
            $month = $data['month'];
            $year = $data['year'];

            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No payroll IDs provided']);
                return;
            }

            require_once __DIR__ . '/../utils/EmailService.php';

            $emailService = new EmailService();
            $sent = 0;

            foreach ($ids as $pid) {
                $payslip = $payrollController->getPayslipByPayrollId($pid);
                if (!$payslip) continue;

                // This returns employee email + summary
                $to = $payslip['work_email'] ?: $payslip['personal_email'];
                if (!$to) continue;

                $emailService->sendPayslip(
                    $to,
                    $payslip['employee_name'],
                    $payslip
                );

                $sent++;
            }

            echo json_encode([
                'success' => true,
                'message' => "Sent {$sent} payslips"
            ]);
            break;

        

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete() {
    http_response_code(501);
    echo json_encode([
        'success' => false,
        'message' => 'Delete not implemented'
    ]);
}

function authorizePayrollAccess(PDO $db, array $session, int $employee_id): bool {
    $role = strtolower($session['role'] ?? $session['user_type'] ?? 'employee');
    $userType = strtolower($session['user_type'] ?? 'employee');
    $sessionEmployeeId = (int)($session['employee_id'] ?? 0);

    if (in_array($role, ['employee'], true) || $userType === 'employee') {
        return $sessionEmployeeId === $employee_id;
    }

    if ($role === 'manager') {
        return canManagerViewEmployee($db, $sessionEmployeeId, $employee_id);
    }

    if (in_array($role, ['hr', 'admin', 'employer'], true)) {
        return true;
    }

    return false;
}

function canManagerViewEmployee(PDO $db, int $managerEmployeeId, int $employeeId): bool {
    if ($managerEmployeeId <= 0 || $employeeId <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT id FROM employees WHERE id = :employee_id AND manager_id = :manager_id LIMIT 1");
    $stmt->execute([':employee_id' => $employeeId, ':manager_id' => $managerEmployeeId]);
    return (bool)$stmt->fetchColumn();
}
?>