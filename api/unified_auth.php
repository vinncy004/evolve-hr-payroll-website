<?php
/**
 * Unified Authentication API
 * Actions (POST JSON body or ?action=):
 *   login, logout, change_password, forgot_password, reset_password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../utils/EmailService.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
header('Content-Type: application/json; charset=UTF-8');

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? 'login');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    switch ($action) {
        case 'login':
            handleLogin($db, $input);
            break;
        case 'logout':
            handleLogout($db);
            break;
        case 'change_password':
            handleChangePassword($db, $input);
            break;
        case 'forgot_password':
            handleForgotPassword($db, $input);
            break;
        case 'reset_password':
            handleResetPassword($db, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('[UNIFIED AUTH] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal error occurred']);
}

function handleLogin(PDO $db, array $input): void
{
    SecurityMiddleware::checkRateLimit('login', 20, 60);

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }

    $sqlEmployer = "
        SELECT
            eu.id, eu.username, eu.email, eu.password_hash, eu.role AS db_role,
            eu.organization_id, eu.is_active, eu.failed_login_attempts, eu.locked_until,
            eu.first_name, eu.last_name, eu.phone_number, eu.force_password_change,
            o.organization_name, o.organization_code,
            'employer' AS user_type
        FROM employer_users eu
        JOIN organizations o ON eu.organization_id = o.id
        WHERE eu.username = :username AND eu.is_active = 1 AND o.status = 'active'
        LIMIT 1";

    $stmt = $db->prepare($sqlEmployer);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $sqlEmployee = "
            SELECT
                eu.id, eu.username, eu.email, eu.password_hash, eu.role AS db_role,
                eu.employee_id, eu.is_active, eu.force_password_change,
                eu.failed_login_attempts, eu.locked_until,
                e.first_name, e.last_name, e.organization_id,
                e.department_id, e.position_id,
                d.name AS department_name, p.title AS position_name,
                o.organization_name, o.organization_code,
                'employee' AS user_type
            FROM employee_users eu
            JOIN employees e ON eu.employee_id = e.id
            JOIN organizations o ON e.organization_id = o.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE eu.username = :username
              AND eu.is_active = 1
              AND o.status = 'active'
              AND e.employment_status = 'active'
            LIMIT 1";

        $stmt = $db->prepare($sqlEmployee);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        logLogin($db, null, null, $username, null, 'failed', 'User not found');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        return;
    }

    $user['role'] = strtolower($user['db_role'] ?? ($user['user_type'] === 'employer' ? 'admin' : 'employee'));

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is locked. Try again later.']);
        return;
    }

    if (!password_verify($password, $user['password_hash'])) {
        recordFailedLogin($db, $user);
        logLogin($db, $user['user_type'], $user['id'], $user['username'], $user['email'] ?? '', 'failed', 'Invalid password');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        return;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $session = $db->prepare("
        INSERT INTO user_sessions (user_type, user_id, session_token, ip_address, user_agent, expires_at, is_active)
        VALUES (:type, :uid, :token, :ip, :agent, :exp, 1)
    ");
    $session->execute([
        ':type' => $user['user_type'],
        ':uid' => $user['id'],
        ':token' => $token,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ':exp' => $expires,
    ]);

    $table = $user['user_type'] === 'employer' ? 'employer_users' : 'employee_users';
    $db->prepare("UPDATE {$table} SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = :id")
        ->execute([':id' => $user['id']]);

    logLogin($db, $user['user_type'], $user['id'], $user['username'], $user['email'] ?? '', 'success', null);

    $response = [
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? '',
            'role' => $user['role'],
            'user_type' => $user['user_type'],
            'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'organization_id' => (int)($user['organization_id'] ?? 0),
        ],
    ];

    if ($user['user_type'] === 'employer') {
        $response['user']['organization_name'] = $user['organization_name'];
        $response['user']['organization_code'] = $user['organization_code'];
    } else {
        $response['user']['employee_id'] = (int)$user['employee_id'];
        $response['user']['department_id'] = $user['department_id'];
        $response['user']['department_name'] = $user['department_name'];
        $response['user']['position_id'] = $user['position_id'];
        $response['user']['position_name'] = $user['position_name'];
        $response['force_password_change'] = (int)($user['force_password_change'] ?? 0);
    }

    echo json_encode($response);
}

function handleLogout(PDO $db): void
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = :token")
            ->execute([':token' => $m[1]]);
    }
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}

function handleChangePassword(PDO $db, array $input): void
{
    $session = SecurityMiddleware::verifyToken();
    $current = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if ($current === '' || $newPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current and new password are required']);
        return;
    }

    $validation = SecurityMiddleware::validatePassword($newPassword);
    if ($validation !== true) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode('. ', $validation)]);
        return;
    }

    $table = $session['user_type'] === 'employer' ? 'employer_users' : 'employee_users';
    $stmt = $db->prepare("SELECT password_hash FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $session['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($current, $row['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->prepare("UPDATE {$table} SET password_hash = :hash, force_password_change = 0 WHERE id = :id")
        ->execute([':hash' => $hash, ':id' => $session['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
}

function handleForgotPassword(PDO $db, array $input): void
{
    SecurityMiddleware::checkRateLimit('forgot_password', 5, 300);

    $identifier = trim($input['username'] ?? $input['email'] ?? '');
    if ($identifier === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username or email is required']);
        return;
    }

    $user = findUserByIdentifier($db, $identifier);
    // Always return success to prevent user enumeration
    if (!$user) {
        echo json_encode(['success' => true, 'message' => 'If the account exists, a reset link has been sent.']);
        return;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_type = :type AND user_id = :uid AND used_at IS NULL")
        ->execute([':type' => $user['user_type'], ':uid' => $user['id']]);

    $db->prepare("
        INSERT INTO password_reset_tokens (user_type, user_id, token, expires_at)
        VALUES (:type, :uid, :token, :exp)
    ")->execute([
        ':type' => $user['user_type'],
        ':uid' => $user['id'],
        ':token' => hash('sha256', $token),
        ':exp' => $expires,
    ]);

    $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost/payroll/BACKEND/pages', '/');
    $resetLink = $baseUrl . '/reset-password.html?token=' . urlencode($token) . '&type=' . urlencode($user['user_type']);

    if (!empty($user['email'])) {
        try {
            $mailer = new EmailService();
            $mailer->sendPasswordResetEmail($user['email'], $user['full_name'] ?? $user['username'], $resetLink);
        } catch (Exception $e) {
            error_log('[FORGOT PASSWORD EMAIL] ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'If the account exists, a reset link has been sent.',
        'reset_link' => (getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'development') ? $resetLink : null,
    ]);
}

function handleResetPassword(PDO $db, array $input): void
{
    $token = trim($input['token'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    $userType = $input['user_type'] ?? 'employee';

    if ($token === '' || $newPassword === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token and new password are required']);
        return;
    }

    $validation = SecurityMiddleware::validatePassword($newPassword);
    if ($validation !== true) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode('. ', $validation)]);
        return;
    }

    $hash = hash('sha256', $token);
    $stmt = $db->prepare("
        SELECT * FROM password_reset_tokens
        WHERE token = :token AND user_type = :type AND used_at IS NULL AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':token' => $hash, ':type' => $userType]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        return;
    }

    $table = $userType === 'employer' ? 'employer_users' : 'employee_users';
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->prepare("UPDATE {$table} SET password_hash = :hash, force_password_change = 0, failed_login_attempts = 0, locked_until = NULL WHERE id = :id")
        ->execute([':hash' => $passwordHash, ':id' => $reset['user_id']]);

    $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id")
        ->execute([':id' => $reset['id']]);

    $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_type = :type AND user_id = :uid")
        ->execute([':type' => $userType, ':uid' => $reset['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
}

function findUserByIdentifier(PDO $db, string $identifier): ?array
{
    foreach (['employer_users' => 'employer', 'employee_users' => 'employee'] as $table => $type) {
        $join = $type === 'employee' ? 'JOIN employees e ON eu.employee_id = e.id' : '';
        $nameFields = $type === 'employee'
            ? "e.first_name, e.last_name"
            : "eu.first_name, eu.last_name";

        $sql = "SELECT eu.id, eu.email, eu.username, {$nameFields}, '{$type}' AS user_type
                FROM {$table} eu {$join}
                WHERE eu.username = :id OR eu.email = :id2
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $identifier, ':id2' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            return $row;
        }
    }
    return null;
}

function recordFailedLogin(PDO $db, array $user): void
{
    $attempts = (int)$user['failed_login_attempts'] + 1;
    $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;
    $table = $user['user_type'] === 'employer' ? 'employer_users' : 'employee_users';
    $db->prepare("UPDATE {$table} SET failed_login_attempts = :a, locked_until = :l WHERE id = :id")
        ->execute([':a' => $attempts, ':l' => $lockUntil, ':id' => $user['id']]);
}

function logLogin(PDO $db, ?string $type, ?int $uid, string $username, ?string $email, string $status, ?string $reason): void
{
    $db->prepare("
        INSERT INTO login_logs (user_type, user_id, username, email, login_status, failure_reason, ip_address, user_agent)
        VALUES (:type, :uid, :uname, :email, :status, :reason, :ip, :agent)
    ")->execute([
        ':type' => $type ?? 'unknown',
        ':uid' => $uid,
        ':uname' => $username,
        ':email' => $email ?? '',
        ':status' => $status,
        ':reason' => $reason,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);
}
