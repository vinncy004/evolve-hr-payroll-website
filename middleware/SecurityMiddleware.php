<?php
/**
 * Security Middleware
 * Handles CORS, input sanitization, rate limiting, and security headers
 * backend/middleware/SecurityMiddleware.php
 */

require_once __DIR__ . '/../config/database.php';

class SecurityMiddleware {

    /**
     * Apply CORS headers based on configuration
     */
    public static function handleCORS() {
        $config = Database::getConfig('cors');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        if ($origin) {
            // browser request
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // curl or non-browser request
            header("Access-Control-Allow-Origin: *");
        }


        header("Access-Control-Allow-Credentials: " . ($config['allow_credentials'] ? 'true' : 'false'));
        header("Access-Control-Allow-Methods: " . implode(', ', $config['allowed_methods']));
        header("Access-Control-Allow-Headers: " . implode(', ', $config['allowed_headers']));
        header("Access-Control-Max-Age: " . $config['max_age']);

        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Apply security headers
     */
    public static function applySecurityHeaders() {
        // Prevent clickjacking
        header("X-Frame-Options: DENY");

        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");

        // Enable XSS protection
        header("X-XSS-Protection: 1; mode=block");

        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // Content Security Policy (adjust as needed)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");

        // HSTS (only in production with HTTPS)
        if (Database::getConfig('app.env') === 'production' &&
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $maxRequests = null, $window = null) {
        $config = Database::getConfig('rate_limit');

        if (!$config['enabled']) {
            return true;
        }

        $maxRequests = $maxRequests ?? $config['max_requests'];
        $window = $window ?? $config['window'];

        // Use IP + identifier as key
        $ip = self::getClientIP();
        $key = "rate_limit:{$ip}:{$identifier}";

        // Check rate limit (implement with Redis or file-based cache)
        $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.cache';

        $requests = [];
        if (file_exists($cacheFile)) {
            $requests = json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        // Remove old requests outside window
        $now = time();
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });

        if (count($requests) >= $maxRequests) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $window
            ]);
            exit();
        }

        // Add current request
        $requests[] = $now;
        file_put_contents($cacheFile, json_encode($requests));

        return true;
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace(chr(0), '', $data);
            // Trim whitespace
            $data = trim($data);
            // Strip tags (for basic protection)
            // $data = strip_tags($data); // Uncomment if you want to strip HTML
        }

        return $data;
    }

    /**
     * Validate required fields
     */
    public static function validateRequired($data, $requiredFields) {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ]);
            exit();
        }

        return true;
    }

    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        $config = Database::getConfig('security');
        $minLength = $config['password_min_length'];

        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters";
        }

        if ($config['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if ($config['password_require_number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if ($config['password_require_special'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    /**
     * Verify JWT token
     */
    /**
 * Verify JWT token (actually our session token) and return full session data
 */
    public static function verifyToken($token = null) {
        // Extract token from standard header locations if not provided
        if ($token === null) {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';

            if (!$auth_header) {
                $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            }

            if (!$auth_header) {
                $auth_header = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_SERVER['REDIRECT_HTTP_X_AUTH_TOKEN'] ?? '';
            }

            if (preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
                $token = $matches[1];
            }

            if (!$token) {
                $token = $_GET['token'] ?? $_POST['token'] ?? $_COOKIE['evolve_token'] ?? null;
            }
        }

        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No token provided']);
            exit();
        }

        try {
            $db = (new Database())->getConnection();

            $query = "
                SELECT 
                    us.user_id,
                    us.user_type,
                    COALESCE(eu.role, emu.role, 'employee') AS role,
                    COALESCE(eu.employee_id, NULL) AS employee_id,
                    COALESCE(eu.organization_id, emu.organization_id, e.organization_id, NULL) AS organization_id,
                    COALESCE(e.manager_id, NULL) AS manager_id
                FROM user_sessions us
                LEFT JOIN employee_users eu ON us.user_id = eu.id AND us.user_type = 'employee'
                LEFT JOIN employees e ON eu.employee_id = e.id
                LEFT JOIN employer_users emu ON us.user_id = emu.id AND us.user_type = 'employer'
                WHERE us.session_token = :token
                AND us.is_active = 1
                AND us.expires_at > NOW()
                LIMIT 1
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([':token' => $token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
                exit();
            }

            $session['employee_id'] = ($session['user_type'] === 'employee')
                ? (int)($session['employee_id'] ?? 0)
                : null;
            $session['organization_id'] = isset($session['organization_id']) ? (int)$session['organization_id'] : null;
            $session['role'] = strtolower($session['role'] ?? ($session['user_type'] ?? 'employee'));

            return $session;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Authentication failed']);
            exit();
        }
    }

    /**
     * Enforce a single role for the current session.
     */
    public static function requireRole($requiredRole, $session = null) {
        $session = $session ?? self::verifyToken();
        $role = strtolower($session['role'] ?? $session['user_type'] ?? 'employee');

        if ($role !== strtolower($requiredRole)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied for this role']);
            exit();
        }
    }

    /**
     * Enforce one of several allowed roles.
     */
    public static function requireAnyRole(array $allowedRoles, $session = null) {
        $session = $session ?? self::verifyToken();
        $role = strtolower($session['role'] ?? $session['user_type'] ?? 'employee');

        if (!in_array($role, array_map('strtolower', $allowedRoles), true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit();
        }
    }

    /**
     * Prevent SQL injection by using parameterized queries
     * This is a helper to remind developers to use prepared statements
     */
    public static function prepareSafeQuery($pdo, $query, $params = []) {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, self::getPDOType($value));
        }
        return $stmt;
    }

    /**
     * Get PDO parameter type
     */
    private static function getPDOType($value) {
        if (is_int($value)) return PDO::PARAM_INT;
        if (is_bool($value)) return PDO::PARAM_BOOL;
        if (is_null($value)) return PDO::PARAM_NULL;
        return PDO::PARAM_STR;
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []) {
        $config = Database::getConfig('logging');

        if (!$config['enabled']) {
            return;
        }

        $logPath = $config['path'];
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $logFile = $logPath . '/security_' . date('Y-m-d') . '.log';

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];

        file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND);
    }

    
}