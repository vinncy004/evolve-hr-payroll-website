<?php
// backend/api/employer/departments_assign.php
/**
 * Bulk-assign employees to a department
 * POST payload: { "department_id": 3, "employee_ids": [12,17,20] }
 *
 * Requires employer authentication (SecurityMiddleware::verifyToken)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('departments_assign', 100, 60);

$database = new Database();
$db = $database->getConnection();

try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success'=>false, 'message'=>'Authentication required']);
    exit();
}

$user_id = $session['user_id'] ?? null;
$user_type = $session['user_type'] ?? null;

if ($user_type !== 'employer') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Access denied']);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
$dept_id = isset($input['department_id']) ? (int)$input['department_id'] : 0;
$employee_ids = isset($input['employee_ids']) && is_array($input['employee_ids']) ? $input['employee_ids'] : [];

if (!$dept_id || empty($employee_ids)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'department_id and employee_ids[] are required']);
    exit();
}

try {
    // Get organization for this employer user
    $orgStmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = :id");
    $orgStmt->execute([':id' => $user_id]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
    $organization_id = $org['organization_id'] ?? null;

    if (!$organization_id) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Organization not found']);
        exit();
    }

    // Verify the department belongs to this organization
    $deptStmt = $db->prepare("SELECT id FROM departments WHERE id = :id AND organization_id = :org");
    $deptStmt->execute([':id'=>$dept_id, ':org'=>$organization_id]);
    if (!$deptStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Department not found']);
        exit();
    }

    // Filter employee_ids that belong to this organization
    // Build placeholders safely
    $unique_ids = array_values(array_unique(array_map('intval', $employee_ids)));
    $placeholders = implode(',', array_fill(0, count($unique_ids), '?'));
    $filterSql = "SELECT id FROM employees WHERE id IN ($placeholders) AND organization_id = ?";
    $filterStmt = $db->prepare($filterSql);
    $execParams = array_merge($unique_ids, [$organization_id]);
    $filterStmt->execute($execParams);
    $valid = $filterStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($valid)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'No valid employees to assign']);
        exit();
    }

    // Begin transaction and update employees
    $db->beginTransaction();
    $updateSql = "UPDATE employees SET department_id = :dept_id, updated_at = NOW() WHERE id = :id AND organization_id = :org";
    $updateStmt = $db->prepare($updateSql);
    $assigned = [];
    foreach ($valid as $eid) {
        $updateStmt->execute([':dept_id'=>$dept_id, ':id'=>$eid, ':org'=>$organization_id]);
        if ($updateStmt->rowCount() > 0) $assigned[] = (int)$eid;
    }
    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Employees assigned successfully',
        'assigned_count' => count($assigned),
        'assigned' => $assigned
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'message'=>'Assignment failed',
        'error' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Server error'
    ]);
}
