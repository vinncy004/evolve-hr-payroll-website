<?php
/**
 * Organization Signup API
 * 
 * Allows new tenants to self-register with:
 * - Organization details
 * - Admin user account
 * - Subscription plan selection
 * - Email verification
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// Get request data
$data = json_decode(file_get_contents("php://input"));

// Validate required fields
$required = ['organization_name', 'organization_code', 'admin_first_name', 
             'admin_last_name', 'admin_email', 'admin_username', 'admin_password'];

foreach ($required as $field) {
    if (empty($data->$field)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Missing required field: " . $field
        ]);
        exit();
    }
}

// Validate email format
if (!filter_var($data->admin_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid email address"
    ]);
    exit();
}

// Validate password strength
if (strlen($data->admin_password) < 8) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters"
    ]);
    exit();
}

// Validate organization code format (alphanumeric, no spaces)
if (!preg_match('/^[A-Z0-9]{3,10}$/', $data->organization_code)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Organization code must be 3-10 uppercase alphanumeric characters (e.g., ABC123)"
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if organization code already exists
    $check_query = "SELECT id FROM organizations WHERE organization_code = :code";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':code', $data->organization_code);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Organization code already exists. Please choose a different code."
        ]);
        exit();
    }
    
    // Check if admin email already exists
    $email_check = "SELECT id FROM employer_users WHERE email = :email";
    $email_stmt = $db->prepare($email_check);
    $email_stmt->bindParam(':email', $data->admin_email);
    $email_stmt->execute();
    
    if ($email_stmt->fetch()) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Email already registered. Please use a different email."
        ]);
        exit();
    }
    
    // Check if admin username already exists
    $username_check = "SELECT id FROM employer_users WHERE username = :username";
    $username_stmt = $db->prepare($username_check);
    $username_stmt->bindParam(':username', $data->admin_username);
    $username_stmt->execute();
    
    if ($username_stmt->fetch()) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Username already exists. Please choose a different username."
        ]);
        exit();
    }
    
    // 1. Create organization
    $subscription_plan = $data->subscription_plan ?? 'trial';
    $valid_plans = ['trial', 'basic', 'professional', 'enterprise'];
    
    if (!in_array($subscription_plan, $valid_plans)) {
        $subscription_plan = 'trial';
    }
    
    $org_query = "INSERT INTO organizations (
        organization_name, 
        organization_code, 
        subscription_plan,
        contact_email,
        contact_phone,
        address,
        is_active,
        trial_ends_at,
        created_at
    ) VALUES (
        :name, 
        :code, 
        :plan,
        :email,
        :phone,
        :address,
        1,
        DATE_ADD(NOW(), INTERVAL 30 DAY),
        NOW()
    )";
    
    $org_stmt = $db->prepare($org_query);
    $org_stmt->execute([
        ':name' => $data->organization_name,
        ':code' => $data->organization_code,
        ':plan' => $subscription_plan,
        ':email' => $data->admin_email,
        ':phone' => $data->phone ?? null,
        ':address' => $data->address ?? null
    ]);
    
    $organization_id = $db->lastInsertId();
    
    // 2. Create admin user
    $password_hash = password_hash($data->admin_password, PASSWORD_BCRYPT);
    
    $admin_query = "INSERT INTO employer_users (
        organization_id,
        username,
        email,
        password_hash,
        first_name,
        last_name,
        role,
        is_active,
        created_at
    ) VALUES (
        :org_id,
        :username,
        :email,
        :password_hash,
        :first_name,
        :last_name,
        'super_admin',
        1,
        NOW()
    )";
    
    $admin_stmt = $db->prepare($admin_query);
    $admin_stmt->execute([
        ':org_id' => $organization_id,
        ':username' => $data->admin_username,
        ':email' => $data->admin_email,
        ':password_hash' => $password_hash,
        ':first_name' => $data->admin_first_name,
        ':last_name' => $data->admin_last_name
    ]);
    
    $admin_user_id = $db->lastInsertId();
    
    // 3. Create default departments for the organization
    $default_departments = [
        ['HR', 'Human Resources', 'Manages employee relations and recruitment'],
        ['FIN', 'Finance', 'Handles financial operations and payroll'],
        ['IT', 'Information Technology', 'Manages technology infrastructure'],
        ['OPS', 'Operations', 'Oversees daily business operations'],
        ['SALES', 'Sales', 'Manages customer acquisition and revenue']
    ];
    
    $dept_query = "INSERT INTO departments (organization_id, code, name, description) 
                   VALUES (:org_id, :code, :name, :description)";
    $dept_stmt = $db->prepare($dept_query);
    
    foreach ($default_departments as $dept) {
        $dept_stmt->execute([
            ':org_id' => $organization_id,
            ':code' => $dept[0],
            ':name' => $dept[1],
            ':description' => $dept[2]
        ]);
    }
    
    // 4. Create default positions
    $default_positions = [
        ['MGR', 'Manager', 'Department Manager'],
        ['SUP', 'Supervisor', 'Team Supervisor'],
        ['EXE', 'Executive', 'Senior Executive'],
        ['OFF', 'Officer', 'Operations Officer'],
        ['AST', 'Assistant', 'Assistant Role']
    ];
    
    $pos_query = "INSERT INTO positions (organization_id, code, title, description) 
                  VALUES (:org_id, :code, :title, :description)";
    $pos_stmt = $db->prepare($pos_query);
    
    foreach ($default_positions as $pos) {
        $pos_stmt->execute([
            ':org_id' => $organization_id,
            ':code' => $pos[0],
            ':title' => $pos[1],
            ':description' => $pos[2]
        ]);
    }
    
    // 5. Log the signup
    $audit_query = "INSERT INTO audit_log (
        user_id, user_type, action, table_name, record_id,
        new_values, ip_address, user_agent
    ) VALUES (
        :user_id, 'employer', 'organization_signup', 'organizations', :record_id,
        :new_values, :ip_address, :user_agent
    )";
    
    $audit_stmt = $db->prepare($audit_query);
    $audit_stmt->execute([
        ':user_id' => $admin_user_id,
        ':record_id' => $organization_id,
        ':new_values' => json_encode([
            'organization_name' => $data->organization_name,
            'organization_code' => $data->organization_code,
            'subscription_plan' => $subscription_plan,
            'admin_username' => $data->admin_username
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Send welcome email (non-blocking - don't fail if email fails)
    try {
        require_once '../utils/EmailService.php';
        $emailService = new EmailService();
        $emailService->sendWelcomeEmail(
            $data->admin_email,
            $data->admin_first_name . ' ' . $data->admin_last_name,
            $data->organization_name,
            $data->admin_username
        );
    } catch (Exception $e) {
        // Log error but don't fail registration
        error_log("Failed to send welcome email: " . $e->getMessage());
    }
    
    // Success response
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Organization registered successfully!",
        "data" => [
            "organization_id" => $organization_id,
            "organization_name" => $data->organization_name,
            "organization_code" => $data->organization_code,
            "subscription_plan" => $subscription_plan,
            "trial_ends_in_days" => 30,
            "admin_username" => $data->admin_username,
            "admin_email" => $data->admin_email,
            "next_step" => "Login with your credentials to start managing your organization"
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Registration failed: " . $e->getMessage()
    ]);
}
?>
