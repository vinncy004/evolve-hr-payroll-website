<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();

$db = (new Database())->getConnection();

// Authenticate employer
try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id = $session['user_id'];
$user_type = $session['user_type'];

if ($user_type !== 'employer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get organization
$stmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$organization_id = $row['organization_id'];

// FIND LATEST employee_no for the organization
$q = $db->prepare("
    SELECT employee_no 
    FROM employees 
    WHERE organization_id = :org 
    ORDER BY id DESC 
    LIMIT 1
");
$q->execute([':org' => $organization_id]);
$last = $q->fetch(PDO::FETCH_ASSOC);

$year = date('Y');

if (!$last) {
    // First employee of the year
    $next = "EMP" . $year . "0001";
} else {
    // Extract number
   preg_match('/EVOLVE-' . $year . '-(\d+)/', $last['employee_no'], $m);
    if (isset($m[1])) {
        $nextNum = intval($m[1]) + 1;
        $next = "EVOLVE-" . $year . "-" . str_pad($nextNum, 4, "0", STR_PAD_LEFT);
    } else {
        // fallback
        $next = "EVOLVE-" . $year . "-0001";
    }

}

echo json_encode([
    "success" => true,
    "next_employee_no" => $next
]);
