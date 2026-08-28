<?php
/**
 * backend/api/employer/employees.php
 * Final version — matches final_schema.sql
 *
 * Supported:
 *  - GET (list with pagination/search or single ?id=)
 *  - POST (create)
 *  - PUT (update)
 *  - DELETE (soft delete: employment_status = 'Terminated', date_terminated)
 *
 * Notes:
 *  - Uses organization scoping via employer_users.organization_id
 *  - Employee unique field is employee_no
 */
/**
 * backend/api/employer/employees.php
 * Employer-side Employee Management (Day 4)
 * Uses employee_no (not employee_number) and full field set.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

// Headers / CORS
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('employees', 200, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = (new Database())->getConnection();

// Authenticate employer
try {
    $session = SecurityMiddleware::verifyToken();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}
$user_id = $session['user_id'] ?? null;
$user_type = $session['user_type'] ?? null;
if ($user_type !== 'employer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // organization context
    $orgStmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = :id LIMIT 1");
    $orgStmt->execute([':id' => $user_id]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
    if (!$org) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }
    $organization_id = (int)$org['organization_id'];

    // ---------------------- GET ----------------------
    if ($method === 'GET') {
        // single by id
        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $q = "SELECT
                    e.*,
                    CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS full_name,
                    d.name AS department_name,
                    p.title AS position_title,
                    CONCAT(m.first_name, ' ', COALESCE(m.middle_name, ''), ' ', m.last_name) AS manager_name
                  FROM employees e
                  LEFT JOIN departments d ON d.id = e.department_id
                  LEFT JOIN positions p ON p.id = e.position_id
                  LEFT JOIN employees m ON m.id = e.manager_id
                  WHERE e.id = :id AND e.organization_id = :org_id
                  LIMIT 1";
            $stmt = $db->prepare($q);
            $stmt->execute([':id' => $id, ':org_id' => $organization_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit();
            }
            echo json_encode(['success' => true, 'data' => $emp]);
            exit();
        }

        // list with pagination/search/filter
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, (int)($_GET['limit'] ?? 50));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        $status = isset($_GET['status']) ? trim($_GET['status']) : null;
        $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
        $position_id = isset($_GET['position_id']) ? (int)$_GET['position_id'] : null;

        $where = ["e.organization_id = :org_id"];
        $params = [':org_id' => $organization_id];

        if ($search !== '') {
            $where[] = "(e.first_name LIKE :search OR e.middle_name LIKE :search OR e.last_name LIKE :search OR e.employee_no LIKE :search OR e.work_email LIKE :search OR e.phone LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($status) {
            // Normalize to DB enum style (e.g., 'Active')
            $params[':status'] = ucfirst(strtolower($status));
            $where[] = "e.employment_status = :status";
        }
        if ($department_id) {
            $where[] = "e.department_id = :department_id";
            $params[':department_id'] = $department_id;
        }
        if ($position_id) {
            $where[] = "e.position_id = :position_id";
            $params[':position_id'] = $position_id;
        }

        $where_sql = implode(' AND ', $where);

        $countQ = "SELECT COUNT(*) AS total FROM employees e WHERE {$where_sql}";
        $countStmt = $db->prepare($countQ);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $q = "SELECT
                e.id,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS full_name,
                e.phone,
                e.work_email,
                e.employment_status,
                e.employment_type,
                e.basic_salary,
                e.currency,
                e.hire_date,
                e.department_id,
                d.name AS department_name,
                e.position_id,
                p.title AS position_title,
                e.manager_id,
                CONCAT(m.first_name, ' ', COALESCE(m.middle_name, ''), ' ', m.last_name) AS manager_name,
                e.photo,
                e.created_at
              FROM employees e
              LEFT JOIN departments d ON d.id = e.department_id
              LEFT JOIN positions p ON p.id = e.position_id
              LEFT JOIN employees m ON m.id = e.manager_id
              WHERE {$where_sql}
              ORDER BY e.created_at DESC
              LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($q);
        // bind dynamic params
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => $limit ? (int)ceil($total / $limit) : 0
            ]
        ]);
        exit();
    }

        // ---------------------- POST (create) ----------------------
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'));
        if (!is_object($data)) $data = json_decode('{}');

        // required
        $required = ['employee_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'work_email', 'hire_date', 'department_id', 'position_id'];
        foreach ($required as $r) {
            if (empty($data->$r)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$r is required"]);
                exit();
            }
        }

        // duplicate employee number
        $chk = $db->prepare("SELECT id FROM employees WHERE employee_no = :emp_no AND organization_id = :org_id");
        $chk->execute([':emp_no' => $data->employee_no, ':org_id' => $organization_id]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Employee number already exists']);
            exit();
        }

        // --------------------------------------
        // INSERT INTO employees
        // --------------------------------------
        $ins = $db->prepare("
            INSERT INTO employees (
                organization_id, employee_no, first_name, middle_name, last_name,
                id_number, kra_pin, nssf_no, nhif_no, shif_number,
                phone, personal_email, work_email,
                date_of_birth, gender, marital_status, nationality, passport_number,
                department_id, position_id, manager_id,
                employment_type, employment_status, basic_salary, currency,
                hire_date, probation_end_date, contract_end_date,
                photo, postal_address, residential_address, county, sub_county, created_at
            ) VALUES (
                :organization_id, :employee_no, :first_name, :middle_name, :last_name,
                :id_number, :kra_pin, :nssf_no, :nhif_no, :shif_number,
                :phone, :personal_email, :work_email,
                :date_of_birth, :gender, :marital_status, :nationality, :passport_number,
                :department_id, :position_id, :manager_id,
                :employment_type, :employment_status, :basic_salary, :currency,
                :hire_date, :probation_end_date, :contract_end_date,
                :photo, :postal_address, :residential_address, :county, :sub_county, NOW()
            )
        ");

        $ins->execute([
            ':organization_id' => $organization_id,
            ':employee_no' => $data->employee_no,
            ':first_name' => $data->first_name,
            ':middle_name' => $data->middle_name ?? null,
            ':last_name' => $data->last_name,
            ':id_number' => $data->id_number ?? null,
            ':kra_pin' => $data->kra_pin ?? null,
            ':nssf_no' => $data->nssf_no ?? null,
            ':nhif_no' => $data->nhif_no ?? null,
            ':shif_number' => $data->shif_number ?? null,
            ':phone' => $data->phone,
            ':personal_email' => $data->personal_email ?? null,
            ':work_email' => strtolower(trim($data->work_email)),
            ':date_of_birth' => $data->date_of_birth,
            ':gender' => $data->gender,
            ':marital_status' => $data->marital_status ?? null,
            ':nationality' => $data->nationality ?? 'Kenyan',
            ':passport_number' => $data->passport_number ?? null,
            ':department_id' => $data->department_id,
            ':position_id' => $data->position_id,
            ':manager_id' => $data->manager_id ?? null,
            ':employment_type' => $data->employment_type ?? 'Permanent',
            ':employment_status' => $data->employment_status ?? 'Active',
            ':basic_salary' => isset($data->basic_salary) ? $data->basic_salary : 0.00,
            ':currency' => $data->currency ?? 'KES',
            ':hire_date' => $data->hire_date,
            ':probation_end_date' => $data->probation_end_date ?? null,
            ':contract_end_date' => $data->contract_end_date ?? null,
            ':photo' => $data->photo ?? null,
            ':postal_address' => $data->postal_address ?? null,
            ':residential_address' => $data->residential_address ?? null,
            ':county' => $data->county ?? null,
            ':sub_county' => $data->sub_county ?? null
        ]);

        $employee_id = $db->lastInsertId();

        // --------------------------------------
        // AUTO-CREATE EMPLOYEE LOGIN ACCOUNT
        // --------------------------------------

        $username = strtolower(trim($data->work_email));
        $email = $username;

        // Check if user already exists (avoid duplicates)
        $existingUser = $db->prepare("SELECT id FROM employee_users WHERE email = :email LIMIT 1");
        $existingUser->execute([':email' => $email]);

        if (!$existingUser->fetch()) {

            // default password
            $default_password = 'Welcome@2025';
            $hashed = password_hash($default_password, PASSWORD_BCRYPT);

            $u = $db->prepare("
                INSERT INTO employee_users 
                (employee_id, username, email, password_hash, role, is_active, force_password_change, created_at) 
                VALUES 
                (:eid, :username, :email, :pass, 'employee', 1, 1, NOW())
            ");

            $u->execute([
                ':eid' => $employee_id,
                ':username' => $username,
                ':email' => $email,
                ':pass' => $hashed
            ]);
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Employee created',
            'data' => [
                'id' => $employee_id,
                'employee_no' => $data->employee_no,
                'login_username' => $email,
                'default_password' => 'Welcome@2025'
            ]
        ]);
        exit();
    }


    // ---------------------- PUT (update) ----------------------
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'));
        if (empty($data->id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID required']);
            exit();
        }
        $employee_id = (int)$data->id;

        // verify belongs to org
        $chk = $db->prepare("SELECT id FROM employees WHERE id = :id AND organization_id = :org_id");
        $chk->execute([':id' => $employee_id, ':org_id' => $organization_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit();
        }

        $updatable = [
            'first_name','middle_name','last_name','id_number','kra_pin','nssf_no','nhif_no','shif_number',
            'phone','personal_email','work_email','date_of_birth','gender','marital_status','nationality',
            'passport_number','department_id','position_id','manager_id','employment_type','employment_status',
            'basic_salary','currency','hire_date','probation_end_date','contract_end_date','photo',
            'postal_address','residential_address','county','sub_county'
        ];

        $sets = [];
        $params = [':id' => $employee_id, ':org_id' => $organization_id];
        foreach ($updatable as $f) {
            if (isset($data->$f)) {
                $sets[] = "$f = :$f";
                $params[":$f"] = $data->$f;
            }
        }
        if (empty($sets)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit();
        }
        $sets[] = "updated_at = NOW()";

        $sql = "UPDATE employees SET " . implode(', ', $sets) . " WHERE id = :id AND organization_id = :org_id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Employee updated']);
        exit();
    }

    // ---------------------- DELETE (soft terminate) ----------------------
    if ($method === 'DELETE') {
        $employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$employee_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID required']);
            exit();
        }
        $chk = $db->prepare("SELECT id FROM employees WHERE id = :id AND organization_id = :org_id");
        $chk->execute([':id' => $employee_id, ':org_id' => $organization_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit();
        }

        $del = $db->prepare("UPDATE employees SET employment_status = 'Terminated', contract_end_date = NOW(), updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $del->execute([':id' => $employee_id, ':org_id' => $organization_id]);

        $ud = $db->prepare("UPDATE employee_users SET is_active = 0 WHERE employee_id = :employee_id");
        $ud->execute([':employee_id' => $employee_id]);

        echo json_encode(['success' => true, 'message' => 'Employee terminated']);
        exit();
    }

    // method not allowed
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
