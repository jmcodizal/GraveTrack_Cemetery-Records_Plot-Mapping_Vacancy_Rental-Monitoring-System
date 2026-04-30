<?php
session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/Database/db_connector.php";

try {
    // read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    $username = isset($data["username"]) ? trim($data["username"]) : null;
    $password = $data["password"] ?? null;

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Missing fields"
        ]);
        exit;
    }

    // connect DB
    $db = new db_connector();
    $conn = $db->connect();

    // find user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    $user = $stmt->fetch();

    if ($user) {
        $stored = $user["password"];
        $isValid = password_verify($password, $stored) || hash_equals($stored, $password);
        if (!$isValid) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Invalid credentials"
            ]);
            exit;
        }

        $_SESSION["user"] = $user["username"];

        echo json_encode([
            "success" => true,
            "message" => "Login successful"
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Invalid credentials"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
