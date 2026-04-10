<?php
class db_connector {
    private $host = "localhost";
    private $db_name = "gravetrack_db";
    private $username = "root";
    private $password = "";
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );

            // Error handling
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Fetch as associative array
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new PDOException("Database Connection Failed: " . $e->getMessage());
        }

        return $this->conn;
    }

    public function getBurialRecords($search = '') {
        try {
            $conn = $this->connect();
            $sql = "SELECT * FROM burial_records_view";
            $params = [];
            if ($search) {
            $sql .= " WHERE full_name LIKE :search OR full_name LIKE :search OR plot_code LIKE :search";

                $params[':search'] = "%$search%";
            }
            $sql .= " ORDER BY date_of_death DESC LIMIT 100";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Burial records query failed: " . $e->getMessage());
            return [];
        }
    }
}
?>

