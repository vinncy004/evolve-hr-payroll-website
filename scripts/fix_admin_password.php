<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Hash the password properly
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Update admin user
$query = "UPDATE users SET password_hash = :password_hash WHERE username = 'admin'";
$stmt = $db->prepare($query);
$stmt->bindParam(':password_hash', $hashed_password);

if($stmt->execute()) {
    echo "Admin password has been reset to: admin123\n";
    echo "Hashed password: " . $hashed_password . "\n";
} else {
    echo "Failed to update password\n";
}

// Check if admin user exists
$check_query = "SELECT id, username, email, role FROM users WHERE username = 'admin'";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute();
$user = $check_stmt->fetch(PDO::FETCH_ASSOC);

if($user) {
    echo "\nAdmin user found:\n";
    print_r($user);
} else {
    echo "\nAdmin user not found. Creating one...\n";

    $create_query = "INSERT INTO users (username, email, password_hash, role, is_active)
                     VALUES ('admin', 'admin@evolve.com', :password_hash, 'admin', 1)";
    $create_stmt = $db->prepare($create_query);
    $create_stmt->bindParam(':password_hash', $hashed_password);

    if($create_stmt->execute()) {
        echo "Admin user created successfully!\n";
    } else {
        echo "Failed to create admin user\n";
    }
}
?>
