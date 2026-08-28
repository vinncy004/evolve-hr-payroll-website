<?php
/**
 * Apply schema, migration, and seed demo data.
 * Run: php scripts/setup_database.php
 */
require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function runSqlFile(PDO $db, string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException("SQL file not found: $path");
    }
    $sql = file_get_contents($path);
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || stripos($stmt, 'USE ') === 0) {
            continue;
        }
        try {
            $db->exec($stmt);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false
                && strpos($e->getMessage(), 'already exists') === false) {
                echo "Warning: " . substr($stmt, 0, 60) . "... => " . $e->getMessage() . "\n";
            }
        }
    }
}

echo "Applying full schema...\n";
runSqlFile($db, __DIR__ . '/../sql/evolve_payroll_full_schema.sql');

echo "Applying migration...\n";
runSqlFile($db, __DIR__ . '/../sql/migrations/20260727_rbac_tax_settings.sql');

// Seed organization
$org = $db->query("SELECT id FROM organizations WHERE organization_code = 'DEMO001' LIMIT 1")->fetch();
if (!$org) {
    $db->exec("
        INSERT INTO organizations (organization_name, organization_code, email, phone, status)
        VALUES ('Demo Company Ltd', 'DEMO001', 'hr@demo.co.ke', '+254700000000', 'active')
    ");
    echo "Created demo organization.\n";
}
$orgId = (int)$db->query("SELECT id FROM organizations WHERE organization_code = 'DEMO001' LIMIT 1")->fetchColumn();

// Seed admin user (password: Admin@123)
$admin = $db->query("SELECT id FROM employer_users WHERE username = 'admin' LIMIT 1")->fetch();
if (!$admin) {
    $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
    $stmt = $db->prepare("
        INSERT INTO employer_users
            (organization_id, username, email, password_hash, role, first_name, last_name, is_active)
        VALUES (:org, 'admin', 'admin@demo.co.ke', :hash, 'admin', 'System', 'Admin', 1)
    ");
    $stmt->execute([':org' => $orgId, ':hash' => $hash]);
    echo "Created admin user (username: admin, password: Admin@123).\n";
}

// Seed HR user
$hr = $db->query("SELECT id FROM employer_users WHERE username = 'hr' LIMIT 1")->fetch();
if (!$hr) {
    $hash = password_hash('Hr@12345', PASSWORD_BCRYPT);
    $stmt = $db->prepare("
        INSERT INTO employer_users
            (organization_id, username, email, password_hash, role, first_name, last_name, is_active)
        VALUES (:org, 'hr', 'hr@demo.co.ke', :hash, 'hr', 'HR', 'Manager', 1)
    ");
    $stmt->execute([':org' => $orgId, ':hash' => $hash]);
    echo "Created HR user (username: hr, password: Hr@12345).\n";
}

// Seed department + position
$deptId = $db->query("SELECT id FROM departments WHERE organization_id = $orgId LIMIT 1")->fetchColumn();
if (!$deptId) {
    $db->exec("INSERT INTO departments (organization_id, name) VALUES ($orgId, 'General')");
    $deptId = (int)$db->lastInsertId();
}
$posId = $db->query("SELECT id FROM positions WHERE organization_id = $orgId LIMIT 1")->fetchColumn();
if (!$posId) {
    $db->exec("INSERT INTO positions (organization_id, department_id, title) VALUES ($orgId, $deptId, 'Staff')");
    $posId = (int)$db->lastInsertId();
}

// Seed sample employee + portal user
$emp = $db->query("SELECT id FROM employees WHERE employee_number = 'EMP001' LIMIT 1")->fetch();
if (!$emp) {
    $stmt = $db->prepare("
        INSERT INTO employees
            (organization_id, employee_number, employee_no, first_name, last_name,
             work_email, department_id, position_id, employment_status, hire_date)
        VALUES (:org, 'EMP001', 'EMP001', 'Jane', 'Doe', 'jane.doe@demo.co.ke', :dept, :pos, 'active', CURDATE())
    ");
    $stmt->execute([':org' => $orgId, ':dept' => $deptId, ':pos' => $posId]);
    $empId = (int)$db->lastInsertId();

    $hash = password_hash('Employee@123', PASSWORD_BCRYPT);
    $db->prepare("
        INSERT INTO employee_users (employee_id, username, email, password_hash, role, is_active)
        VALUES (:eid, 'jane.doe', 'jane.doe@demo.co.ke', :hash, 'employee', 1)
    ")->execute([':eid' => $empId, ':hash' => $hash]);
    echo "Created sample employee (username: jane.doe, password: Employee@123).\n";
}

echo "Setup complete.\n";
