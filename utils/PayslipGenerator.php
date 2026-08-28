<?php
require_once __DIR__ . '/../config/payroll_config.php';

/**
 * Payslip Generator
 * Generates payslip documents in HTML and PDF format
 * backend/utils/PayslipGenerator.php
 */
class PayslipGenerator {
    private $payslip_data;

    public function __construct($payslip_data) {
        $this->payslip_data = $payslip_data;
    }

    /**
     * Generate HTML payslip
     */
    public function generateHTML() {
        $data = $this->payslip_data;
        $month_name = date('F', mktime(0, 0, 0, $data['period_month'], 1));

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - ' . htmlspecialchars($data['employee_name']) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .payslip-container {
            max-width: 800px;
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

        .company-name {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .payslip-title {
            font-size: 18px;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
        }

        .info-label {
            color: #718096;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .amount {
            text-align: right;
            font-weight: 500;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a365d;
            margin: 20px 0 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #d4af37;
        }

        .total-row {
            background: #f7fafc;
            font-weight: bold;
            font-size: 16px;
        }

        .net-pay-box {
            background: linear-gradient(135deg, #1a365d, #3182ce);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 30px;
        }

        .net-pay-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .net-pay-amount {
            font-size: 36px;
            font-weight: bold;
        }

        .footer {
            background: #f7fafc;
            padding: 20px 30px;
            text-align: center;
            color: #718096;
            font-size: 12px;
            border-top: 1px solid #e2e8f0;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .payslip-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="header">
            <div class="company-name">' . COMPANY_NAME . '</div>
            <div class="payslip-title">PAYSLIP FOR ' . strtoupper($month_name . ' ' . $data['period_year']) . '</div>
        </div>

        <div class="content">
            <div class="info-section">
                <div class="info-box">
                    <div class="info-label">Employee Name</div>
                    <div class="info-value">' . htmlspecialchars($data['employee_name']) . '</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Employee Number</div>
                    <div class="info-value">' . htmlspecialchars($data['employee_number']) . '</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Department</div>
                    <div class="info-value">' . htmlspecialchars($data['department']) . '</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Position</div>
                    <div class="info-value">' . htmlspecialchars($data['position']) . '</div>
                </div>
            </div>

            <div class="section-title">Earnings</div>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="amount">' . number_format($data['basic_salary'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Housing Allowance</td>
                        <td class="amount">' . number_format($data['housing_allowance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Transport Allowance</td>
                        <td class="amount">' . number_format($data['transport_allowance'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Medical Allowance</td>
                        <td class="amount">' . number_format($data['medical_allowance'], 2) . '</td>
                    </tr>';

        if ($data['overtime_pay'] > 0) {
            $html .= '<tr>
                        <td>Overtime Pay (' . $data['overtime_hours'] . ' hours)</td>
                        <td class="amount">' . number_format($data['overtime_pay'], 2) . '</td>
                    </tr>';
        }

        if ($data['absence_deduction'] > 0) {
            $html .= '<tr>
                        <td>Absence Deduction (' . $data['absent_days'] . ' days)</td>
                        <td class="amount">-' . number_format($data['absence_deduction'], 2) . '</td>
                    </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td>Gross Pay</td>
                        <td class="amount">' . number_format($data['gross_pay'], 2) . '</td>
                    </tr>
                </tbody>
            </table>

            <div class="section-title">Deductions</div>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount (KES)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PAYE (Income Tax)</td>
                        <td class="amount">' . number_format($data['paye'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>NSSF (6%)</td>
                        <td class="amount">' . number_format($data['nssf_employee'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>SHIF (2.75%)</td>
                        <td class="amount">' . number_format($data['shif'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Housing Levy (1.5%)</td>
                        <td class="amount">' . number_format($data['housing_levy'], 2) . '</td>
                    </tr>
                    <tr>
                        <td>Personal Relief</td>
                        <td class="amount">-' . number_format($data['personal_relief'], 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="amount">' . number_format($data['total_deductions'], 2) . '</td>
                    </tr>
                </tbody>
            </table>

            <div class="net-pay-box">
                <div class="net-pay-label">NET PAY</div>
                <div class="net-pay-amount">KES ' . number_format($data['net_pay'], 2) . '</div>
            </div>
        </div>

        <div class="footer">
            <p><strong>' . COMPANY_NAME . '</strong></p>
            <p>' . COMPANY_ADDRESS . ' | ' . COMPANY_EMAIL . ' | ' . COMPANY_PHONE . '</p>
            <p>PIN: ' . COMPANY_PIN . '</p>
            <p style="margin-top: 10px;">This is a computer-generated payslip and does not require a signature.</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Save HTML payslip to file
     */
    public function saveHTML($filename = null) {
        if (!$filename) {
            $filename = 'payslip_' . $this->payslip_data['employee_number'] . '_' .
                       $this->payslip_data['period_year'] . '_' .
                       str_pad($this->payslip_data['period_month'], 2, '0', STR_PAD_LEFT) . '.html';
        }

        $output_path = REPORTS_OUTPUT_PATH . $filename;

        // Create directory if it doesn't exist
        if (!file_exists(REPORTS_OUTPUT_PATH)) {
            mkdir(REPORTS_OUTPUT_PATH, 0777, true);
        }

        file_put_contents($output_path, $this->generateHTML());

        return $output_path;
    }

    /**
     * Generate PDF without external dependencies.
     */
    public function generatePDF() {
        $data = $this->payslip_data;
        $lines = [
            COMPANY_NAME . ' Payslip',
            'Employee: ' . ($data['employee_name'] ?? 'N/A'),
            'Employee No: ' . ($data['employee_number'] ?? $data['employee_no'] ?? 'N/A'),
            'Period: ' . date('F', mktime(0, 0, 0, (int)($data['period_month'] ?? 1), 1)) . ' ' . ($data['period_year'] ?? date('Y')),
            'Gross Pay: KES ' . number_format((float)($data['gross_pay'] ?? 0), 2),
            'Basic Salary: KES ' . number_format((float)($data['basic_salary'] ?? 0), 2),
            'Allowances: KES ' . number_format((float)($data['total_allowances'] ?? ($data['housing_allowance'] ?? 0) + ($data['transport_allowance'] ?? 0) + ($data['medical_allowance'] ?? 0)), 2),
            'PAYE: KES ' . number_format((float)($data['paye'] ?? 0), 2),
            'NSSF: KES ' . number_format((float)($data['nssf_employee'] ?? 0), 2),
            'SHIF: KES ' . number_format((float)($data['shif'] ?? 0), 2),
            'Housing Levy: KES ' . number_format((float)($data['housing_levy'] ?? 0), 2),
            'Personal Relief: KES ' . number_format((float)($data['personal_relief'] ?? 0), 2),
            'Total Deductions: KES ' . number_format((float)($data['total_deductions'] ?? 0), 2),
            'Net Pay: KES ' . number_format((float)($data['net_pay'] ?? 0), 2)
        ];

        return SimplePdfGenerator::fromLines($lines, 'Payslip');
    }

    /**
     * Send payslip via email
     */
    public function sendEmail($to_email = null) {
        if (!$to_email && isset($this->payslip_data['email'])) {
            $to_email = $this->payslip_data['email'];
        }

        if (!$to_email) {
            return ['success' => false, 'message' => 'No email address provided'];
        }

        $subject = 'Payslip for ' . date('F Y', mktime(0, 0, 0, $this->payslip_data['period_month'], 1));
        $message = $this->generateHTML();

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . COMPANY_EMAIL . "\r\n";

        $sent = mail($to_email, $subject, $message, $headers);

        return [
            'success' => $sent,
            'message' => $sent ? 'Payslip sent successfully' : 'Failed to send payslip'
        ];
    }
}
?>
