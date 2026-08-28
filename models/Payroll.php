<?php

/**
 * backend/models/Payroll.php
 */

class Payroll {
    private $conn;
    private $table_name = "payroll";

    public $id;
    public $employee_id;
    public $period_month;
    public $period_year;
    public $gross_pay;
    public $net_pay;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function generatePayroll($employee_id, $month, $year) {
        // Get employee salary structure
        $salary = $this->getEmployeeSalary($employee_id);
        if(!$salary) return false;

        // Get overtime hours for the month
        $overtime = $this->getOvertimeHours($employee_id, $month, $year);

        // Calculate earnings
        $basic_salary = $salary['basic_salary'];
        $allowances = $salary['housing_allowance'] + $salary['transport_allowance'] + $salary['medical_allowance'];
        $overtime_pay = $overtime * ($basic_salary / 160) * OVERTIME_RATE; // Assuming 160 work hours/month
        $gross_pay = $basic_salary + $allowances + $overtime_pay;

        // Calculate deductions
        $paye = $this->calculatePAYE($gross_pay);
        $nssf = $this->calculateNSSF($gross_pay);
        $shif = $this->calculateSHIF($gross_pay);
        $housing_levy = $this->calculateHousingLevy($gross_pay);

        $total_deductions = $paye + $nssf + $shif + $housing_levy;
        $net_pay = $gross_pay - $total_deductions;

        // Insert payroll record
        $query = "INSERT INTO " . $this->table_name . "
                SET employee_id=:employee_id, period_month=:month, period_year=:year,
                    basic_salary=:basic_salary, housing_allowance=:housing_allowance,
                    transport_allowance=:transport_allowance, medical_allowance=:medical_allowance,
                    overtime_pay=:overtime_pay, gross_pay=:gross_pay,
                    paye=:paye, nssf_deduction=:nssf, shif_deduction=:shif,
                    housing_levy=:housing_levy, total_deductions=:total_deductions,
                    net_pay=:net_pay, status='draft'
                ON DUPLICATE KEY UPDATE
                    gross_pay=:gross_pay, net_pay=:net_pay";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":employee_id", $employee_id);
        $stmt->bindParam(":month", $month);
        $stmt->bindParam(":year", $year);
        $stmt->bindParam(":basic_salary", $basic_salary);
        $stmt->bindParam(":housing_allowance", $salary['housing_allowance']);
        $stmt->bindParam(":transport_allowance", $salary['transport_allowance']);
        $stmt->bindParam(":medical_allowance", $salary['medical_allowance']);
        $stmt->bindParam(":overtime_pay", $overtime_pay);
        $stmt->bindParam(":gross_pay", $gross_pay);
        $stmt->bindParam(":paye", $paye);
        $stmt->bindParam(":nssf", $nssf);
        $stmt->bindParam(":shif", $shif);
        $stmt->bindParam(":housing_levy", $housing_levy);
        $stmt->bindParam(":total_deductions", $total_deductions);
        $stmt->bindParam(":net_pay", $net_pay);

        return $stmt->execute();
    }

    private function getEmployeeSalary($employee_id) {
        $query = "SELECT * FROM salary_structures
                  WHERE employee_id = ? AND is_active = 1
                  ORDER BY effective_date DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getOvertimeHours($employee_id, $month, $year) {
        $query = "SELECT SUM(overtime_hours) as total_overtime
                  FROM attendance
                  WHERE employee_id = ? AND MONTH(attendance_date) = ?
                  AND YEAR(attendance_date) = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->bindParam(2, $month);
        $stmt->bindParam(3, $year);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_overtime'] ?? 0;
    }

    private function calculatePAYE($gross_pay) {
        $tax = 0;
        foreach(PAYE_BANDS as $band) {
            if($gross_pay > $band['min']) {
                $taxable = min($gross_pay, $band['max']) - $band['min'];
                $tax += $taxable * $band['rate'];
            }
        }
        return round($tax, 2);
    }

    private function calculateNSSF($gross_pay) {
        $pensionable_pay = min($gross_pay, NSSF_UPPER_LIMIT);
        return round($pensionable_pay * NSSF_RATE, 2);
    }

    private function calculateSHIF($gross_pay) {
        return round($gross_pay * SHIF_RATE, 2);
    }

    private function calculateHousingLevy($gross_pay) {
        return round($gross_pay * HOUSING_LEVY_RATE, 2);
    }

    public function getPayrollByMonth($month, $year) {
        $query = "SELECT p.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                  e.employee_number
                  FROM " . $this->table_name . " p
                  LEFT JOIN employees e ON p.employee_id = e.id
                  WHERE p.period_month = ? AND p.period_year = ?
                  ORDER BY e.employee_number";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $month);
        $stmt->bindParam(2, $year);
        $stmt->execute();
        return $stmt;
    }

    public function getEmployeePayslips($employee_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE employee_id = ?
                  ORDER BY period_year DESC, period_month DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->execute();
        return $stmt;
    }
}
?>
