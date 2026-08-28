<?php
/**
 * EMPLOYEE BULK UPLOAD API
 * ---------------------------------------
 * Modes:
 *  - preview : Parse + validate CSV, no DB writes
 *  - upload  : Validate + bulk insert (transaction-safe)
 */
// ================== CORS PREFLIGHT FIX ==================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, X-User, Content-Type");
    header("Access-Control-Allow-Credentials: true");
    http_response_code(200);
    exit;
}

/* =========================================================
   DEV ERROR VISIBILITY (REMOVE IN PROD)
   ========================================================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================================================
   STEP 1 — BOOTSTRAP, SECURITY & AUTH
   ========================================================= */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/SecurityMiddleware.php';

header('Content-Type: application/json');

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();

$db = (new Database())->getConnection();
$session = SecurityMiddleware::verifyToken();

if ($session['user_type'] !== 'employer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

/* =========================================================
   STEP 2 — RESOLVE ORGANIZATION
   ========================================================= */

$stmt = $db->prepare(
    "SELECT organization_id FROM employer_users WHERE id = :id"
);
$stmt->execute([':id' => $session['user_id']]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$org) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization not found']);
    exit();
}

$organization_id = (int) $org['organization_id'];

/* =========================================================
   STEP 3 — ACCEPT CSV FILE
   ========================================================= */

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSV file required']);
    exit();
}

$filePath = $_FILES['file']['tmp_name'];

/* =========================================================
   STEP 4 — PARSE CSV (BOM + EXCEL SAFE)
   ========================================================= */

$rows = [];
$headers = null;

if (($handle = fopen($filePath, 'r')) !== false) {
    while (($data = fgetcsv($handle, 2000, ',')) !== false) {

        if (!$headers) {
            // Normalize headers: trim, lowercase, remove BOM
            $headers = array_map(function ($h) {
                $h = preg_replace('/\xEF\xBB\xBF/', '', $h);
                return strtolower(trim($h));
            }, $data);
            continue;
        }

        if (count(array_filter($data)) === 0) continue;

        $rows[] = array_combine(
            $headers,
            array_map('trim', $data)
        );
    }
    fclose($handle);
}

if (empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'CSV has no data']);
    exit();
}

/* =========================================================
   STEP 5 — CSV HEADER CONTRACT ENFORCEMENT
   ========================================================= */

$requiredHeaders = [
    'employee_no',
    'first_name',
    'last_name',
    'work_email',
    'phone',
    'gender',
    'date_of_birth',
    'hire_date',
    'department_id',
    'position_id',
    'basic_salary'
];

$missingHeaders = array_diff($requiredHeaders, $headers);

if (!empty($missingHeaders)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSV headers',
        'missing_headers' => array_values($missingHeaders)
    ]);
    exit();
}

/* =========================================================
   STEP 6 — MODE DETECTION
   ========================================================= */

$mode = $_POST['mode'] ?? 'preview';

/* =========================================================
   STEP 7 — ROW VALIDATION FUNCTION
   ========================================================= */

function validateEmployeeRow(
    array $row,
    int $orgId,
    PDO $db,
    array $csvEmployeeNos
): array {
    $errors = [];

    foreach (['employee_no','first_name','last_name','work_email'] as $field) {
        if (empty($row[$field])) {
            $errors[] = "$field missing";
        }
    }

    if (!empty($row['work_email']) &&
        !filter_var($row['work_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'invalid email';
    }

    if (!in_array($row['gender'], ['Male','Female','Other'], true)) {
        $errors[] = 'invalid gender';
    }

    if (!is_numeric($row['basic_salary']) || $row['basic_salary'] < 0) {
        $errors[] = 'invalid salary';
    }

    foreach (['date_of_birth','hire_date'] as $dateField) {
        if (!DateTime::createFromFormat('m-d-Y', $row[$dateField])) {
            $errors[] = "invalid $dateField format";
        }
    }

    if (count(array_keys($csvEmployeeNos, $row['employee_no'])) > 1) {
        $errors[] = 'duplicate employee_no in CSV';
    }

    $stmt = $db->prepare("
        SELECT id FROM employees
        WHERE employee_no = :emp
          AND organization_id = :org
        LIMIT 1
    ");
    $stmt->execute([
        ':emp' => $row['employee_no'],
        ':org' => $orgId
    ]);

    if ($stmt->fetch()) {
        $errors[] = 'employee_no already exists';
    }

    return $errors;
}

/* =========================================================
   STEP 8 — PREVIEW MODE (UNCHANGED)
   ========================================================= */

if ($mode === 'preview') {
    echo json_encode([
        'success' => true,
        'preview' => array_slice($rows, 0, 10),
        'total_rows' => count($rows)
    ]);
    exit();
}

/* =========================================================
   STEP 9 — UPLOAD MODE (ATOMIC & SAFE)
   ========================================================= */

$csvEmployeeNos = array_column($rows, 'employee_no');
$failed = [];

/* --- Pre-validate ALL rows before DB write --- */
foreach ($rows as $index => $row) {
    $errors = validateEmployeeRow($row, $organization_id, $db, $csvEmployeeNos);

    if (!empty($errors)) {
        $failed[] = [
            'row' => $index + 2,
            'errors' => $errors
        ];
    }
}

if (!empty($failed)) {
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'failed_rows' => $failed
    ]);
    exit();
}

/* --- Transaction-safe insert --- */
try {
    $db->beginTransaction();

    $success = [];

    $stmt = $db->prepare("
        INSERT INTO employees (
            organization_id,
            employee_no,
            first_name,
            last_name,
            work_email,
            phone,
            gender,
            date_of_birth,
            hire_date,
            department_id,
            position_id,
            basic_salary
        ) VALUES (
            :org,:emp,:fn,:ln,:email,:phone,:gender,
            STR_TO_DATE(:dob,'%m-%d-%Y'),
            STR_TO_DATE(:hire,'%m-%d-%Y'),
            :dept,:pos,:salary
        )
    ");

    foreach ($rows as $row) {
        $stmt->execute([
            ':org'    => $organization_id,
            ':emp'    => $row['employee_no'],
            ':fn'     => $row['first_name'],
            ':ln'     => $row['last_name'],
            ':email'  => $row['work_email'],
            ':phone'  => $row['phone'],
            ':gender' => $row['gender'],
            ':dob'    => $row['date_of_birth'],
            ':hire'   => $row['hire_date'],
            ':dept'   => $row['department_id'],
            ':pos'    => $row['position_id'],
            ':salary' => $row['basic_salary']
        ]);

        $success[] = $row['employee_no'];
    }

    $db->commit();

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bulk upload failed',
        'error' => $e->getMessage()
    ]);
    exit();
}

/* =========================================================
   STEP 10 — FINAL RESPONSE
   ========================================================= */

echo json_encode([
    'success' => true,
    'summary' => [
        'total'    => count($rows),
        'inserted' => count($success),
        'failed'   => 0
    ],
    'failed_rows' => []
]);
