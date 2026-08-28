<?php
// backend/utils/CalculationService.php

require_once __DIR__ . '/TaxSettingsService.php';

class CalculationService
{
    public static function calculate($basic, $allowances = [], $benefits = [], $organizationId = null)
    {
        $taxable_allowances = 0;
        $taxable_benefits = 0;

        foreach ($allowances as $al) {
            if (($al['taxable'] ?? 1) == 1) {
                $taxable_allowances += ($al['amount'] ?? 0);
            }
        }

        foreach ($benefits as $b) {
            if (($b['taxable'] ?? 0) == 1) {
                $taxable_benefits += ($b['amount'] ?? 0);
            }
        }

        $gross = $basic + $taxable_allowances + $taxable_benefits;

        return self::calculateFromGross($gross, [
            'basic' => $basic,
            'allowances_taxable' => $taxable_allowances,
            'benefits_taxable' => $taxable_benefits,
            'raw_allowances' => $allowances,
            'raw_benefits' => $benefits,
        ], $organizationId);
    }

    public static function calculateFromGross($gross, $meta = [], $organizationId = null)
    {
        $orgId = TaxSettingsService::getOrganizationId($organizationId);
        $config = TaxSettingsService::load($orgId);
        $settings = $config['settings'];

        $tier1Cap = (float)($settings['nssf_tier1_cap'] ?? 7000);
        $tier2Cap = (float)($settings['nssf_tier2_cap'] ?? 36000);
        $nssfRate = (float)($settings['nssf_employee_rate'] ?? 0.06);
        $nssfEmployerRate = (float)($settings['nssf_employer_rate'] ?? 0.06);
        $personalRelief = (float)($settings['personal_relief'] ?? 2400);
        $housingRate = (float)($settings['housing_levy_rate'] ?? 0.015);

        $tier1Base = min($gross, $tier1Cap);
        $tier2Base = min(max($gross - $tier1Cap, 0), $tier2Cap - $tier1Cap);

        $nssfEmployee = ($tier1Base * $nssfRate) + ($tier2Base * $nssfRate);
        $nssfEmployer = ($tier1Base * $nssfEmployerRate) + ($tier2Base * $nssfEmployerRate);

        $taxableIncome = $gross - $nssfEmployee;
        $paye = self::computePAYE($taxableIncome, $config['paye_bands']);
        $paye = max(0, $paye - $personalRelief);

        $shif = self::computeSHIF($gross, $config, $settings);
        $housingLevy = round($gross * $housingRate, 2);

        $totalDeductions = $paye + $nssfEmployee + $shif + $housingLevy;
        $net = $gross - $totalDeductions;

        return [
            'success' => true,
            'gross_pay' => round($gross, 2),
            'taxable_income' => round($taxableIncome, 2),
            'nssf_employee' => round($nssfEmployee, 2),
            'nssf_employer' => round($nssfEmployer, 2),
            'paye' => round($paye, 2),
            'shif' => round($shif, 2),
            'housing_levy' => round($housingLevy, 2),
            'personal_relief' => $personalRelief,
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($net, 2),
            'organization_id' => $orgId,
            '_meta' => $meta,
        ];
    }

    private static function computePAYE($taxable, array $bands): float
    {
        if (empty($bands)) {
            return 0;
        }

        usort($bands, static function ($a, $b) {
            return ($a['min'] ?? 0) <=> ($b['min'] ?? 0);
        });

        $tax = 0;
        $remaining = $taxable;
        $prevMax = 0;

        foreach ($bands as $band) {
            $rate = (float)($band['rate'] ?? 0);
            $max = $band['max'] ?? null;

            if ($max === null) {
                if ($remaining > 0) {
                    $tax += $remaining * $rate;
                }
                break;
            }

            $bandWidth = max(0, (float)$max - $prevMax);
            $taxableInBand = min($remaining, $bandWidth);
            $tax += $taxableInBand * $rate;
            $remaining -= $taxableInBand;
            $prevMax = (float)$max;

            if ($remaining <= 0) {
                break;
            }
        }

        return $tax;
    }

    private static function computeSHIF($gross, array $config, array $settings): float
    {
        $mode = $settings['shif_mode'] ?? 'brackets';

        if ($mode === 'percentage') {
            $rate = (float)($settings['shif_flat_rate'] ?? 0.0275);
            return round($gross * $rate, 2);
        }

        $brackets = $config['shif_brackets'] ?? [];
        if (empty($brackets)) {
            $rate = (float)($settings['shif_flat_rate'] ?? 0.0275);
            return round($gross * $rate, 2);
        }

        usort($brackets, static function ($a, $b) {
            $aLimit = $a['gross_up_to'] ?? PHP_FLOAT_MAX;
            $bLimit = $b['gross_up_to'] ?? PHP_FLOAT_MAX;
            return $aLimit <=> $bLimit;
        });

        foreach ($brackets as $br) {
            $calcType = $br['calc_type'] ?? 'fixed';
            $limit = $br['gross_up_to'];

            if ($limit === null) {
                if ($calcType === 'percentage') {
                    return round($gross * (float)($br['rate'] ?? 0), 2);
                }
                return round((float)($br['fixed_amount'] ?? 0), 2);
            }

            if ($gross <= (float)$limit) {
                if ($calcType === 'percentage') {
                    return round($gross * (float)($br['rate'] ?? 0), 2);
                }
                return round((float)($br['fixed_amount'] ?? 0), 2);
            }
        }

        return 0;
    }
}
