<?php
// Application Configuration
// backend/config/config.php
define('APP_NAME', 'HR Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost:8000');
define('API_BASE_URL', BASE_URL . '/api');

// Security
define('JWT_SECRET_KEY', 'your-secret-key-change-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 86400); // 24 hours

// File Upload
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Kenyan Compliance Settings
define('MIN_WAGE_KENYA', 15000);
define('STANDARD_WORK_HOURS_PER_DAY', 8);
define('STANDARD_WORK_DAYS_PER_WEEK', 5);
define('OVERTIME_RATE', 1.5);

// PAYE Tax Rates (Kenya 2024)
define('PAYE_BANDS', [
    ['min' => 0, 'max' => 24000, 'rate' => 0.10],
    ['min' => 24001, 'max' => 32333, 'rate' => 0.25],
    ['min' => 32334, 'max' => 500000, 'rate' => 0.30],
    ['min' => 500001, 'max' => 800000, 'rate' => 0.325],
    ['min' => 800001, 'max' => PHP_INT_MAX, 'rate' => 0.35]
]);

// NSSF Rates
define('NSSF_LOWER_LIMIT', 7000);
define('NSSF_UPPER_LIMIT', 36000);
define('NSSF_RATE', 0.06);

// SHIF (Formerly NHIF) Rates
define('SHIF_RATE', 0.0275); // 2.75% of gross salary

// Housing Levy
define('HOUSING_LEVY_RATE', 0.015); // 1.5% of gross salary

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
