<?php
/**
 * System verification script — run: php scripts/verify_system.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/CalculationService.php';
require_once __DIR__ . '/../utils/TaxSettingsService.php';

$errors = [];
$pass = 0;

function check(bool $ok, string $label, string $detail = ''): void
{
    global $errors, $pass;
    if ($ok) {
        echo "[PASS] $label\n";
        $pass++;
    } else {
        echo "[FAIL] $label" . ($detail ? " — $detail" : '') . "\n";
        $errors[] = $label;
    }
}

echo "=== Evolve Payroll System Verification ===\n\n";

// DB connection
try {
    $db = (new Database())->getConnection();
    check(true, 'Database connection');
} catch (Exception $e) {
    check(false, 'Database connection', $e->getMessage());
    exit(1);
}

// Required tables
$required = [
    'organizations', 'employer_users', 'employee_users', 'employees',
    'user_sessions', 'payroll', 'leave_requests', 'leave_types', 'leave_balances',
    'attendance', 'payroll_tax_settings', 'paye_tax_bands', 'shif_brackets',
    'password_reset_tokens',
];
$existing = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($required as $t) {
    check(in_array($t, $existing, true), "Table exists: $t");
}

// Seed data
$orgCount = (int)$db->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
check($orgCount > 0, 'Organization seeded', "count=$orgCount");

$adminCount = (int)$db->query("SELECT COUNT(*) FROM employer_users WHERE role IN ('admin','hr')")->fetchColumn();
check($adminCount > 0, 'HR/Admin user exists', "count=$adminCount");

// PHP files syntax / existence
$files = [
    'api/unified_auth.php',
    'api/tax_settings.php',
    'api/dashboard.php',
    'api/leave.php',
    'api/leave_balance.php',
    'api/attendance.php',
    'middleware/RbacMiddleware.php',
    'utils/TaxSettingsService.php',
    'utils/CalculationService.php',
    'pages/login.html',
    'pages/reset-password.html',
    'pages/hr-dashboard.html',
];
foreach ($files as $f) {
    $path = __DIR__ . '/../' . $f;
    check(file_exists($path), "File exists: $f");
}

// CalculationService smoke test
try {
    $result = CalculationService::calculateFromGross(50000, [], 1);
    check(($result['success'] ?? false) && $result['gross_pay'] == 50000, 'Payroll calculation (50k gross)');
    check($result['paye'] >= 0 && $result['shif'] > 0, 'Statutory deductions computed');
} catch (Exception $e) {
    check(false, 'Payroll calculation', $e->getMessage());
}

// Tax settings load
try {
    $config = TaxSettingsService::load(1);
    check(!empty($config['settings']['personal_relief']), 'Tax settings load');
    check(!empty($config['paye_bands']), 'PAYE bands load');
} catch (Exception $e) {
    check(false, 'Tax settings load', $e->getMessage());
}

echo "\n=== Summary: $pass passed, " . count($errors) . " failed ===\n";
exit(count($errors) > 0 ? 1 : 0);
