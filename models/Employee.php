<?php

// backend/models/Employee.php

class Employee {
    private $conn;
    private $table_name = "employees";

    public $id;
    public $employee_number;
    public $first_name;
    public $middle_name;
    public $last_name;
    public $national_id;
    public $kra_pin;
    public $shif_number;
    public $nssf_number;
    public $date_of_birth;
    public $gender;
    public $phone_number;
    public $personal_email;
    public $work_email;
    public $department_id;
    public $position_id;
    public $manager_id;
    public $employment_type;
    public $employment_status;
    public $hire_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
    $query = "INSERT INTO " . $this->table_name . "
            SET employee_number=:employee_number, first_name=:first_name,
                middle_name=:middle_name, last_name=:last_name,
                national_id=:national_id, kra_pin=:kra_pin,
                shif_number=:shif_number, nssf_number=:nssf_number,
                date_of_birth=:date_of_birth, gender=:gender,
                phone_number=:phone_number, personal_email=:personal_email,
                work_email=:work_email, department_id=:department_id,
                position_id=:position_id, manager_id=:manager_id,
                employment_type=:employment_type, employment_status=:employment_status,
                hire_date=:hire_date";

    $stmt = $this->conn->prepare($query);

    // Sanitize inputs
    $this->employee_number = htmlspecialchars(strip_tags($this->employee_number));
    $this->first_name = htmlspecialchars(strip_tags($this->first_name));
    $this->last_name = htmlspecialchars(strip_tags($this->last_name));
    $this->national_id = htmlspecialchars(strip_tags($this->national_id));
    $this->kra_pin = htmlspecialchars(strip_tags($this->kra_pin));

    // Bind parameters
    $stmt->bindParam(":employee_number", $this->employee_number);
    $stmt->bindParam(":first_name", $this->first_name);
    $stmt->bindParam(":middle_name", $this->middle_name);
    $stmt->bindParam(":last_name", $this->last_name);
    $stmt->bindParam(":national_id", $this->national_id);
    $stmt->bindParam(":kra_pin", $this->kra_pin);
    $stmt->bindParam(":shif_number", $this->shif_number);
    $stmt->bindParam(":nssf_number", $this->nssf_number);
    $stmt->bindParam(":date_of_birth", $this->date_of_birth);
    $stmt->bindParam(":gender", $this->gender);
    $stmt->bindParam(":phone_number", $this->phone_number);
    $stmt->bindParam(":personal_email", $this->personal_email);
    $stmt->bindParam(":work_email", $this->work_email);
    $stmt->bindParam(":department_id", $this->department_id);
    $stmt->bindParam(":position_id", $this->position_id);
    $stmt->bindParam(":manager_id", $this->manager_id);
    $stmt->bindParam(":employment_type", $this->employment_type);
    $stmt->bindParam(":employment_status", $this->employment_status);
    $stmt->bindParam(":hire_date", $this->hire_date);

    if($stmt->execute()) {
        // Persist the newly created id to the model instance for callers
        $this->id = $this->conn->lastInsertId();
        return true;
    }
    return false;
}


    public function read() {
        $query = "SELECT e.*, d.name as department_name, p.title as position_title,
                  CONCAT(m.first_name, ' ', m.last_name) as manager_name
                  FROM " . $this->table_name . " e
                  LEFT JOIN departments d ON e.department_id = d.id
                  LEFT JOIN positions p ON e.position_id = p.id
                  LEFT JOIN employees m ON e.manager_id = m.id
                  WHERE e.employment_status = 'active'
                  ORDER BY e.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT e.*, d.name as department_name, p.title as position_title,
                  CONCAT(m.first_name, ' ', m.last_name) as manager_name
                  FROM " . $this->table_name . " e
                  LEFT JOIN departments d ON e.department_id = d.id
                  LEFT JOIN positions p ON e.position_id = p.id
                  LEFT JOIN employees m ON e.manager_id = m.id
                  WHERE e.id = ?
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->employee_number = $row['employee_number'];
            $this->first_name = $row['first_name'];
            $this->middle_name = $row['middle_name'];
            $this->last_name = $row['last_name'];
            $this->national_id = $row['national_id'];
            $this->kra_pin = $row['kra_pin'];
            $this->shif_number = $row['shif_number'];
            $this->nssf_number = $row['nssf_number'];
            $this->date_of_birth = $row['date_of_birth'];
            $this->gender = $row['gender'];
            $this->phone_number = $row['phone_number'];
            $this->personal_email = $row['personal_email'] ?? null;
            $this->work_email = $row['work_email'] ?? null;
            $this->department_id = $row['department_id'];
            $this->position_id = $row['position_id'];
            $this->manager_id = $row['manager_id'] ?? null;
            $this->employment_type = $row['employment_type'];
            $this->employment_status = $row['employment_status'];
            $this->hire_date = $row['hire_date'] ?? null;
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET first_name=:first_name, middle_name=:middle_name,
                    last_name=:last_name, national_id=:national_id,
                    kra_pin=:kra_pin, shif_number=:shif_number,
                    nssf_number=:nssf_number, date_of_birth=:date_of_birth,
                    gender=:gender, phone_number=:phone_number,
                    personal_email=:personal_email, work_email=:work_email,
                    department_id=:department_id, position_id=:position_id,
                    manager_id=:manager_id, employment_type=:employment_type,
                    employment_status=:employment_status
                WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->national_id = htmlspecialchars(strip_tags($this->national_id));
        $this->kra_pin = htmlspecialchars(strip_tags($this->kra_pin));

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":national_id", $this->national_id);
        $stmt->bindParam(":kra_pin", $this->kra_pin);
        $stmt->bindParam(":shif_number", $this->shif_number);
        $stmt->bindParam(":nssf_number", $this->nssf_number);
        $stmt->bindParam(":date_of_birth", $this->date_of_birth);
        $stmt->bindParam(":gender", $this->gender);
        $stmt->bindParam(":phone_number", $this->phone_number);
        $stmt->bindParam(":personal_email", $this->personal_email);
        $stmt->bindParam(":work_email", $this->work_email);
        $stmt->bindParam(":department_id", $this->department_id);
        $stmt->bindParam(":position_id", $this->position_id);
        $stmt->bindParam(":manager_id", $this->manager_id);
        $stmt->bindParam(":employment_type", $this->employment_type);
        $stmt->bindParam(":employment_status", $this->employment_status);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "UPDATE " . $this->table_name . "
                  SET employment_status='terminated', termination_date=NOW()
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function search($keywords) {
        $query = "SELECT e.*, d.name as department_name, p.title as position_title
                  FROM " . $this->table_name . " e
                  LEFT JOIN departments d ON e.department_id = d.id
                  LEFT JOIN positions p ON e.position_id = p.id
                  WHERE e.first_name LIKE ? OR e.last_name LIKE ?
                  OR e.employee_number LIKE ? OR e.national_id LIKE ?
                  ORDER BY e.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $keywords = "%{$keywords}%";
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);
        $stmt->bindParam(4, $keywords);
        $stmt->execute();

        return $stmt;
    }
}
?>
