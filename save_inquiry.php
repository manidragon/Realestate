<?php
// save_inquiry.php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$agent_id    = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
$property_id = isset($_POST['property_id']) ? (int)$_POST['property_id'] : 0;
$name        = trim($_POST['name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$message     = trim($_POST['message'] ?? '');

if ($agent_id <= 0 || $property_id <= 0) {
    die('Invalid agent or property.');
}

if ($name === '' || $email === '' || $phone === '' || $message === '') {
    die('Please fill all fields.');
}

// escape strings
$nameEsc    = $conn->real_escape_string($name);
$emailEsc   = $conn->real_escape_string($email);
$phoneEsc   = $conn->real_escape_string($phone);
$messageEsc = $conn->real_escape_string($message);

// INSERT (table name must match your DB)
$sql = "
  INSERT INTO property_inquiries (user_id, property_id, name, email, phone, message)
  VALUES ($agent_id, $property_id, '$nameEsc', '$emailEsc', '$phoneEsc', '$messageEsc')
";

if ($conn->query($sql) === TRUE) {
    // redirect back to property page with success flag
    header("Location: single-property-1.php?id=" . $property_id . "&inq=success");
    exit;
} else {
    echo "Error: " . $conn->error;
}
