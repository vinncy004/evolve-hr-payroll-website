<?php
/**
 * backend/api/employee/profile.php
 * Employee Self-Service (ESS) profile endpoints.
 * - GET returns the authenticated employee profile
 * - PUT updates limited fields (phone, personal_email, photo, emergency contacts)
 *
 * Note: Authentication uses employee_users token. SecurityMiddleware::verifyToken() must return user_type 'employee' and user_id.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('employee_profile', 200, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = (new Database())->getConnection();

try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_id = $session['user_id'] ?? null;
$user_type = $session['user_type'] ?? null;

if ($user_type !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Map employee_users.user_id -> employees.id
try {
    // find employee id from employee_users
    $u = $db->prepare("SELECT employee_id FROM employee_users WHERE id = :id LIMIT 1");
    $u->execute([':id' => $user_id]);
    $row = $u->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee record not found']);
        exit();
    }
    $employee_id = (int)$row['employee_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = "SELECT
        e.id,
        e.employee_no,
        e.first_name,
        e.middle_name,
        e.last_name,
        CONCAT(e.first_name, ' ', COALESCE(e.middle_name,''), ' ', e.last_name) AS full_name,
        e.phone,
        e.personal_email,
        e.work_email,
        e.photo,

        -- NEW FIELDS ADDED
        e.residential_address,
        e.postal_address,
        e.county,
        e.sub_county,
        e.marital_status,
        e.nationality,
        e.date_of_birth,

        e.department_id,
        d.name AS department_name,
        e.position_id,
        p.title AS position_title,
        e.manager_id,
        CONCAT(m.first_name,' ',COALESCE(m.middle_name,''),' ',m.last_name) AS manager_name,

        e.employment_status,
        e.employment_type,
        e.basic_salary,
        e.currency,
        e.hire_date,
        e.created_at
      FROM employees e
      LEFT JOIN departments d ON d.id = e.department_id
      LEFT JOIN positions p ON p.id = e.position_id
      LEFT JOIN employees m ON m.id = e.manager_id
      WHERE e.id = :id
      LIMIT 1";

        $stmt = $db->prepare($q);
        $stmt->execute([':id' => $employee_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit();
        }
        echo json_encode(['success' => true, 'data' => $emp]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'));
        if (!is_object($data)) $data = json_decode('{}');

        // allow only limited updatable fields in ESS
        $allowed = ['phone', 'personal_email', 'photo', 'postal_address', 'residential_address', 'emergency_contact_name', 'emergency_contact_phone'];
        $sets = [];
        $params = [':id' => $employee_id];
        foreach ($allowed as $f) {
            if (isset($data->$f)) {
                $sets[] = "$f = :$f";
                $params[":$f"] = $data->$f;
            }
        }

        if (empty($sets)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No allowed fields to update']);
            exit();
        }

        $sets[] = "updated_at = NOW()";
        $sql = "UPDATE employees SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Profile updated']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    $err = (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Database error';
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $err]);
    exit();
}
?>

