<?php
/**
 * GET /api/employee/my_salary_structure.php
 * Returns current salary structure for the logged-in employee
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../../models/SalaryStructure.php';

// ----------------------------------------
// CORS + Security Headers (Unified Standard)
// ----------------------------------------
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('my_salary_structure', 200, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ----------------------------------------
// Authentication (Unified Token Model)
// ----------------------------------------
try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authentication required"]);
    exit();
}

$user_id   = $session['user_id'] ?? null;
$user_type = $session['user_type'] ?? null;

if (!$user_id || !$user_type) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Invalid session"]);
    exit();
}

// Only employee, hr, or admin
if (!in_array($user_type, ['employee', 'hr', 'admin'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied"]);
    exit();
}

$db = (new Database())->getConnection();

// ----------------------------------------
// Determine employee_id
// ----------------------------------------

// If employee self-service
if ($user_type === 'employee') {

    // Map employee_users.id → employees.id
    $q = $db->prepare("SELECT employee_id FROM employee_users WHERE id = :id LIMIT 1");
    $q->execute([':id' => $user_id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Employee record not found"]);
        exit();
    }

    $employee_id = (int)$row['employee_id'];

} else {
    // Admin / HR can specify ?employee_id=1
    if (isset($_GET['employee_id'])) {
        $employee_id = (int)$_GET['employee_id'];
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Employee ID required"]);
        exit();
    }
}

// ----------------------------------------
// Load Salary Structure (Original Logic)
// ----------------------------------------

$model = new SalaryStructure($db);

// Active assignment
$stmt = $db->prepare("
    SELECT structure_id, effective_from, effective_to, assigned_at 
    FROM employee_salary_structure 
    WHERE employee_id = ? AND is_active = 1 
    ORDER BY is_active DESC, assigned_at DESC, id DESC 
    LIMIT 1
");
$stmt->execute([$employee_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "No active salary structure found"]);
    exit();
}

$structure_id = (int)$assignment['structure_id'];
$structure = $model->findById($structure_id);

if (!$structure) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Salary structure not found"]);
    exit();
}

// allowances
$allowances = $db->prepare("
    SELECT id, name, amount, taxable 
    FROM salary_structure_allowances 
    WHERE structure_id = ?
");
$allowances->execute([$structure_id]);
$allowances = $allowances->fetchAll(PDO::FETCH_ASSOC);

// benefits
$benefits = $db->prepare("
    SELECT id, name, amount, benefit_type, taxable 
    FROM salary_structure_benefits 
    WHERE structure_id = ?
");
$benefits->execute([$structure_id]);
$benefits = $benefits->fetchAll(PDO::FETCH_ASSOC);

// Calculations
$gross = round((float)$structure['basic_salary'], 2);
foreach ($allowances as $a) {
    $gross += round((float)$a['amount'], 2);
}

$net = $gross;
foreach ($benefits as $b) {
    if ($b['taxable']) {
        $net -= round((float)$b['amount'], 2);
    }
}

// ----------------------------------------
// Final Response
// ----------------------------------------
http_response_code(200);
echo json_encode([
    "success" => true,
    "data" => [
        "structure" => [
            "title"          => $structure['title'],
            "basic_salary"   => (float)$structure['basic_salary'],
            "gross_salary"   => $gross,
            "net_salary"     => $net,
            "currency"       => $structure['currency'],
            "effective_from" => $assignment['effective_from'] ?? null,
            "effective_to"   => $assignment['effective_to'] ?? null,
            "allowances"     => $allowances,
            "benefits"       => $benefits,
        ],
        "assigned_at" => $assignment['assigned_at']
    ]
], JSON_NUMERIC_CHECK);

