<?php
include('connect.php');


header('Content-Type: application/json');

if (isset($_POST['username02'], $_POST['password02'])) {
    $username = $_POST['username02'];
    $password = $_POST['password02'];

    $sql = "SELECT id, username, password, status, usertype FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $storedHash = $user['password'];

        if ($user['status'] === 'pending') {
            echo json_encode(["status" => "error", "message" => "Your account is pending approval."]);
            exit;
        }

        if ($user['status'] === 'rejected') {
            echo json_encode(["status" => "error", "message" => "Your registration was rejected."]);
            exit;
        }

        // Single password check
        if (password_verify($password, $storedHash)) {

            // Determine user type safely from available columns (fallback to 'user')
            $usertype = 'user';
            if (!empty($user['usertype'])) {
                $usertype = $user['usertype'];
            } 

            session_start();
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['usertype']  = $usertype;

            // Return different status codes for admin vs normal user
            if (strtolower($usertype) === 'admin') {
                echo json_encode(["status" => "success2", "message" => "Admin login successful! Redirecting..."]);
                exit;
            } else {
                echo json_encode(["status" => "success1", "message" => "Login successful! Redirecting..."]);
                exit;
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid password."]);
            exit;
        }
    } else {
        echo json_encode(["status" => "error", "message" => "User not found."]);
        exit;
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}
