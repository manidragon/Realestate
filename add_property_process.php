<?php
session_start();
include('connect.php'); // must set $conn = new mysqli(...)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

// ------------ BASIC FIELDS ------------
$title        = $_POST['title']        ?? '';
$description  = $_POST['description']  ?? '';
$category     = $_POST['category']     ?? '';
$type         = $_POST['type']         ?? '';
$price        = $_POST['price']        ?? '';
$address      = $_POST['address']      ?? '';
$state        = $_POST['state']        ?? '';
$city         = $_POST['city']         ?? '';
$zip          = $_POST['zip']          ?? '';
$size         = $_POST['size']         ?? '';
$rooms        = $_POST['rooms']        ?? '';
$bathroom     = $_POST['bathroom']     ?? '';
$bedroom      = $_POST['bedroom']      ?? '';
$available    = $_POST['available']    ?? '';
$extradetails = $_POST['extradetails'] ?? '';
$garages      = $_POST['garages']      ?? '';
$garagesize   = $_POST['garagesize']   ?? '';
$yearbuilt    = $_POST['yearbuilt']    ?? '';
$roofing      = $_POST['roofing']      ?? '';
$floors       = $_POST['floors']       ?? '';

// ------------ SINGLE MAP (stores filename in properties.map) ------------
$map = $_POST['map']       ?? '';

$user_id = $_SESSION['user_id'];




// ------------ INSERT PROPERTY ------------
$sql = "INSERT INTO properties 
  (title, user_id, description, category, type, price, address, state, city, zip, size, rooms, bathroom, bedroom, available, extradetails, garages, garagesize, yearbuilt, roofing, floors, map)
  VALUES
  ('" . $conn->real_escape_string($title) . "',
  '" . $conn->real_escape_string($user_id) . "',
   '" . $conn->real_escape_string($description) . "',
   '" . $conn->real_escape_string($category) . "',
   '" . $conn->real_escape_string($type) . "',
   '" . $conn->real_escape_string($price) . "',
   '" . $conn->real_escape_string($address) . "',
   '" . $conn->real_escape_string($state) . "',
   '" . $conn->real_escape_string($city) . "',
   '" . $conn->real_escape_string($zip) . "',
   '" . $conn->real_escape_string($size) . "',
   '" . $conn->real_escape_string($rooms) . "',
   '" . $conn->real_escape_string($bathroom) . "',
   '" . $conn->real_escape_string($bedroom) . "',
   '" . $conn->real_escape_string($available) . "',
   '" . $conn->real_escape_string($extradetails) . "',
   '" . $conn->real_escape_string($garages) . "',
   '" . $conn->real_escape_string($garagesize) . "',
   '" . $conn->real_escape_string($yearbuilt) . "',
   '" . $conn->real_escape_string($roofing) . "',
   '" . $conn->real_escape_string($floors) . "',
   '" . $conn->real_escape_string($map) . "'
  )";

if (!$conn->query($sql)) {
    echo "<script>alert('Error adding property: " . $conn->error . "'); history.back();</script>";
    exit;
}

$propertyId = (int)$conn->insert_id;

$features = isset($_POST['features']) && is_array($_POST['features']) ? $_POST['features'] : [];
$dist     = isset($_POST['distance']) && is_array($_POST['distance']) ? $_POST['distance'] : [];

foreach ($features as $key => $label) {
    // Only process if the checkbox was checked (it will be present in $_POST)
    $label    = trim($label);
    $d        = isset($dist[$key]) ? trim($dist[$key]) : '';
    $combined = $label . ($d !== '' ? (': ' . $d) : ''); // e.g. "School: 20 km" or just "CCTV"

    // Simple insert (matches your style). You can switch to prepared statements if you want.
    $val = $conn->real_escape_string($combined);
    $conn->query("INSERT INTO property_feature_notes (property_id, value) VALUES ($propertyId, '$val')");
}

// ------------ MULTIPLE IMAGES (property_images[]) ------------
if (!empty($_FILES['property_images']['name']) && is_array($_FILES['property_images']['name'])) {
    $imgDir = "uploads/properties/$propertyId/images/";
    if (!is_dir($imgDir)) {
        @mkdir($imgDir, 0775, true);
    }
    for ($i = 0; $i < count($_FILES['property_images']['name']); $i++) {
        if (($_FILES['property_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['property_images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;
        $name = $propertyId . '_img_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['property_images']['tmp_name'][$i], $imgDir . $name)) {
            $path = $conn->real_escape_string($imgDir . $name);
            $conn->query("INSERT INTO property_images (property_id, file_path) VALUES ($propertyId, '$path')");
        }
    }
}

// ------------ MULTIPLE VIDEOS (property_videos[]) ------------
if (!empty($_FILES['property_videos']['name']) && is_array($_FILES['property_videos']['name'])) {
    $vidDir = "uploads/properties/$propertyId/videos/";
    if (!is_dir($vidDir)) {
        @mkdir($vidDir, 0775, true);
    }

    for ($i = 0; $i < count($_FILES['property_videos']['name']); $i++) {
        if (($_FILES['property_videos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($_FILES['property_videos']['name'][$i], PATHINFO_EXTENSION));
        if ($ext !== 'mp4') continue;

        $name = $propertyId . '_vid_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['property_videos']['tmp_name'][$i], $vidDir . $name)) {
            $path = $conn->real_escape_string($vidDir . $name);
            $conn->query("INSERT INTO property_videos (property_id, file_path) VALUES ($propertyId, '$path')");
        }
    }
}

echo "<script>alert('Property added successfully!'); window.location='dashboard.php';</script>";
$conn->close();
