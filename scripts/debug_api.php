<?php
$base = 'http://localhost/payroll/BACKEND/api';
$login = json_decode(file_get_contents($base . '/unified_auth.php?action=login', false, stream_context_create([
    'http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>json_encode(['username'=>'admin','password'=>'Admin@123'])]
])), true);
$token = $login['token'] ?? '';
echo "Token: " . substr($token,0,20) . "...\n";
$r = file_get_contents($base . '/dashboard.php?action=overview', false, stream_context_create([
    'http' => ['header'=>"Authorization: Bearer $token\r\nAccept: application/json\r\n", 'ignore_errors'=>true]
]));
echo "Dashboard response:\n$r\n";
echo "Headers:\n";
print_r($http_response_header ?? []);
