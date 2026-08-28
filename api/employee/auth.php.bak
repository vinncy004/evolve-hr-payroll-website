<?php
/**
 * Employee Authentication API
 * Handles login, logout, and session management for employee users
 * (Self-service portal access)
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

// Parse action from query string or URI
$action = 'login'; // default

// Check query parameter first
if (isset($_GET['action'])) {
    $action = str_replace('-', '_', $_GET['action']);
}
// Fall back to URI path parsing
elseif (strpos($request_uri, '/logout') !== false) {
    $action = 'logout';
} elseif (strpos($request_uri, '/verify') !== false) {
    $action = 'verify';
} elseif (strpos($request_uri, '/change-password') !== false || strpos($request_uri, 'change_password') !== false) {
    $action = 'change_password';
}

error_log("[EMPLOYEE AUTH] Parsed action: " . $action);

/**
 * Login endpoint
 */
if ($request_method == 'POST' && $action == 'login') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->username) && !empty($data->password)) {
        try {
            // Query employee_users table with employee details
            $query = "SELECT 
                        eu.id, eu.username, eu.email, eu.password_hash, 
                        eu.is_active, eu.force_password_change, eu.two_factor_enabled,
                        eu.failed_login_attempts, eu.locked_until,
                        e.id as employee_id, e.employee_number, e.first_name, e.middle_name, e.last_name,
                        e.organization_id, e.department_id, e.position_id, e.employment_status,
                        e.phone_number, e.work_email, e.profile_photo,
                        o.organization_name, o.organization_code,
                        d.name as department_name,
                        p.title as position_title
                      FROM employee_users eu
                      JOIN employees e ON eu.employee_id = e.id
                      JOIN organizations o ON e.organization_id = o.id
                      LEFT JOIN departments d ON e.department_id = d.id
                      LEFT JOIN positions p ON e.position_id = p.id
                      WHERE eu.username = :username 
                        AND eu.is_active = 1
                        AND e.employment_status = 'active'";

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
                        "message" => "Account is locked. Please contact HR department."
                    ]);
                    exit;
                }

                // Verify password
                if (password_verify($data->password, $user['password_hash'])) {
                    // Password is correct
                    http_response_code(200);

                    // Generate session token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+8 hours')); // Shorter session for employees

                    // Create session in database
                    $session_query = "INSERT INTO user_sessions 
                                    (user_type, user_id, session_token, ip_address, user_agent, device_type, expires_at, is_active)
                                    VALUES ('employee', :user_id, :token, :ip, :user_agent, :device, :expires_at, 1)";
                    $session_stmt = $db->prepare($session_query);
                    $session_stmt->execute([
                        ':user_id' => $user['id'],
                        ':token' => $token,
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        ':device' => 'web',
                        ':expires_at' => $expires_at
                    ]);

                    // Update last login and reset failed attempts
                    $update_query = "UPDATE employee_users 
                                   SET last_login = NOW(), 
                                       last_login_ip = :ip,
                                       last_login_device = :device,
                                       failed_login_attempts = 0,
                                       locked_until = NULL
                                   WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':device' => 'web',
                        ':id' => $user['id']
                    ]);

                    // Log successful login
                    $log_query = "INSERT INTO login_logs 
                                (user_type, user_id, username, email, login_status, ip_address, user_agent, device_type)
                                VALUES ('employee', :user_id, :username, :email, 'success', :ip, :user_agent, :device)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        ':user_id' => $user['id'],
                        ':username' => $user['username'],
                        ':email' => $user['email'],
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        ':device' => 'web'
                    ]);

                    $full_name = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);

                    echo json_encode([
                        "success" => true,
                        "message" => "Login successful",
                        "token" => $token,
                        "force_password_change" => (bool)$user['force_password_change'],
                        "user" => [
                            "id" => $user['id'],
                            "username" => $user['username'],
                            "email" => $user['email'],
                            "employee_id" => $user['employee_id'],
                            "employee_number" => $user['employee_number'],
                            "full_name" => $full_name,
                            "first_name" => $user['first_name'],
                            "last_name" => $user['last_name'],
                            "organization_id" => $user['organization_id'],
                            "organization_name" => $user['organization_name'],
                            "department_id" => $user['department_id'],
                            "department_name" => $user['department_name'],
                            "position_id" => $user['position_id'],
                            "position_title" => $user['position_title'],
                            "phone_number" => $user['phone_number'],
                            "profile_photo" => $user['profile_photo'],
                            "user_type" => "employee"
                        ]
                    ]);
                } else {
                    // Wrong password - increment failed attempts
                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    $locked_until = null;

                    // Lock account after 5 failed attempts
                    if ($failed_attempts >= 5) {
                        $locked_until = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    }

                    $fail_query = "UPDATE employee_users 
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
                                VALUES ('employee', :user_id, :username, :email, 'failed', 'Invalid password', :ip, :user_agent)";
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
                        $message = "Too many failed attempts. Account locked for 1 hour. Please contact HR.";
                    }
                    echo json_encode(["success" => false, "message" => $message]);
                }
            } else {
                // User not found or inactive - log attempt without user_id
                try {
                    $log_query = "INSERT INTO login_logs
                                (user_type, username, login_status, failure_reason, ip_address, user_agent)
                                VALUES ('employee', :username, 'failed', 'User not found or inactive', :ip, :user_agent)";
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
                     WHERE session_token = :token AND user_type = 'employee'";
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
                        eu.username, eu.email, eu.force_password_change,
                        e.employee_id, e.employee_number, e.first_name, e.last_name,
                        e.organization_id, e.department_id, e.position_id,
                        o.organization_name,
                        d.name as department_name,
                        p.title as position_title
                      FROM user_sessions s
                      JOIN employee_users eu ON s.user_id = eu.id
                      JOIN employees e ON eu.employee_id = e.id
                      JOIN organizations o ON e.organization_id = o.id
                      LEFT JOIN departments d ON e.department_id = d.id
                      LEFT JOIN positions p ON e.position_id = p.id
                      WHERE s.session_token = :token 
                        AND s.user_type = 'employee'
                        AND s.is_active = 1
                        AND s.expires_at > NOW()
                        AND e.employment_status = 'active'";
            
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
                    "force_password_change" => (bool)$session['force_password_change'],
                    "user" => [
                        "id" => $session['user_id'],
                        "username" => $session['username'],
                        "email" => $session['email'],
                        "employee_id" => $session['employee_id'],
                        "employee_number" => $session['employee_number'],
                        "full_name" => trim($session['first_name'] . ' ' . $session['last_name']),
                        "organization_id" => $session['organization_id'],
                        "organization_name" => $session['organization_name'],
                        "department_name" => $session['department_name'],
                        "position_title" => $session['position_title'],
                        "user_type" => "employee"
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
 * Change password endpoint
 */
elseif ($request_method == 'POST' && $action == 'change_password') {
    error_log("[EMPLOYEE CHANGE PASSWORD] Action triggered");

    $headers = getallheaders();
    $token = null;

    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }

    $raw_input = file_get_contents("php://input");
    error_log("[EMPLOYEE CHANGE PASSWORD] Raw input: " . $raw_input);

    $data = json_decode($raw_input);
    error_log("[EMPLOYEE CHANGE PASSWORD] Token present: " . ($token ? 'YES' : 'NO'));
    error_log("[EMPLOYEE CHANGE PASSWORD] New password present: " . (!empty($data->new_password) ? 'YES' : 'NO'));
    error_log("[EMPLOYEE CHANGE PASSWORD] Current password present: " . (!empty($data->current_password) ? 'YES' : 'NO'));

    if ($token && !empty($data->new_password)) {
        try {
            // Get user from session
            $query = "SELECT s.user_id, eu.password_hash
                     FROM user_sessions s
                     JOIN employee_users eu ON s.user_id = eu.id
                     WHERE s.session_token = :token 
                       AND s.user_type = 'employee'
                       AND s.is_active = 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":token", $token);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                // Verify current password if provided
                if (!empty($data->current_password)) {
                    if (!password_verify($data->current_password, $session['password_hash'])) {
                        http_response_code(400);
                        echo json_encode(["success" => false, "message" => "Current password is incorrect"]);
                        exit;
                    }
                }

                // Hash new password
                $new_hash = password_hash($data->new_password, PASSWORD_BCRYPT);

                // Update password
                $update_query = "UPDATE employee_users 
                               SET password_hash = :password_hash,
                                   force_password_change = FALSE
                               WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    ':password_hash' => $new_hash,
                    ':id' => $session['user_id']
                ]);

                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Password changed successfully"]);
            } else {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Invalid session"]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Password change failed"]);
        }
    } else {
        error_log("[EMPLOYEE CHANGE PASSWORD] Validation failed - Token: " . ($token ? 'present' : 'missing') . ", New password: " . (!empty($data->new_password) ? 'present' : 'missing'));
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid request - Token or new password missing",
            "debug" => [
                "has_token" => !empty($token),
                "has_new_password" => !empty($data->new_password)
            ]
        ]);
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
