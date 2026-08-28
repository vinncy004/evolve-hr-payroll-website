<?php
require_once __DIR__ . '/../config/payroll_config.php';

/**
 * Payroll Report Generator
 * Generates various payroll reports
 * backend/utils/PayrollReportGenerator.php
 */
class PayrollReportGenerator {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Generate Monthly Payroll Summary Report
     */
    public function generateMonthlySummary($month, $year) {
        $query = "SELECT
                    COUNT(*) as total_employees,
                    SUM(basic_salary) as total_basic_salary,
                    SUM(housing_allowance) as total_housing_allowance,
                    SUM(transport_allowance) as total_transport_allowance,
                    SUM(medical_allowance) as total_medical_allowance,
                    SUM(overtime_pay) as total_overtime,
                    SUM(gross_pay) as total_gross_pay,
                    SUM(paye) as total_paye,
                    SUM(nssf_employee) as total_nssf,
                    SUM(shif) as total_shif,
                    SUM(housing_levy) as total_housing_levy,
                    SUM(total_deductions) as total_deductions,
                    SUM(net_pay) as total_net_pay
                  FROM payroll
                  WHERE period_month = ? AND period_year = ?";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$month, $year]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->generateSummaryHTML($summary, $month, $year);
    }

    /**
     * Generate HTML for monthly summary
     */
    private function generateSummaryHTML($data, $month, $year) {
        $month_name = date('F', mktime(0, 0, 0, $month, 1));

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Summary - ' . $month_name . ' ' . $year . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #1a365d, #2d3748);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .company-name { font-size: 32px; font-weight: bold; margin-bottom: 10px; }
        .report-title { font-size: 20px; opacity: 0.9; }
        .content { padding: 30px; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-label {
            color: #718096;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #1a365d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #1a365d;
            color: white;
            padding: 15px;
            text-align: left;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .amount { text-align: right; font-weight: 500; }
        .total-row {
            background: #f7fafc;
            font-weight: bold;
            font-size: 16px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a365d;
            margin: 30px 0 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #d4af37;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="company-name">' . COMPANY_NAME . '</div>
            <div class="report-title">MONTHLY PAYROLL SUMMARY - ' . strtoupper($month_name . ' ' . $year) . '</div>
        </div>

        <div class="content">
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Total Employees</div>
                    <div class="summary-value">' . number_format($data['total_employees']) . '</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Total Gross Pay</div>
                    <div class="summary-value">KES ' . number_format($data['total_gross_pay'], 2) . '</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Total Net Pay</div>
                    <div class="summary-value">KES ' . number_format($data['total_net_pay'], 2) . '</div>
                </div>
            </div>

            <div class="section-title">Earnings Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="amount">Amount (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="amount">' . number_format($data['total_basic_salary'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Housing Allowance</td>
                        <td class="amount">' . number_format($data['total_housing_allowance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Transport Allowance</td>
                        <td class="amount">' . number_format($data['total_transport_allowance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Medical Allowance</td>
                        <td class="amount">' . number_format($data['total_medical_allowance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Overtime Pay</td>
                        <td class="amount">' . number_format($data['total_overtime'], 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Gross Pay</td>
                        <td class="amount">' . number_format($data['total_gross_pay'], 2) . '</td>
                    </tr>
                </tbody>
            </table>

            <div class="section-title">Deductions Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="amount">Amount (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PAYE (Income Tax)</td>
                        <td class="amount">' . number_format($data['total_paye'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>NSSF Employee Contribution</td>
                        <td class="amount">' . number_format($data['total_nssf'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>SHIF (Social Health Insurance)</td>
                        <td class="amount">' . number_format($data['total_shif'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Housing Levy</td>
                        <td class="amount">' . number_format($data['total_housing_levy'], 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="amount">' . number_format($data['total_deductions'], 2) . '</td>
                    </tr>
                    <tr class="total-row" style="background: #1a365d; color: white;">
                        <td>NET PAYROLL</td>
                        <td class="amount">' . number_format($data['total_net_pay'], 2) . '</td>
                    </tr>
                </tbody>
            </table>

            <p style="margin-top: 30px; color: #718096; font-size: 12px;">
                Generated on: ' . date('d/m/Y H:i:s') . '<br>
                Report prepared by: ' . COMPANY_NAME . ' Payroll System
            </p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate Detailed Payroll Report (All Employees)
     */
    public function generateDetailedReport($month, $year) {
        $query = "SELECT p.*,
                  CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                  e.employee_number,
                  e.department,
                  e.position
                  FROM payroll p
                  LEFT JOIN employees e ON p.employee_id = e.id
                  WHERE p.period_month = ? AND p.period_year = ?
                  ORDER BY e.department, e.employee_number";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$month, $year]);
        $payroll_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->generateDetailedHTML($payroll_data, $month, $year);
    }

    /**
     * Generate HTML for detailed report
     */
    private function generateDetailedHTML($data, $month, $year) {
        $month_name = date('F', mktime(0, 0, 0, $month, 1));

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Payroll Report - ' . $month_name . ' ' . $year . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #1a365d, #2d3748);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .company-name { font-size: 32px; font-weight: bold; margin-bottom: 10px; }
        .report-title { font-size: 20px; opacity: 0.9; }
        .content { padding: 30px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }
        th {
            background: #1a365d;
            color: white;
            padding: 10px 8px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .amount { text-align: right; }
        tr:hover { background: #f7fafc; }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="company-name">' . COMPANY_NAME . '</div>
            <div class="report-title">DETAILED PAYROLL REPORT - ' . strtoupper($month_name . ' ' . $year) . '</div>
        </div>

        <div class="content">
            <table>
                <thead>
                    <tr>
                        <th>Emp #</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th class="amount">Basic</th>
                        <th class="amount">Allowances</th>
                        <th class="amount">Overtime</th>
                        <th class="amount">Gross Pay</th>
                        <th class="amount">PAYE</th>
                        <th class="amount">NSSF</th>
                        <th class="amount">SHIF</th>
                        <th class="amount">Deductions</th>
                        <th class="amount">Net Pay</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($data as $row) {
            $allowances = $row['housing_allowance'] + $row['transport_allowance'] + $row['medical_allowance'];
            $html .= '<tr>
                        <td>' . htmlspecialchars($row['employee_number']) . '</td>
                        <td>' . htmlspecialchars($row['employee_name']) . '</td>
                        <td>' . htmlspecialchars($row['department']) . '</td>
                        <td class="amount">' . number_format($row['basic_salary'], 2) . '</td>
                        <td class="amount">' . number_format($allowances, 2) . '</td>
                        <td class="amount">' . number_format($row['overtime_pay'], 2) . '</td>
                        <td class="amount">' . number_format($row['gross_pay'], 2) . '</td>
                        <td class="amount">' . number_format($row['paye'], 2) . '</td>
                        <td class="amount">' . number_format($row['nssf_employee'], 2) . '</td>
                        <td class="amount">' . number_format($row['shif'], 2) . '</td>
                        <td class="amount">' . number_format($row['total_deductions'], 2) . '</td>
                        <td class="amount"><strong>' . number_format($row['net_pay'], 2) . '</strong></td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate Tax Summary Report (For KRA Submission)
     */
    public function generateTaxReport($month, $year) {
        $query = "SELECT
                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                    e.employee_number,
                    e.kra_pin,
                    p.gross_pay,
                    p.nssf_employee,
                    p.housing_levy,
                    p.paye,
                    p.personal_relief,
                    p.net_pay
                  FROM payroll p
                  LEFT JOIN employees e ON p.employee_id = e.id
                  WHERE p.period_month = ? AND p.period_year = ?
                  ORDER BY e.employee_number";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$month, $year]);
        $tax_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->generateTaxHTML($tax_data, $month, $year);
    }

    /**
     * Generate HTML for tax report
     */
    private function generateTaxHTML($data, $month, $year) {
        $month_name = date('F', mktime(0, 0, 0, $month, 1));

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Report - ' . $month_name . ' ' . $year . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; }
        .report-container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #1a365d; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #1a365d; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .amount { text-align: right; }
    </style>
</head>
<body>
    <div class="report-container">
        <h1>PAYE Tax Report - ' . $month_name . ' ' . $year . '</h1>
        <p><strong>Company:</strong> ' . COMPANY_NAME . '</p>
        <p><strong>PIN:</strong> ' . COMPANY_PIN . '</p>

        <table>
            <thead>
                <tr>
                    <th>Employee Number</th>
                    <th>Employee Name</th>
                    <th>KRA PIN</th>
                    <th class="amount">Gross Pay</th>
                    <th class="amount">PAYE</th>
                    <th class="amount">Relief</th>
                    <th class="amount">Net Tax</th>
                </tr>
            </thead>
            <tbody>';

        $total_gross = 0;
        $total_paye = 0;
        $total_relief = 0;

        foreach ($data as $row) {
            $net_tax = $row['paye'] - $row['personal_relief'];
            $total_gross += $row['gross_pay'];
            $total_paye += $row['paye'];
            $total_relief += $row['personal_relief'];

            $html .= '<tr>
                        <td>' . htmlspecialchars($row['employee_number']) . '</td>
                        <td>' . htmlspecialchars($row['employee_name']) . '</td>
                        <td>' . htmlspecialchars($row['kra_pin'] ?? 'N/A') . '</td>
                        <td class="amount">' . number_format($row['gross_pay'], 2) . '</td>
                        <td class="amount">' . number_format($row['paye'], 2) . '</td>
                        <td class="amount">' . number_format($row['personal_relief'], 2) . '</td>
                        <td class="amount">' . number_format($net_tax, 2) . '</td>
                    </tr>';
        }

        $total_net_tax = $total_paye - $total_relief;

        $html .= '
                    <tr style="background: #f0f0f0; font-weight: bold;">
                        <td colspan="3">TOTAL</td>
                        <td class="amount">' . number_format($total_gross, 2) . '</td>
                        <td class="amount">' . number_format($total_paye, 2) . '</td>
                        <td class="amount">' . number_format($total_relief, 2) . '</td>
                        <td class="amount">' . number_format($total_net_tax, 2) . '</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </body>
    </html>';

        return $html;
    }

    /**
     * Save report to file
     */
    public function saveReport($html, $filename) {
        $output_path = REPORTS_OUTPUT_PATH . $filename;

        // Create directory if it doesn't exist
        if (!file_exists(REPORTS_OUTPUT_PATH)) {
            mkdir(REPORTS_OUTPUT_PATH, 0777, true);
        }

        file_put_contents($output_path, $html);
        return $output_path;
    }
}
?>
