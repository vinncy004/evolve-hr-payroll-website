<?php
// backend/api/employer/employee_documents.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('employer_employee_documents', 200, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$db = (new Database())->getConnection();

// authenticate employer
$session = SecurityMiddleware::verifyToken();
if ($session['user_type'] !== 'employer') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Access denied']); exit(); }
$user_id = $session['user_id'];

// get organization id from employer_users
$orgStmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = :id LIMIT 1");
$orgStmt->execute([':id' => $user_id]);
$org = $orgStmt->fetch(PDO::FETCH_ASSOC);
if (!$org) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Organization not found']); exit(); }
$organization_id = (int)$org['organization_id'];

// require employee_id param
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if (!$employee_id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'employee_id required']); exit(); }

// verify employee belongs to org
$chk = $db->prepare("SELECT id FROM employees WHERE id = :id AND organization_id = :org_id");
$chk->execute([':id' => $employee_id, ':org_id' => $organization_id]);
if (!$chk->fetch()) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Employee not found in your organization']); exit(); }

// fetch docs
$stmt = $db->prepare("SELECT id, title, file_path, file_type, uploaded_at FROM employee_documents WHERE employee_id = :emp ORDER BY uploaded_at DESC");
$stmt->execute([':emp' => $employee_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'data'=>$rows]);
