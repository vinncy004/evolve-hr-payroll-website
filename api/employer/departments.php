<?php
/**
 * backend/api/employer/departments.php
 * FINAL CLEAN VERSION — Matches your real SQL schema.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

// CORS + headers
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('departments', 100, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

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
$action = $_GET['action'] ?? null;

try {
    // Get employer's organization
    $orgStmt = $db->prepare("SELECT organization_id FROM employer_users WHERE id = :id LIMIT 1");
    $orgStmt->execute([':id' => $user_id]);
    $orgData = $orgStmt->fetch(PDO::FETCH_ASSOC);

    if (!$orgData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit();
    }

    $organization_id = (int)$orgData['organization_id'];

    // ============================================================
    // GET — List or fetch one department
    // ============================================================
    if ($method === 'GET') {

        // Get single department
        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];

            $query = "
                SELECT 
                    d.id, d.name, d.manager_id, d.is_active, d.created_at, d.updated_at,
                    CONCAT(e.first_name, ' ', e.last_name) AS manager_name
                FROM departments d
                LEFT JOIN employees e ON e.id = d.manager_id
                WHERE d.organization_id = :org AND d.id = :id
                LIMIT 1
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([':org' => $organization_id, ':id' => $id]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dept) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Department not found']);
                exit();
            }

            echo json_encode(['success' => true, 'data' => $dept]);
            exit();
        }

        // Get all departments
        $query = "
            SELECT 
                d.id, d.name, d.manager_id, d.is_active, d.created_at, d.updated_at,
                CONCAT(e.first_name, ' ', e.last_name) AS manager_name,
                (SELECT COUNT(*) FROM employees em 
                 WHERE em.department_id = d.id 
                 AND em.employment_status = 'Active') AS employee_count
            FROM departments d
            LEFT JOIN employees e ON e.id = d.manager_id
            WHERE d.organization_id = :org
            ORDER BY d.name ASC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([':org' => $organization_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    // ============================================================
    // POST — Create or assign/remove employee
    // ============================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"));

        // Assign employee to department
        if ($action === 'assignEmployee') {

            if (empty($input->department_id) || empty($input->employee_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'department_id and employee_id required']);
                exit();
            }

            $dept_id = (int)$input->department_id;
            $employee_id = (int)$input->employee_id;

            // Validate department
            $d = $db->prepare("SELECT id FROM departments WHERE id = :id AND organization_id = :org");
            $d->execute([':id' => $dept_id, ':org' => $organization_id]);
            if (!$d->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Department not found']);
                exit();
            }

            // Validate employee
            $e = $db->prepare("SELECT id FROM employees WHERE id = :id AND organization_id = :org");
            $e->execute([':id' => $employee_id, ':org' => $organization_id]);
            if (!$e->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit();
            }

            $stmt = $db->prepare("
                UPDATE employees 
                SET department_id = :dept, updated_at = NOW()
                WHERE id = :emp AND organization_id = :org
            ");

            $stmt->execute([
                ':dept' => $dept_id,
                ':emp' => $employee_id,
                ':org' => $organization_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Employee assigned successfully']);
            exit();
        }

        // Remove employee from department
        if ($action === 'removeEmployee') {

            if (empty($input->employee_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'employee_id required']);
                exit();
            }

            $emp_id = (int)$input->employee_id;

            $stmt = $db->prepare("
                UPDATE employees 
                SET department_id = NULL, updated_at = NOW()
                WHERE id = :emp AND organization_id = :org
            ");
            $stmt->execute([':emp' => $emp_id, ':org' => $organization_id]);

            echo json_encode(['success' => true, 'message' => 'Employee removed from department']);
            exit();
        }

        // CREATE DEPARTMENT
        if (empty($input->name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department name is required']);
            exit();
        }

        // Check duplicate
        $chk = $db->prepare("SELECT id FROM departments WHERE name = :name AND organization_id = :org");
        $chk->execute([':name' => $input->name, ':org' => $organization_id]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Department already exists']);
            exit();
        }

        $ins = $db->prepare("
            INSERT INTO departments (organization_id, name, manager_id, is_active, created_at)
            VALUES (:org, :name, :manager, 1, NOW())
        ");

        $ins->execute([
            ':org' => $organization_id,
            ':name' => $input->name,
            ':manager' => $input->manager_id ?? null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => ['id' => $db->lastInsertId(), 'name' => $input->name]
        ]);
        exit();
    }

    // ============================================================
    // PUT — Update department
    // ============================================================
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents("php://input"));

        if (empty($input->id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department ID required']);
            exit();
        }

        $dept_id = (int)$input->id;

        // Validate department
        $chk = $db->prepare("SELECT id FROM departments WHERE id = :id AND organization_id = :org");
        $chk->execute([':id' => $dept_id, ':org' => $organization_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Department not found']);
            exit();
        }

        $fields = [];
        $params = [':id' => $dept_id];

        if (isset($input->name)) {
            $fields[] = "name = :name";
            $params[':name'] = $input->name;
        }

        if (isset($input->manager_id)) {
            $fields[] = "manager_id = :manager_id";
            $params[':manager_id'] = $input->manager_id ?: null;
        }

        if (isset($input->is_active)) {
            $fields[] = "is_active = :active";
            $params[':active'] = $input->is_active;
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit();
        }

        $fields[] = "updated_at = NOW()";

        $sql = "UPDATE departments SET ".implode(', ', $fields)." WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
        exit();
    }

    // ============================================================
    // DELETE — Delete department
    // ============================================================
    if ($method === 'DELETE') {
        $dept_id = (int)($_GET['id'] ?? 0);

        if (!$dept_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department ID missing']);
            exit();
        }

        // Check if department has active employees
        $ec = $db->prepare("
            SELECT COUNT(*) AS c
            FROM employees
            WHERE department_id = :dept AND employment_status = 'Active'
        ");
        $ec->execute([':dept' => $dept_id]);
        $count = (int)$ec->fetch(PDO::FETCH_ASSOC)['c'];

        if ($count > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Cannot delete department with $count active employees"]);
            exit();
        }

        $del = $db->prepare("DELETE FROM departments WHERE id = :id AND organization_id = :org");
        $del->execute([':id' => $dept_id, ':org' => $organization_id]);

        if ($del->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Department not found']);
            exit();
        }

        echo json_encode(['success' => true, 'message' => 'Department deleted successfully']);
        exit();
    }

    // Default
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();


} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => (defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Database error')
    ]);
    exit();
}
?>
