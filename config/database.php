<?php
class Database
{
    private $host = 'localhost';
    private $dbname = 'test';   // change to your actual database name
    private $username = 'root';
    private $password = '';
    private $pdo = null;

    /**
     * Returns a PDO instance or false on failure.
     */
    public function getConnection()
    {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $this->username, $this->password);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                // Optionally log the error: error_log($e->getMessage());
                return false;
            }
        }
        return $this->pdo;
    }
}
?>
