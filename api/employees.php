<?php
/**
 * backend/api/employees.php
 * Organization-scoped Employee CRUD & onboarding
 * Secure version aligned with SecurityMiddleware standard.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/EmployeeOnboardingController.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';

// ----------------------------------------------------
// CORS + Security Headers (Unified Across All APIs)
// ----------------------------------------------------
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('employees_api', 300, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ----------------------------------------------------
// AUTHENTICATION (Unified Token Model)
// ----------------------------------------------------
try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Authentication required"
    ]);
    exit();
}

$user_id   = $session['user_id'] ?? null;
$user_type = $session['user_type'] ?? null;
$org_id    = $session['organization_id'] ?? null;

if (!$user_id || !$org_id) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Invalid session or organization context"
    ]);
    exit();
}

// Only employer/admin/HR should access this endpoint
if (!in_array($user_type, ['employer', 'admin', 'hr'])) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied"
    ]);
    exit();
}

// ----------------------------------------------------
// INIT CONTROLLER + DB
// ----------------------------------------------------
$db = (new Database())->getConnection();
$controller = new EmployeeOnboardingController($db, $org_id, $user_id);

$method       = $_SERVER['REQUEST_METHOD'];
$request_uri  = $_SERVER['REQUEST_URI'];
$uri_parts    = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

$id = null;
$query_params = [];
parse_str(parse_url($request_uri, PHP_URL_QUERY) ?? '', $query_params);

// Extract numeric ID from URI
if (is_numeric(end($uri_parts))) {
    $id = end($uri_parts);
}

// ----------------------------------------------------
// ROUTING
// ----------------------------------------------------
switch ($method) {

    // ------------------------------
    // GET: Search, view single, view all
    // ------------------------------
    case 'GET':

        if (isset($query_params['search'])) {
            $controller->searchEmployees($query_params['search']);
        }
        elseif ($id) {
            $controller->getEmployee($id);
        }
        else {
            $controller->getAllEmployees();
        }
        break;

    // ------------------------------
    // POST: onboard employee
    // ------------------------------
    case 'POST':

        $data = json_decode(file_get_contents("php://input"));
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid JSON: " . json_last_error_msg()
            ]);
            exit();
        }

        $controller->onboardEmployee($data);
        break;

    // ------------------------------
    // PUT: update employee
    // ------------------------------
    case 'PUT':

        $data = json_decode(file_get_contents("php://input"));
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid JSON: " . json_last_error_msg()
            ]);
            exit();
        }

        $controller->updateEmployee($data);
        break;

    // ------------------------------
    // DELETE: soft delete employee
    // ------------------------------
    case 'DELETE':

        if ($id) {
            $data = (object)[
                'id' => $id,
                'employment_status' => 'terminated'
            ];
            $controller->updateEmployee($data);
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Employee ID required"
            ]);
        }
        break;

    // ------------------------------
    // METHOD NOT ALLOWED
    // ------------------------------
    default:
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Method not allowed"
        ]);
        break;
}

?>
