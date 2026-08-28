<?php
// backend/api/calculate_payroll.php

require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../utils/CalculationService.php';

// CORS + Security Headers
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();



// Preflight is handled inside handleCORS(), no need again.

// Authenticate using ONLY SecurityMiddleware
$session = SecurityMiddleware::verifyToken();

// Now the user is authenticated
// $session contains:
// - user_id
// - user_type (admin | hr | employee)
// - employee_id (if employee)

$payload = json_decode(file_get_contents('php://input'), true);
$payload = SecurityMiddleware::sanitizeInput($payload);

$gross      = $payload['gross']      ?? null;
$basic      = $payload['basic']      ?? null;
$allowances = $payload['allowances'] ?? [];
$benefits   = $payload['benefits']   ?? [];

try {
    if ($gross) {
        $result = CalculationService::calculateFromGross($gross);
    } else {
        if ($basic === null) {
            throw new Exception("Either gross or {basic, allowances, benefits} must be provided");
        }
        $result = CalculationService::calculate($basic, $allowances, $benefits);
    }

    echo json_encode([
        'success' => true,
        'user_type' => $session['user_type'],
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
