<?php
/**
 * api_test_ultra.php
 * THE definitive tool to find WHY users get logged out
 * Tests: Token validation, Session persistence, Header stripping, 500 crashes, CORS
 */

header("Content-Type: text/html; charset=UTF-8");

// ===================================
// CONFIGURATION
// ===================================
$BASE = "http://localhost:8000/api";

$TOKENS = [
    "employer" => "2f5c475e280597a2a68ca44e0b931a9c36e42e0d3b4964f2aea08d1b2f4c04c5",
    "employee" => "a75f08f1247922899d25aab408cb94764d52faaa3d3bd839bb2c9457f5cacc4c"
];

$XUSER = [
    "employer" => json_encode(["user_id"=>1,"user_type"=>"employer","organization_id"=>1]),
    "employee" => json_encode(["user_id"=>1,"user_type"=>"employee","employee_id"=>1,"organization_id"=>1])
];

// Critical endpoints that cause logout when they fail
$CRITICAL_ENDPOINTS = [
    ["GET",    "/employee/my_salary_structure.php"],
    ["GET",    "/payroll.php?action=my_payslips"],
    ["GET",    "/employee_salary_structure.php?employee_id=1"],
    ["GET",    "/employer/employees.php"],
    ["POST",   "/payroll.php",               ["action" => "get_summary", "month" => 1, "year" => 2025]],
    ["GET",    "/verify_session.php"],
];

// ===================================
// LOGGING (appears in same folder)
// ===================================
function log_debug($msg) {
    $f = __DIR__ . '/api_test_ultra.log';
    file_put_contents($f, "[" . date('H:i:s') . "] $msg\n", FILE_APPEND | LOCK_EX);
}
log_debug("=== NEW TEST RUN ===");

// ===================================
// MAIN TEST FUNCTION
// ===================================
function test_endpoint($method, $path, $BASE, $token, $xuser, $post_data = null) {
    $full_url = $BASE . $path;
    $start = microtime(true);

    $headers = [
        "Authorization: Bearer $token",
        "X-User: $xuser",
        "Content-Type: application/json",
        "Accept: application/json",
        "Origin: http://localhost:8000/api",
        "Referer: http://localhost:8000/api",
        "User-Agent: Mozilla/5.0 (api_test_ultra)"
    ];

    $opts = [
        "http" => [
            "method" => $method,
            "header" => implode("\r\n", $headers),
            "ignore_errors" => true,
            "timeout" => 15
        ]
    ];

    if ($post_data !== null) {
        $opts["http"]["content"] = is_string($post_data) ? $post_data : json_encode($post_data);
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($full_url, false, $context);
    $duration = round((microtime(true) - $start) * 1000, 2);
    $status = $http_response_header[0] ?? "NO STATUS";
    $status_code = (int)substr($status, 9, 3);

    // Extract CORS headers
    $origin = $cors_header = $methods = "N/A";
    foreach (($http_response_header ?? []) as $h) {
        if (stripos($h, "Access-Control-Allow-Origin") === 0) $origin = trim(substr($h, 28));
        if (stripos($h, "Access-Control-Allow-Credentials") === 0) $cors_header = trim(substr($h, 33));
        if (stripos($h, "Access-Control-Allow-Methods") === 0) $methods = trim(substr($h, 28));
    }

    // Build result
    echo "<div style='border:2px solid #ccc; margin:15px 0; padding:15px; border-radius:10px; background:#fff'>";
    echo "<h3 style='margin:0 0 10px 0'>[$method] $path</h3>";
    echo "<p><strong>Status:</strong> <span style='font-size:1.2em'>$status</span> ({$duration}ms)</p>";

    // CRITICAL DIAGNOSIS
    if ($status_code >= 500) {
        echo "<p style='color:red;font-weight:bold'>SERVER CRASH (500+) → This is 99% why users get logged out!</p>";
        log_debug("500+ CRASH on $method $path → logout cause");
    }
    if ($status_code === 401) {
        echo "<p style='color:red;font-weight:bold'>Token rejected → session killed</p>";
    }
    if ($status_code === 403) {
        echo "<p style='color:orange;font-weight:bold'>Permission denied (possible wrong user_type)</p>";
    }
    if ($status_code === 200) {
        echo "<p style='color:green;font-weight:bold'>Success – session would stay alive</p>";
    }

    // Show actual headers sent (to catch stripping)
    echo "<details><summary><strong>Headers sent</strong></summary><pre>";
    foreach ($headers as $h) echo htmlspecialchars($h) . "\n";
    echo "</pre></details>";

    // Show response headers (CORS + Set-Cookie)
    echo "<details><summary><strong>Response headers</strong></summary><pre>";
    foreach (($http_response_header ?? []) as $h) echo htmlspecialchars($h) . "\n";
    echo "</pre></details>";

    echo "<p><strong>CORS Origin:</strong> $origin | <strong>Credentials:</strong> $cors_header</p>";

    // Response body (truncated)
    $body = $response === false ? "NO RESPONSE" : $response;
    echo "<details><summary><strong>Response body</strong> (first 800 chars)</summary>";
    echo "<pre style='max-height:400px;overflow:auto;background:#f0f0f0'>";
    echo htmlspecialchars(substr($body, 0, 800)) . (strlen($body) > 800 ? "\n... (truncated)" : "");
    echo "</pre></details>";

    echo "</div><hr>";
}

// ===================================
// RUN TESTS
// ===================================
?>
<!DOCTYPE html>
<html><head><title>ULTRA API TEST – Find the Logout Bug</title>
<style>
body {font-family:Arial,sans-serif;background:#f9f9f9;padding:20px;}
h1 {color:#d00;}
pre {background:#222;color:#0f0;padding:10px;border-radius:8px;}
details {margin:10px 0;}
</style></head><body>

<h1>EvolvePayroll – Why Are Users Being Logged Out?</h1>
<p>This tool will show you the <strong>exact endpoint</strong> that crashes or rejects the token → causing logout.</p>
<p><strong>Current time:</strong> <?=date('Y-m-d H:i:s')?></p>
<p><strong>Log file:</strong> <a href="api_test_ultra.log" target="_blank">api_test_ultra.log</a> (tail -f this file)</p>

<?php
foreach (["employer", "employee"] as $role) {
    echo "<h2 style='color:#0066cc'>Testing as <u>$role</u></h2>";
    $token = $TOKENS[$role];
    $xuser = $XUSER[$role];

    foreach ($CRITICAL_ENDPOINTS as $ep) {
        $method = $ep[0];
        $path   = $ep[1];
        $data   = $ep[2] ?? null;
        test_endpoint($method, $path, $BASE, $token, $xuser, $data);
    }
    echo "<br><br>";
}
?>

<p><strong>Done.</strong> Check above for any red 500+ errors → that’s your logout criminal.</p>
</body></html>