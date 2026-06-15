<?php
// get_property.php - returns JSON for the edit modal
header('Content-Type: application/json; charset=utf-8');

// capture stray output for debug
ob_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// require DB connection
$connectPath = __DIR__ . '/connect.php';
if (!file_exists($connectPath)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Missing connect.php']);
    exit;
}
require_once $connectPath;
if (!isset($conn) || !($conn instanceof mysqli)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection ($conn) not available from connect.php']);
    exit;
}

function respond($payload)
{
    $buf = trim(ob_get_clean());
    if ($buf !== '') $payload['debug'] = strlen($buf) > 2000 ? substr($buf, 0, 2000) . '... (truncated)' : $buf;
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
    respond(['success' => false, 'error' => 'Invalid or missing property id']);
}
$pid = (int) $_GET['id'];

/* ---------- Fetch property ---------- */
$sql = "SELECT * FROM properties WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt === false) respond(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
$stmt->bind_param('i', $pid);
if (!$stmt->execute()) respond(['success' => false, 'error' => 'DB execute failed: ' . $stmt->error]);
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) respond(['success' => false, 'error' => 'Property not found']);
$prop = $res->fetch_assoc();
$stmt->close();

/* ---------- Fetch images (return id + url) ---------- */
$images = [];
if ($conn->query("SHOW TABLES LIKE 'property_images'")->num_rows > 0) {
    $q = $conn->prepare("SELECT id, file_path FROM property_images WHERE property_id = ? ORDER BY id ASC");
    if ($q) {
        $q->bind_param('i', $pid);
        $q->execute();
        $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $images[] = ['id' => (int)$row['id'], 'url' => $row['file_path']];
        }
        $q->close();
    } else {
        $images_error = 'property_images prepare failed: ' . $conn->error;
    }
}

/* ---------- Fetch videos (return id + url) ---------- */
$videos = [];
if ($conn->query("SHOW TABLES LIKE 'property_videos'")->num_rows > 0) {
    $q = $conn->prepare("SELECT id, file_path FROM property_videos WHERE property_id = ? ORDER BY id ASC");
    if ($q) {
        $q->bind_param('i', $pid);
        $q->execute();
        $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            $videos[] = ['id' => (int)$row['id'], 'url' => $row['file_path']];
        }
        $q->close();
    } else {
        $videos_error = 'property_videos prepare failed: ' . $conn->error;
    }
}

/* ---------- Fetch features (attempt JSON column then fallback table) ---------- */
$features = [];
if (array_key_exists('features', $prop) && $prop['features']) {
    $f = @json_decode($prop['features'], true);
    if (is_array($f)) $features = $f;
}

if (empty($features) && $conn->query("SHOW TABLES LIKE 'property_feature_notes'")->num_rows > 0) {
    $q = $conn->prepare("SELECT value FROM property_feature_notes WHERE property_id = ?");
    if ($q) {
        $q->bind_param('i', $pid);
        $q->execute();
        $r = $q->get_result();
        while ($row = $r->fetch_assoc()) {
            // values are stored like "Hospital: 2 km" or "CCTV"
            $features[] = $row['value'];
        }
        $q->close();
    } else {
        $features_error = 'property_feature_notes prepare failed: ' . $conn->error;
    }
}

/* ---------- Normalize features into map-like shape (key => value or true) ---------- */
$featMap = [];
if (!empty($features)) {
    if (array_keys($features) === range(0, count($features) - 1)) {
        // numeric array - items are strings like "CCTV" or "Hospital: 2 km"
        foreach ($features as $v) {
            if (!is_string($v)) continue;
            $parts = explode(':', $v, 2);
            $key = trim($parts[0]);
            $val = isset($parts[1]) ? trim($parts[1]) : true;
            $featMap[$key] = $val;
        }
    } elseif (is_array($features)) {
        // associative already
        foreach ($features as $k => $v) {
            $featMap[$k] = $v;
        }
    }
}

/* ---------- Build response ---------- */
$property = [
    'id' => (int)$prop['id'],
    'title' => $prop['title'] ?? '',
    'description' => $prop['description'] ?? '',
    'category' => $prop['category'] ?? '',
    'type' => $prop['type'] ?? '',
    'price' => $prop['price'] ?? '',
    'address' => $prop['address'] ?? '',
    'city' => $prop['city'] ?? '',
    'state' => $prop['state'] ?? '',
    'zip' => $prop['zip'] ?? '',
    'map' => $prop['map'] ?? '',
    'size' => $prop['size'] ?? '',
    'rooms' => $prop['rooms'] ?? '',
    'bedroom' => $prop['bedroom'] ?? '',
    'bathroom' => $prop['bathroom'] ?? '',
    'available' => $prop['available'] ?? '',
    'extradetails' => $prop['extradetails'] ?? '',
    'garages' => $prop['garages'] ?? '',
    'garagesize' => $prop['garagesize'] ?? '',
    'yearbuilt' => $prop['yearbuilt'] ?? '',
    'roofing' => $prop['roofing'] ?? '',
    'floors' => $prop['floors'] ?? '',
    'images' => $images,
    'videos' => $videos,
    'features' => $featMap
];

$response = ['success' => true, 'property' => $property];
$debugNotes = [];
if (!empty($images_error)) $debugNotes[] = $images_error;
if (!empty($videos_error)) $debugNotes[] = $videos_error;
if (!empty($features_error)) $debugNotes[] = $features_error;
if (!empty($debugNotes)) $response['notes'] = $debugNotes;

respond($response);
