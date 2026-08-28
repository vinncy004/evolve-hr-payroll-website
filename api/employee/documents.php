<?php
// backend/api/employee/documents.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('employee_documents', 200, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$db = (new Database())->getConnection();

// verify token (employee)
$session = SecurityMiddleware::verifyToken();
if ($session['user_type'] !== 'employee') {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Access denied']); exit();
}

// Map employee_users -> employees
$u = $db->prepare("SELECT employee_id FROM employee_users WHERE id = :id LIMIT 1");
$u->execute([':id' => $session['user_id']]);
$row = $u->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Employee not found']); exit(); }
$employee_id = (int)$row['employee_id'];

$uploadDir = __DIR__ . '/../../uploads/employee_docs/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// load env constraints (UPLOAD_MAX_SIZE, UPLOAD_ALLOWED_TYPES) from Database::getConfig or getenv
$maxSize = (int)(getenv('UPLOAD_MAX_SIZE') ?: 5242880); // bytes
$allowed = explode(',', strtolower(getenv('UPLOAD_ALLOWED_TYPES') ?: 'jpg,jpeg,png,pdf,doc,docx'));

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Upload single file via multipart/form-data with field 'file' and optional 'title'
    if (!isset($_FILES['file'])) {
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'No file uploaded']); exit();
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Upload error']); exit();
    }
    if ($file['size'] > $maxSize) {
        http_response_code(413); echo json_encode(['success'=>false,'message'=>'File exceeds max size']); exit();
    }
    $origName = $file['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        http_response_code(415); echo json_encode(['success'=>false,'message'=>'File type not allowed']); exit();
    }
    // safe file name
    $timestamp = time();
    $safeName = $employee_id . '_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9\-\_\.]/','_', $origName);
    $dest = $uploadDir . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to save file']); exit();
    }
    // insert metadata
    $title = trim($_POST['title'] ?? $origName);
    $stmt = $db->prepare("INSERT INTO employee_documents (employee_id, title, file_path, file_type, uploaded_by) VALUES (:employee_id, :title, :file_path, :file_type, :uploaded_by)");
    $stmt->execute([
        ':employee_id' => $employee_id,
        ':title' => $title,
        ':file_path' => 'uploads/employee_docs/' . $safeName,
        ':file_type' => $ext,
        ':uploaded_by' => $session['user_id']
    ]);
    http_response_code(201); echo json_encode(['success'=>true,'message'=>'Uploaded','data'=>['file'=> 'uploads/employee_docs/' . $safeName]]);
    exit();
}

if ($method === 'GET') {
    // list documents for the logged-in employee
    $stmt = $db->prepare("SELECT id, title, file_path, file_type, uploaded_at FROM employee_documents WHERE employee_id = :emp ORDER BY uploaded_at DESC");
    $stmt->execute([':emp' => $employee_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit();
}

// method not allowed
http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']);
