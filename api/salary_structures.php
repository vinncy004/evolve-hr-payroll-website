<?php
// public_html/api/salary_structures.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../controllers/SalaryStructureController.php';

// === UNIFIED SECURITY (same as local) ===
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('salary_structures', 100, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = (new Database())->getConnection();

try {
    $session = SecurityMiddleware::verifyToken();   // This is the fixed version you already have
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id     = $session['user_id'];
$user_type   = $session['user_type'];
$employee_id = $session['employee_id'] ?? null;

// Only HR / Admin can manage salary structures
if (!in_array($user_type, ['hr', 'admin', 'employer'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get organization_id for this HR/admin user
$stmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$org) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Organization not found']);
    exit;
}

$org_id = (int)$org['organization_id'];

// Controller
$controller = new SalaryStructureController($db, $org_id);
$method     = $_SERVER['REQUEST_METHOD'];

// ==================== CREATE ====================
if ($method === 'POST') {
    $payload = json_decode(file_get_contents("php://input"), true);

    try {
        $id = $controller->create($payload);

        if (!empty($payload['allowances'])) {
            $controller->saveAllowances($id, $payload['allowances']);
        }
        if (!empty($payload['benefits'])) {
            $controller->saveBenefits($id, $payload['benefits']);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Structure created',
            'id' => $id
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==================== LIST ALL ====================
if ($method === 'GET' && !isset($_GET['id'])) {
    $data = $controller->getAll();
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ==================== GET ONE ====================
if ($method === 'GET' && isset($_GET['id'])) {
    $id   = (int)$_GET['id'];
    $data = $controller->getOne($id);

    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ==================== UPDATE ====================
if ($method === 'PUT') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id is required']);
        exit;
    }

    $payload = json_decode(file_get_contents("php://input"), true);

    try {
        $controller->update($id, $payload);

        if (isset($payload['allowances'])) {
            $controller->saveAllowances($id, $payload['allowances']);
        }
        if (isset($payload['benefits'])) {
            $controller->saveBenefits($id, $payload['benefits']);
        }

        echo json_encode(['success' => true, 'message' => 'Updated']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==================== METHOD NOT ALLOWED ====================
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);