<?php
// Secure registration backend
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);
ob_clean(); // clear any accidental whitespace

include 'connect.php';

if (isset($_POST['mobile01'], $_POST['username01'], $_POST['password01'])) {
    $mobile = trim($_POST['mobile01']);
    $username = trim($_POST['username01']);
    $password = $_POST['password01'];

    // Validation
    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        echo json_encode([
            "status" => "error",
            "message" => "Mobile number must be exactly 10 digits."
        ]);
        exit;
    }
    if (!preg_match("/^[A-Za-z0-9_]{4,15}$/", $username)) {
        echo json_encode(["status" => "error", "message" => "Invalid username format."]);
        exit;
    }
    if (!preg_match("/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/", $password)) {
        echo json_encode(["status" => "error", "message" => "Weak password."]);
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check duplicate username
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Username already taken."]);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (mobile, username, password, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("sss", $mobile, $username, $hashedPassword);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Registration successful! Wait for admin approval."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error. Try again later."]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
