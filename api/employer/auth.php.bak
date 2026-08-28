<?php
/**
 * Employer Authentication API
 * Handles login, logout, and session management for employer users
 * (Admin, HR Manager, Payroll Officer, Department Manager, Recruiter)
 */

require_once '../../config/database_secure.php';
require_once '../../middleware/SecurityMiddleware.php';

// Apply security measures
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();

header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$db = $database->getConnection();

$request_method = $_SERVER["REQUEST_METHOD"];
$request_uri = $_SERVER['REQUEST_URI'];

// Parse action from URI
$action = 'login'; // default
if (strpos($request_uri, '/logout') !== false) {
    $action = 'logout';
} elseif (strpos($request_uri, '/verify') !== false) {
    $action = 'verify';
} elseif (strpos($request_uri, '/refresh') !== false) {
    $action = 'refresh';
}

/**
 * Login endpoint
 */
if ($request_method == 'POST' && $action == 'login') {
    $raw_input = file_get_contents("php://input");
    error_log("[EMPLOYER AUTH] Raw input: " . $raw_input);

    $data = json_decode($raw_input);
    error_log("[EMPLOYER AUTH] Decoded data: " . json_encode($data));
    error_log("[EMPLOYER AUTH] Username: " . ($data->username ?? 'EMPTY'));
    error_log("[EMPLOYER AUTH] Password length: " . (isset($data->password) ? strlen($data->password) : 'EMPTY'));

    if (!empty($data->username) && !empty($data->password)) {
        error_log("[EMPLOYER AUTH] Attempting login for user: " . $data->username);
        try {
            // Query employer_users table
            $query = "SELECT 
                        eu.id, eu.username, eu.email, eu.password_hash, eu.role, 
                        eu.organization_id, eu.employee_id, eu.is_active, 
                        eu.two_factor_enabled, eu.failed_login_attempts, eu.locked_until,
                        eu.first_name, eu.last_name, eu.phone_number,
                        o.organization_name, o.organization_code
                      FROM employer_users eu
                      JOIN organizations o ON eu.organization_id = o.id
                      WHERE eu.username = :username AND eu.is_active = 1";

            $stmt = $db->prepare($query);
            $stmt->bindParam(":username", $data->username);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    http_response_code(403);
                    echo json_encode([
                        "success" => false,
                        "message" => "Account is locked. Please try again later or contact administrator."
                    ]);
                    exit;
                }

                // Verify password
                error_log("[EMPLOYER AUTH] Verifying password for user: " . $user['username']);
                $password_match = password_verify($data->password, $user['password_hash']);
                error_log("[EMPLOYER AUTH] Password verification result: " . ($password_match ? 'SUCCESS' : 'FAILED'));

                if ($password_match) {
                    // Password is correct
                    error_log("[EMPLOYER AUTH] Login successful for: " . $user['username']);
                    http_response_code(200);

                    // Generate session token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    // Create session in database
                    $session_query = "INSERT INTO user_sessions 
                                    (user_type, user_id, session_token, ip_address, user_agent, expires_at, is_active)
                                    VALUES ('employer', :user_id, :token, :ip, :user_agent, :expires_at, 1)";
                    $session_stmt = $db->prepare($session_query);
                    $session_stmt->execute([
                        ':user_id' => $user['id'],
                        ':token' => $token,
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        ':expires_at' => $expires_at
                    ]);

                    // Update last login and reset failed attempts
                    $update_query = "UPDATE employer_users 
                                   SET last_login = NOW(), 
                                       last_login_ip = :ip,
                                       failed_login_attempts = 0,
                                       locked_until = NULL
                                   WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':id' => $user['id']
                    ]);

                    // Log successful login
                    $log_query = "INSERT INTO login_logs 
                                (user_type, user_id, username, email, login_status, ip_address, user_agent, device_type)
                                VALUES ('employer', :user_id, :username, :email, 'success', :ip, :user_agent, :device)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        ':user_id' => $user['id'],
                        ':username' => $user['username'],
                        ':email' => $user['email'],
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        ':device' => 'web'
                    ]);

                    echo json_encode([
                        "success" => true,
                        "message" => "Login successful",
                        "token" => $token,
                        "user" => [
                            "id" => $user['id'],
                            "username" => $user['username'],
                            "email" => $user['email'],
                            "role" => $user['role'],
                            "organization_id" => $user['organization_id'],
                            "organization_name" => $user['organization_name'],
                            "employee_id" => $user['employee_id'],
                            "full_name" => trim($user['first_name'] . ' ' . $user['last_name']),
                            "user_type" => "employer"
                        ]
                    ]);
                } else {
                    // Wrong password - increment failed attempts
                    error_log("[EMPLOYER AUTH] Wrong password for user: " . $user['username']);
                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    $locked_until = null;

                    // Lock account after 5 failed attempts
                    if ($failed_attempts >= 5) {
                        $locked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    }

                    $fail_query = "UPDATE employer_users 
                                 SET failed_login_attempts = :attempts,
                                     locked_until = :locked_until
                                 WHERE id = :id";
                    $fail_stmt = $db->prepare($fail_query);
                    $fail_stmt->execute([
                        ':attempts' => $failed_attempts,
                        ':locked_until' => $locked_until,
                        ':id' => $user['id']
                    ]);

                    // Log failed attempt
                    $log_query = "INSERT INTO login_logs 
                                (user_type, user_id, username, email, login_status, failure_reason, ip_address, user_agent)
                                VALUES ('employer', :user_id, :username, :email, 'failed', 'Invalid password', :ip, :user_agent)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        ':user_id' => $user['id'],
                        ':username' => $user['username'],
                        ':email' => $user['email'],
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);

                    http_response_code(401);
                    $message = "Invalid username or password";
                    if ($locked_until) {
                        $message = "Too many failed attempts. Account locked for 30 minutes.";
                    }
                    echo json_encode(["success" => false, "message" => $message]);
                }
            } else {
                // User not found - log attempt without user_id
                error_log("[EMPLOYER AUTH] User not found: " . $data->username);
                try {
                    $log_query = "INSERT INTO login_logs
                                (user_type, username, login_status, failure_reason, ip_address, user_agent)
                                VALUES ('employer', :username, 'failed', 'User not found', :ip, :user_agent)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        ':username' => $data->username,
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                } catch (PDOException $log_error) {
                    // Continue even if logging fails
                    error_log("Login log error: " . $log_error->getMessage());
                }

                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Invalid username or password"]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database error occurred",
                "error" => $e->getMessage()
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Username and password are required"]);
    }
}

