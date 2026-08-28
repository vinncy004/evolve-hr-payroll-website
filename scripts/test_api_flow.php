<?php
/**
 * End-to-end API smoke test — run: php scripts/test_api_flow.php
 */
require_once __DIR__ . '/../config/database.php';

$base = 'http://localhost/payroll/BACKEND/api';
$passed = 0;
$failed = 0;

function test(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        echo "[PASS] $label\n";
        $passed++;
    } else {
        echo "[FAIL] $label" . ($detail ? " — $detail" : '') . "\n";
        $failed++;
    }
}

function request(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
    if ($token) $headers .= "Authorization: Bearer $token\r\n";

    $opts = [
        'http' => [
            'method' => $method,
            'header' => $headers,
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ];
    if ($body !== null) {
        $opts['http']['content'] = json_encode($body);
    }
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\d{3}/', $statusLine, $m);
    $code = (int)($m[0] ?? 0);
    return ['code' => $code, 'body' => json_decode($response ?: '{}', true) ?: []];
}

echo "=== API Flow Test ===\n";
echo "Base: $base\n\n";

// Login as admin
$login = request('POST', "$base/unified_auth.php?action=login", [
    'username' => 'admin',
    'password' => 'Admin@123',
]);
test('Admin login', $login['code'] === 200 && ($login['body']['success'] ?? false), json_encode($login['body']));
$token = $login['body']['token'] ?? null;

if (!$token) {
    echo "\nCannot continue without token. Run setup_database.php first.\n";
    exit(1);
}

test('Admin role returned', ($login['body']['user']['role'] ?? '') === 'admin');

// Dashboard
$dash = request('GET', "$base/dashboard.php?action=overview", null, $token);
test('HR dashboard overview', $dash['code'] === 200 && ($dash['body']['success'] ?? false));

// Tax settings
$tax = request('GET', "$base/tax_settings.php?action=get", null, $token);
test('Tax settings GET', $tax['code'] === 200 && !empty($tax['body']['data']['settings']));

// Leave list (auth required)
$leave = request('GET', "$base/leave.php", null, $token);
test('Leave API (authenticated)', $leave['code'] === 200 && ($leave['body']['success'] ?? false));

// Attendance
$att = request('GET', "$base/attendance.php?employee_id=1", null, $token);
test('Attendance API', in_array($att['code'], [200, 403], true)); // 403 if no employee 1 access pattern

// Employee login
$empLogin = request('POST', "$base/unified_auth.php?action=login", [
    'username' => 'jane.doe',
    'password' => 'Employee@123',
]);
test('Employee login', $empLogin['code'] === 200 && ($empLogin['body']['success'] ?? false));
$empToken = $empLogin['body']['token'] ?? null;
if ($empToken) {
    $empDash = request('GET', "$base/dashboard.php?action=overview", null, $empToken);
    test('Employee dashboard', $empDash['code'] === 200 && ($empDash['body']['data']['role'] ?? '') === 'employee');
    $empId = $empLogin['body']['user']['employee_id'] ?? 0;
    $bal = request('GET', "$base/leave_balance.php?employee_id=$empId", null, $empToken);
    test('Leave balance', $bal['code'] === 200 && ($bal['body']['success'] ?? false));
}

// Forgot password (should always succeed message)
$forgot = request('POST', "$base/unified_auth.php?action=forgot_password", ['username' => 'admin']);
test('Forgot password', $forgot['code'] === 200 && ($forgot['body']['success'] ?? false));

echo "\n=== $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
