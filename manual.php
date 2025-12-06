<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = ""; // your DB password
$db   = "quotation_system";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// User data
$user1_username = "employee1";
$user1_password = password_hash("123456", PASSWORD_DEFAULT);
$user1_fullname = "John Doe";
$user1_type     = "employee";

$user2_username = "clerk1";
$user2_password = password_hash("123456", PASSWORD_DEFAULT);
$user2_fullname = "Jane Smith";
$user2_type     = "clerk";

// Insert query
$sql = "INSERT INTO users (username, password, user_type, full_name) VALUES
        ('$user1_username', '$user1_password', '$user1_type', '$user1_fullname'),
        ('$user2_username', '$user2_password', '$user2_type', '$user2_fullname')";

if ($conn->query($sql) === TRUE) {
    echo "Users created successfully";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
