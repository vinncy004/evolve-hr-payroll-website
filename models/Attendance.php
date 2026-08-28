<?php

// backend/models/Attendance.php

class Attendance {
    private $conn;
    private $table_name = "attendance";

    public $id;
    public $employee_id;
    public $attendance_date;
    public $clock_in;
    public $clock_out;
    public $work_hours;
    public $overtime_hours;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function clockIn($method, $latitude = null, $longitude = null, $location = null) {
        $query = "INSERT INTO " . $this->table_name . "
                SET employee_id=:employee_id, attendance_date=CURDATE(),
                    clock_in=NOW(), clock_in_method=:method,
                    clock_in_latitude=:latitude, clock_in_longitude=:longitude,
                    clock_in_location=:location, status='present'
                ON DUPLICATE KEY UPDATE
                    clock_in=NOW(), clock_in_method=:method";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":employee_id", $this->employee_id);
        $stmt->bindParam(":method", $method);
        $stmt->bindParam(":latitude", $latitude);
        $stmt->bindParam(":longitude", $longitude);
        $stmt->bindParam(":location", $location);

        return $stmt->execute();
    }

    public function clockOut($method, $latitude = null, $longitude = null, $location = null) {
        $query = "UPDATE " . $this->table_name . "
                SET clock_out=NOW(), clock_out_method=:method,
                    clock_out_latitude=:latitude, clock_out_longitude=:longitude,
                    clock_out_location=:location
                WHERE employee_id=:employee_id AND attendance_date=CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":employee_id", $this->employee_id);
        $stmt->bindParam(":method", $method);
        $stmt->bindParam(":latitude", $latitude);
        $stmt->bindParam(":longitude", $longitude);
        $stmt->bindParam(":location", $location);

        if($stmt->execute()) {
            $this->calculateWorkHours();
            return true;
        }
        return false;
    }

    private function calculateWorkHours() {
        $query = "UPDATE " . $this->table_name . "
                SET work_hours = TIMESTAMPDIFF(HOUR, clock_in, clock_out),
                    overtime_hours = CASE
                        WHEN TIMESTAMPDIFF(HOUR, clock_in, clock_out) > 8
                        THEN TIMESTAMPDIFF(HOUR, clock_in, clock_out) - 8
                        ELSE 0
                    END
                WHERE employee_id=:employee_id AND attendance_date=CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":employee_id", $this->employee_id);
        return $stmt->execute();
    }

    public function getEmployeeAttendance($employee_id, $month, $year) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE employee_id = ? AND MONTH(attendance_date) = ?
                  AND YEAR(attendance_date) = ?
                  ORDER BY attendance_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->bindParam(2, $month);
        $stmt->bindParam(3, $year);
        $stmt->execute();
        return $stmt;
    }

    public function getTodayAttendance() {
        $query = "SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                  e.employee_number
                  FROM " . $this->table_name . " a
                  LEFT JOIN employees e ON a.employee_id = e.id
                  WHERE a.attendance_date = CURDATE()
                  ORDER BY a.clock_in DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getAttendanceSummary($employee_id, $month, $year) {
        $query = "SELECT
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_days,
                    SUM(work_hours) as total_work_hours,
                    SUM(overtime_hours) as total_overtime_hours
                  FROM " . $this->table_name . "
                  WHERE employee_id = ? AND MONTH(attendance_date) = ?
                  AND YEAR(attendance_date) = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->bindParam(2, $month);
        $stmt->bindParam(3, $year);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
