<?php
/**
 * Positions API
 * Handles position/job title CRUD operations
 * backend/api/employer/positions.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);




require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

// Apply security measures
SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('positions', 100, 60);




$database = new Database();
$db = $database->getConnection();

$request_method = $_SERVER["REQUEST_METHOD"];

// Verify authentication
$session = SecurityMiddleware::verifyToken();
$user_id = $session['user_id'];
$user_type = $session['user_type'];

// Only employers can access
if ($user_type !== 'employer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

/**
 * GET - List all positions
 */
if ($request_method === 'GET') {
    try {
        // Get employer's organization
        $org_query = "SELECT organization_id FROM employer_users WHERE id = :user_id";
        $org_stmt = $db->prepare($org_query);
        $org_stmt->execute([':user_id' => $user_id]);
        $org_data = $org_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$org_data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Organization not found']);
            exit();
        }

        $organization_id = $org_data['organization_id'];

        // Get positions with employee count
        $query = "SELECT
            p.id,
            p.title,
            p.description,
            p.department_id,
            p.level,
            p.is_active,
            p.created_at,
            d.name AS department_name,
            COUNT(e.id) AS employee_count
        FROM positions p
        LEFT JOIN departments d 
            ON p.department_id = d.id
        LEFT JOIN employees e 
            ON p.id = e.position_id 
            AND e.employment_status = 'active'
        WHERE p.organization_id = :organization_id
        GROUP BY p.id
        ORDER BY p.title";

        $stmt = $db->prepare($query);
        $stmt->execute([':organization_id' => $organization_id]);
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $positions
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch positions',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * POST - Create position
 */
elseif ($request_method === 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"));

        // Validate required fields
        SecurityMiddleware::validateRequired((array)$data, ['title']);

        // Get employer's organization
        $org_query = "SELECT organization_id FROM employer_users WHERE id = :user_id";
        $org_stmt = $db->prepare($org_query);
        $org_stmt->execute([':user_id' => $user_id]);
        $org_data = $org_stmt->fetch(PDO::FETCH_ASSOC);
        $organization_id = $org_data['organization_id'];

        // Insert position
        $insert_query = "INSERT INTO positions (
            organization_id, title, description, department_id, level, is_active, created_at
        ) VALUES (
            :organization_id, :title, :description, :department_id, :level, 1, NOW()
        )";

        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->execute([
            ':organization_id' => $organization_id,
            ':title' => $data->title,
            ':description' => $data->description ?? null,
            ':department_id' => $data->department_id ?? null,
            ':level' => $data->level ?? 'junior'
        ]);

        $position_id = $db->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Position created successfully',
            'data' => [
                'id' => $position_id,
                'title' => $data->title
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create position',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * PUT - Update position
 */
elseif ($request_method === 'PUT') {
    try {
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Position ID required']);
            exit();
        }

        // Get employer's organization
        $org_query = "SELECT organization_id FROM employer_users WHERE id = :user_id";
        $org_stmt = $db->prepare($org_query);
        $org_stmt->execute([':user_id' => $user_id]);
        $org_data = $org_stmt->fetch(PDO::FETCH_ASSOC);
        $organization_id = $org_data['organization_id'];

        // Verify position belongs to organization
        $check_query = "SELECT id FROM positions WHERE id = :id AND organization_id = :org_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':id' => $data->id, ':org_id' => $organization_id]);
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Position not found']);
            exit();
        }

        // Build update query
        $updates = [];
        $params = [':id' => $data->id];

        $updatable_fields = ['title', 'description', 'department_id', 'level', 'is_active'];

        foreach ($updatable_fields as $field) {
            if (isset($data->$field)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data->$field;
            }
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit();
        }

        $updates[] = "updated_at = NOW()";
        $update_query = "UPDATE positions SET " . implode(', ', $updates) . " WHERE id = :id";

        $stmt = $db->prepare($update_query);
        $stmt->execute($params);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Position updated successfully'
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update position',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * DELETE - Delete position
 */
elseif ($request_method === 'DELETE') {
    try {
        $position_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$position_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Position ID required']);
            exit();
        }

        // Get employer's organization
        $org_query = "SELECT organization_id FROM employer_users WHERE id = :user_id";
        $org_stmt = $db->prepare($org_query);
        $org_stmt->execute([':user_id' => $user_id]);
        $org_data = $org_stmt->fetch(PDO::FETCH_ASSOC);
        $organization_id = $org_data['organization_id'];

        // Check if position has employees
        $emp_check = "SELECT COUNT(*) as count FROM employees WHERE position_id = :pos_id AND employment_status = 'active'";
        $emp_stmt = $db->prepare($emp_check);
        $emp_stmt->execute([':pos_id' => $position_id]);
        $emp_count = $emp_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($emp_count > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Cannot delete position with {$emp_count} active employees"
            ]);
            exit();
        }

        // Delete position
        $delete_query = "DELETE FROM positions WHERE id = :id AND organization_id = :org_id";
        $stmt = $db->prepare($delete_query);
        $stmt->execute([':id' => $position_id, ':org_id' => $organization_id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Position not found']);
            exit();
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete position',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Invalid method
 */
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
