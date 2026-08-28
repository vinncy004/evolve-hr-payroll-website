<?php
class Leave {
    private $conn;
    private $table_name = "leave_applications";

    public $id;
    public $employee_id;
    public $leave_type_id;
    public $start_date;
    public $end_date;
    public $days_requested;
    public $reason;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET employee_id=:employee_id, leave_type_id=:leave_type_id,
                    start_date=:start_date, end_date=:end_date,
                    days_requested=:days_requested, reason=:reason,
                    status='pending'";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":employee_id", $this->employee_id);
        $stmt->bindParam(":leave_type_id", $this->leave_type_id);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":days_requested", $this->days_requested);
        $stmt->bindParam(":reason", htmlspecialchars(strip_tags($this->reason)));

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function read() {
        $query = "SELECT l.*, lt.name as leave_type_name,
                  CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                  e.employee_number
                  FROM " . $this->table_name . " l
                  LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
                  LEFT JOIN employees e ON l.employee_id = e.id
                  ORDER BY l.applied_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readByEmployee($employee_id) {
        $query = "SELECT l.*, lt.name as leave_type_name
                  FROM " . $this->table_name . " l
                  LEFT JOIN leave_types lt ON l.leave_type_id = lt.id
                  WHERE l.employee_id = ?
                  ORDER BY l.applied_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->execute();
        return $stmt;
    }

    public function approve($reviewed_by, $comments) {
        $query = "UPDATE " . $this->table_name . "
                SET status='approved', reviewed_by=:reviewed_by,
                    review_date=NOW(), review_comments=:comments
                WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":reviewed_by", $reviewed_by);
        $stmt->bindParam(":comments", $comments);

        if($stmt->execute()) {
            // Update leave balance
            $this->updateLeaveBalance();
            return true;
        }
        return false;
    }

    public function reject($reviewed_by, $comments) {
        $query = "UPDATE " . $this->table_name . "
                SET status='rejected', reviewed_by=:reviewed_by,
                    review_date=NOW(), review_comments=:comments
                WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":reviewed_by", $reviewed_by);
        $stmt->bindParam(":comments", $comments);

        return $stmt->execute();
    }

    private function updateLeaveBalance() {
        $query = "UPDATE leave_balances
                  SET days_taken = days_taken + :days,
                      days_remaining = days_remaining - :days
                  WHERE employee_id = :employee_id
                  AND leave_type_id = :leave_type_id
                  AND year = YEAR(NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":days", $this->days_requested);
        $stmt->bindParam(":employee_id", $this->employee_id);
        $stmt->bindParam(":leave_type_id", $this->leave_type_id);

        return $stmt->execute();
    }

    public function getLeaveBalance($employee_id, $year) {
        $query = "SELECT lb.*, lt.name as leave_type_name
                  FROM leave_balances lb
                  LEFT JOIN leave_types lt ON lb.leave_type_id = lt.id
                  WHERE lb.employee_id = ? AND lb.year = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $employee_id);
        $stmt->bindParam(2, $year);
        $stmt->execute();
        return $stmt;
    }
}
?>
