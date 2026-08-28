<?php
/**
 * Loads organization-scoped payroll tax settings from database.
 * Falls back to payroll_config.php constants when DB rows are missing.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';

class TaxSettingsService
{
    private static array $cache = [];

    public static function getOrganizationId(?int $orgId = null): int
    {
        if ($orgId !== null && $orgId > 0) {
            return $orgId;
        }
        return 1;
    }

    public static function load(int $organizationId): array
    {
        if (isset(self::$cache[$organizationId])) {
            return self::$cache[$organizationId];
        }

        $defaults = self::defaultConfig();
        $config = $defaults;

        try {
            $db = (new Database())->getConnection();

            $stmt = $db->prepare("
                SELECT setting_key, setting_value, setting_type
                FROM payroll_tax_settings
                WHERE organization_id = :org_id
            ");
            $stmt->execute([':org_id' => $organizationId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $config['settings'][$row['setting_key']] = self::castValue(
                    $row['setting_value'],
                    $row['setting_type']
                );
            }

            $bandStmt = $db->prepare("
                SELECT band_order, min_amount, max_amount, rate
                FROM paye_tax_bands
                WHERE organization_id = :org_id AND is_active = 1
                ORDER BY band_order ASC
            ");
            $bandStmt->execute([':org_id' => $organizationId]);
            $bands = $bandStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($bands)) {
                $config['paye_bands'] = array_map(static function ($b) {
                    return [
                        'min' => (float)$b['min_amount'],
                        'max' => $b['max_amount'] !== null ? (float)$b['max_amount'] : null,
                        'rate' => (float)$b['rate'],
                    ];
                }, $bands);
            }

            $shifStmt = $db->prepare("
                SELECT bracket_order, gross_up_to, fixed_amount, rate, calc_type
                FROM shif_brackets
                WHERE organization_id = :org_id AND is_active = 1
                ORDER BY bracket_order ASC
            ");
            $shifStmt->execute([':org_id' => $organizationId]);
            $brackets = $shifStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($brackets)) {
                $config['shif_brackets'] = $brackets;
            }
        } catch (Exception $e) {
            error_log('[TaxSettingsService] ' . $e->getMessage());
        }

        self::$cache[$organizationId] = $config;
        return $config;
    }

    public static function clearCache(?int $organizationId = null): void
    {
        if ($organizationId === null) {
            self::$cache = [];
            return;
        }
        unset(self::$cache[$organizationId]);
    }

    public static function getSetting(int $organizationId, string $key, $fallback = null)
    {
        $config = self::load($organizationId);
        return $config['settings'][$key] ?? $fallback;
    }

    public static function saveSettings(int $organizationId, array $settings, ?int $updatedBy = null): void
    {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("
            INSERT INTO payroll_tax_settings
                (organization_id, setting_key, setting_value, setting_type, category, label, updated_by)
            VALUES
                (:org_id, :key, :value, :type, :category, :label, :updated_by)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");

        foreach ($settings as $item) {
            $stmt->execute([
                ':org_id' => $organizationId,
                ':key' => $item['key'],
                ':value' => (string)$item['value'],
                ':type' => $item['type'] ?? 'string',
                ':category' => $item['category'] ?? 'general',
                ':label' => $item['label'] ?? $item['key'],
                ':updated_by' => $updatedBy,
            ]);
        }

        self::clearCache($organizationId);
    }

    public static function savePayeBands(int $organizationId, array $bands): void
    {
        $db = (new Database())->getConnection();
        $db->beginTransaction();
        try {
            $del = $db->prepare("DELETE FROM paye_tax_bands WHERE organization_id = :org_id");
            $del->execute([':org_id' => $organizationId]);

            $ins = $db->prepare("
                INSERT INTO paye_tax_bands (organization_id, band_order, min_amount, max_amount, rate)
                VALUES (:org_id, :order, :min, :max, :rate)
            ");
            foreach ($bands as $i => $band) {
                $ins->execute([
                    ':org_id' => $organizationId,
                    ':order' => $i + 1,
                    ':min' => $band['min'],
                    ':max' => $band['max'] ?? null,
                    ':rate' => $band['rate'],
                ]);
            }
            $db->commit();
            self::clearCache($organizationId);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function saveShifBrackets(int $organizationId, array $brackets): void
    {
        $db = (new Database())->getConnection();
        $db->beginTransaction();
        try {
            $del = $db->prepare("DELETE FROM shif_brackets WHERE organization_id = :org_id");
            $del->execute([':org_id' => $organizationId]);

            $ins = $db->prepare("
                INSERT INTO shif_brackets
                    (organization_id, bracket_order, gross_up_to, fixed_amount, rate, calc_type)
                VALUES (:org_id, :order, :gross_up_to, :fixed_amount, :rate, :calc_type)
            ");
            foreach ($brackets as $i => $br) {
                $ins->execute([
                    ':org_id' => $organizationId,
                    ':order' => $i + 1,
                    ':gross_up_to' => $br['gross_up_to'] ?? null,
                    ':fixed_amount' => $br['fixed_amount'] ?? null,
                    ':rate' => $br['rate'] ?? null,
                    ':calc_type' => $br['calc_type'] ?? 'fixed',
                ]);
            }
            $db->commit();
            self::clearCache($organizationId);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function logAudit(int $organizationId, string $changeType, $previous, $new, ?int $userId): void
    {
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("
                INSERT INTO tax_settings_audit
                    (organization_id, changed_by, change_type, previous_value, new_value, ip_address)
                VALUES (:org_id, :user_id, :type, :prev, :new, :ip)
            ");
            $stmt->execute([
                ':org_id' => $organizationId,
                ':user_id' => $userId,
                ':type' => $changeType,
                ':prev' => json_encode($previous),
                ':new' => json_encode($new),
                ':ip' => SecurityMiddleware::getClientIP(),
            ]);
        } catch (Exception $e) {
            error_log('[TaxSettingsService::logAudit] ' . $e->getMessage());
        }
    }

    private static function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'decimal': return (float)$value;
            case 'integer': return (int)$value;
            case 'boolean': return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json': return json_decode($value, true);
            default: return $value;
        }
    }

    private static function defaultConfig(): array
    {
        if (file_exists(__DIR__ . '/../config/payroll_config.php')) {
            require_once __DIR__ . '/../config/payroll_config.php';
        }

        return [
            'settings' => [
                'personal_relief' => defined('PERSONAL_RELIEF') ? PERSONAL_RELIEF : 2400,
                'nssf_employee_rate' => defined('NSSF_RATE') ? NSSF_RATE : 0.06,
                'nssf_employer_rate' => defined('NSSF_EMPLOYER_RATE') ? NSSF_EMPLOYER_RATE : 0.06,
                'nssf_tier1_cap' => 7000,
                'nssf_tier2_cap' => defined('NSSF_UPPER_LIMIT') ? NSSF_UPPER_LIMIT : 36000,
                'housing_levy_rate' => defined('HOUSING_LEVY_RATE') ? HOUSING_LEVY_RATE : 0.015,
                'shif_mode' => 'brackets',
                'shif_flat_rate' => defined('SHIF_RATE') ? SHIF_RATE : 0.0275,
                'overtime_rate' => defined('OVERTIME_RATE') ? OVERTIME_RATE : 1.5,
                'working_hours_per_month' => defined('WORKING_HOURS_PER_MONTH') ? WORKING_HOURS_PER_MONTH : 160,
                'working_days_per_month' => defined('WORKING_DAYS_PER_MONTH') ? WORKING_DAYS_PER_MONTH : 22,
            ],
            'paye_bands' => defined('PAYE_BANDS') ? array_map(static function ($b) {
                return [
                    'min' => (float)$b['min'],
                    'max' => ($b['max'] ?? null) !== null && $b['max'] < PHP_INT_MAX ? (float)$b['max'] : null,
                    'rate' => (float)$b['rate'],
                ];
            }, PAYE_BANDS) : [
                ['min' => 0, 'max' => 24000, 'rate' => 0.10],
                ['min' => 24000.01, 'max' => 32333, 'rate' => 0.25],
                ['min' => 32333.01, 'max' => 500000, 'rate' => 0.30],
                ['min' => 500000.01, 'max' => 800000, 'rate' => 0.325],
                ['min' => 800000.01, 'max' => null, 'rate' => 0.35],
            ],
            'shif_brackets' => [],
        ];
    }
}
