<?php
session_start();
require "connect.php";

// user must be logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$uid = $_SESSION['user_id'];

// Receive form values
$firstName = $_POST['firstName'] ?? "";
$lastName  = $_POST['lastName'] ?? "";
$phone     = $_POST['phone'] ?? "";
$mobile    = $_POST['mobile'] ?? "";
$email     = $_POST['email'] ?? "";
$title     = $_POST['title'] ?? "";

$facebook  = $_POST['facebook'] ?? "";
$instagram = $_POST['instagram'] ?? "";
$twitter   = $_POST['twitter'] ?? "";
$linkedin  = $_POST['linkedin'] ?? "";
$website   = $_POST['website'] ?? "";

// Profile Image Upload
$profile_image = null;

if (!empty($_FILES['profile_image']['name'])) {
    $img = time() . "_" . basename($_FILES['profile_image']['name']);
    $target = "uploads/users/" . $img;

    if (!is_dir("uploads/users")) {
        mkdir("uploads/users", 0777, true);
    }

    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
        $profile_image = $img;
    }
}

// Build SQL
$sql = "UPDATE users SET 
        first_name=?, 
        last_name=?, 
        phone=?, 
        mobile=?, 
        email=?, 
        title=?, 
        facebook=?, 
        instagram=?, 
        twitter=?, 
        linkedin=?, 
        website=?";

if ($profile_image) {
    $sql .= ", profile_image='$profile_image'";
}

$sql .= " WHERE id=?";

// Prepare and bind
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sssssssssssi",
    $firstName,
    $lastName,
    $phone,
    $mobile,
    $email,
    $title,
    $facebook,
    $instagram,
    $twitter,
    $linkedin,
    $website,
    $uid
);

if ($stmt->execute()) {
    header("Location: dashboard-my-profiles.php?success=1");
    exit;
} else {
    echo "Error updating profile";
}