/**
 * Logout endpoint
 */
elseif ($request_method == 'POST' && $action == 'logout') {
    $headers = getallheaders();
    $token = null;

    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }

    if ($token) {
        try {
            // Deactivate session
            $query = "UPDATE user_sessions 
                     SET is_active = 0, logout_time = NOW()
                     WHERE session_token = :token AND user_type = 'employer'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":token", $token);
            $stmt->execute();

            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Logout successful"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Logout failed"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "No token provided"]);
    }
}

/**
 * Verify token endpoint
 */
elseif ($request_method == 'GET' && $action == 'verify') {
    $headers = getallheaders();
    $token = null;

    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }

    if ($token) {
        try {
            $query = "SELECT 
                        s.id, s.user_id, s.expires_at,
                        eu.username, eu.email, eu.role, eu.organization_id,
                        eu.first_name, eu.last_name,
                        o.organization_name
                      FROM user_sessions s
                      JOIN employer_users eu ON s.user_id = eu.id
                      JOIN organizations o ON eu.organization_id = o.id
                      WHERE s.session_token = :token 
                        AND s.user_type = 'employer'
                        AND s.is_active = 1
                        AND s.expires_at > NOW()";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":token", $token);
            $stmt->execute();

            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                // Update last activity
                $update_query = "UPDATE user_sessions 
                               SET last_activity = NOW()
                               WHERE session_token = :token";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(":token", $token);
                $update_stmt->execute();

                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "user" => [
                        "id" => $session['user_id'],
                        "username" => $session['username'],
                        "email" => $session['email'],
                        "role" => $session['role'],
                        "organization_id" => $session['organization_id'],
                        "organization_name" => $session['organization_name'],
                        "full_name" => trim($session['first_name'] . ' ' . $session['last_name']),
                        "user_type" => "employer"
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Invalid or expired token"]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Verification failed"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "No token provided"]);
    }
}

/**
 * Invalid request
 */
else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
?>
