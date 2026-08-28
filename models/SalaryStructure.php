<?php
// backend/models/SalaryStructure.php

class SalaryStructure {
    private $conn;
    public $id;
    public $organization_id;
    public $title;
    public $description;
    public $basic_salary;
    public $is_template;
    public $active_from;
    public $active_to;
    public $status;
    public $created_by;
    public $currency;


    public function __construct($db) {
        $this->conn = $db;
    }

    // create structure with allowances and benefits handled in controller (transaction)
    public function create() {
        $sql = "INSERT INTO salary_structures (organization_id, title, description, basic_salary, is_template, active_from, active_to, status, created_by, currency)
                VALUES (:organization_id, :title, :description, :basic_salary, :is_template, :active_from, :active_to, :status, :created_by)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':organization_id', $this->organization_id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $this->title);
        $stmt->bindValue(':description', $this->description);
        $stmt->bindValue(':basic_salary', $this->basic_salary);
        $stmt->bindValue(':is_template', $this->is_template);
        $stmt->bindValue(':active_from', $this->active_from);
        $stmt->bindValue(':active_to', $this->active_to);
        $stmt->bindValue(':status', $this->status ?: 'active');
        $stmt->bindValue(':created_by', $this->created_by);
        $stmt->bindValue(':currency', $this->currency);
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function findById($id) {
        $sql = "SELECT * FROM salary_structures WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $fields) {
        // Build query dynamically and keep updated_at automatic
        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = :$k";
            $params[":$k"] = $v;
        }
        if (empty($sets)) return false;
        $sql = "UPDATE salary_structures SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function listForOrganization($org_id) {
        $sql = "SELECT * FROM salary_structures WHERE organization_id = :org_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':org_id', $org_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
