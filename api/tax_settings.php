<?php
/**
 * Payroll tax settings API — HR/Admin only
 * GET  ?action=get
 * PUT  ?action=update  (settings, paye_bands, shif_brackets)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../middleware/RbacMiddleware.php';
require_once __DIR__ . '/../utils/TaxSettingsService.php';

SecurityMiddleware::handleCORS();
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::checkRateLimit('tax_settings', 60, 60);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = (new Database())->getConnection();
$session = RbacMiddleware::requireHrAdmin();
$orgId = RbacMiddleware::organizationId($session);

if ($orgId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization context required']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';

try {
    if ($method === 'GET' && $action === 'get') {
        $config = TaxSettingsService::load($orgId);
        echo json_encode(['success' => true, 'data' => $config]);
        exit();
    }

    if ($method === 'PUT' && $action === 'update') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $previous = TaxSettingsService::load($orgId);

        if (!empty($payload['settings']) && is_array($payload['settings'])) {
            $rows = [];
            foreach ($payload['settings'] as $key => $item) {
                if (is_array($item)) {
                    $rows[] = [
                        'key' => $key,
                        'value' => $item['value'],
                        'type' => $item['type'] ?? 'string',
                        'category' => $item['category'] ?? 'general',
                        'label' => $item['label'] ?? $key,
                    ];
                } else {
                    $rows[] = ['key' => $key, 'value' => $item, 'type' => 'string', 'category' => 'general', 'label' => $key];
                }
            }
            TaxSettingsService::saveSettings($orgId, $rows, (int)$session['user_id']);
        }

        if (!empty($payload['paye_bands']) && is_array($payload['paye_bands'])) {
            TaxSettingsService::savePayeBands($orgId, $payload['paye_bands']);
        }

        if (!empty($payload['shif_brackets']) && is_array($payload['shif_brackets'])) {
            TaxSettingsService::saveShifBrackets($orgId, $payload['shif_brackets']);
        }

        TaxSettingsService::logAudit($orgId, 'tax_settings_update', $previous, $payload, (int)$session['user_id']);
        echo json_encode(['success' => true, 'message' => 'Tax settings updated', 'data' => TaxSettingsService::load($orgId)]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method or action not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
