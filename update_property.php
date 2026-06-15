<?php
// update_property.php - accepts updates, deletes, replacements and new uploads
header('Content-Type: application/json; charset=utf-8');

ob_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// require DB
$connectPath = __DIR__ . '/connect.php';
if (!file_exists($connectPath)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Missing connect.php']);
    exit;
}
require_once $connectPath;
if (!isset($conn) || !($conn instanceof mysqli)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection ($conn) not available']);
    exit;
}

function respond($payload)
{
    $buf = trim(ob_get_clean());
    if ($buf !== '') $payload['debug'] = strlen($buf) > 2000 ? substr($buf, 0, 2000) . '... (truncated)' : $buf;
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success' => false, 'error' => 'Only POST allowed']);

$debugMode = isset($_POST['__debug']) && $_POST['__debug'] === '1';

function p($k)
{
    return isset($_POST[$k]) ? trim($_POST[$k]) : '';
}

$propertyId = (int) p('id');
if ($propertyId <= 0) respond(['success' => false, 'error' => 'Invalid property id']);

// Basic fields
$title        = p('title');
$description  = p('description');
$category     = p('category');
$type         = p('type');
$price        = p('price');
$address      = p('address');
$state        = p('state');
$city         = p('city');
$zip          = p('zip');
$size         = p('size');
$rooms        = p('rooms');
$bathroom     = p('bathroom');
$bedroom      = p('bedroom');
$available    = p('available');
$extradetails = p('extradetails');
$garages      = p('garages');
$garagesize   = p('garagesize');
$yearbuilt    = p('yearbuilt');
$roofing      = p('roofing');
$floors       = p('floors');
$map          = p('map');

// limits & allowed
$MAX_IMG_BYTES = 8 * 1024 * 1024; // 8MB
$MAX_VID_BYTES = 60 * 1024 * 1024; // 60MB
$ALLOWED_IMG_EXT = ['jpg', 'jpeg', 'png', 'webp'];
$ALLOWED_VID_EXT = ['mp4', 'webm', 'ogg'];

// helper to save uploaded tmp file
function saveUploadFile($tmpPath, $destDir, $origName, $maxSize, $allowedExts)
{
    if (!is_uploaded_file($tmpPath)) return [false, 'no_upload'];
    if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) return [false, 'mkdir_failed'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) return [false, 'invalid_ext'];
    if (filesize($tmpPath) > $maxSize) return [false, 'too_large'];
    $newName = uniqid('', true) . '.' . $ext;
    $dest = rtrim($destDir, '/') . '/' . $newName;
    if (!move_uploaded_file($tmpPath, $dest)) return [false, 'move_failed'];
    $rel = str_replace(__DIR__ . '/', '', $dest);
    return [true, $rel];
}

// Begin transaction
$conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

try {
    // 1) Update properties
    $sql = "UPDATE properties SET
        title=?, description=?, category=?, type=?, price=?, address=?, state=?, city=?, zip=?,
        size=?, rooms=?, bathroom=?, bedroom=?, available=?, extradetails=?, garages=?, garagesize=?,
        yearbuilt=?, roofing=?, floors=?, map=?
        WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param(
        'sssssssssssssssssssssi',
        $title,
        $description,
        $category,
        $type,
        $price,
        $address,
        $state,
        $city,
        $zip,
        $size,
        $rooms,
        $bathroom,
        $bedroom,
        $available,
        $extradetails,
        $garages,
        $garagesize,
        $yearbuilt,
        $roofing,
        $floors,
        $map,
        $propertyId
    );
    if (!$stmt->execute()) throw new Exception('Update properties failed: ' . $stmt->error);
    $stmt->close();

    // 2) Features (simple: delete existing notes and insert new ones)
    if ($conn->query("SHOW TABLES LIKE 'property_feature_notes'")->num_rows > 0) {
        $conn->query("DELETE FROM property_feature_notes WHERE property_id = " . $propertyId);
        $features = isset($_POST['features']) && is_array($_POST['features']) ? $_POST['features'] : [];
        $dist = isset($_POST['distance']) && is_array($_POST['distance']) ? $_POST['distance'] : [];
        if (!empty($features)) {
            $ins = $conn->prepare("INSERT INTO property_feature_notes (property_id, value) VALUES (?, ?)");
            foreach ($features as $key => $label) {
                $label = trim($label);
                if ($label === '') continue;
                $d = isset($dist[$key]) ? trim($dist[$key]) : '';
                $combined = $label . ($d !== '' ? (': ' . $d) : '');
                $ins->bind_param('is', $propertyId, $combined);
                if (!$ins->execute()) throw new Exception('Insert feature failed: ' . $ins->error);
            }
            $ins->close();
        }
    }

    // 3) Deletes - images
    $deletedImages = isset($_POST['delete_images']) && is_array($_POST['delete_images']) ? $_POST['delete_images'] : [];
    if (!empty($deletedImages)) {
        $sel = $conn->prepare("SELECT file_path FROM property_images WHERE id = ? AND property_id = ?");
        $del = $conn->prepare("DELETE FROM property_images WHERE id = ? AND property_id = ?");
        foreach ($deletedImages as $did) {
            $did = (int)$did;
            if ($did <= 0) continue;
            $sel->bind_param('ii', $did, $propertyId);
            $sel->execute();
            $res = $sel->get_result();
            if ($row = $res->fetch_assoc()) {
                $full = __DIR__ . '/' . ltrim($row['file_path'], '/');
                if (file_exists($full)) @unlink($full);
                $del->bind_param('ii', $did, $propertyId);
                $del->execute();
            }
        }
        $sel->close();
        $del->close();
    }

    // 4) Deletes - videos
    $deletedVideos = isset($_POST['delete_videos']) && is_array($_POST['delete_videos']) ? $_POST['delete_videos'] : [];
    if (!empty($deletedVideos)) {
        $sel = $conn->prepare("SELECT file_path FROM property_videos WHERE id = ? AND property_id = ?");
        $del = $conn->prepare("DELETE FROM property_videos WHERE id = ? AND property_id = ?");
        foreach ($deletedVideos as $did) {
            $did = (int)$did;
            if ($did <= 0) continue;
            $sel->bind_param('ii', $did, $propertyId);
            $sel->execute();
            $res = $sel->get_result();
            if ($row = $res->fetch_assoc()) {
                $full = __DIR__ . '/' . ltrim($row['file_path'], '/');
                if (file_exists($full)) @unlink($full);
                $del->bind_param('ii', $did, $propertyId);
                $del->execute();
            }
        }
        $sel->close();
        $del->close();
    }

    // 5) Parse replace files from $_FILES (support both styles)
    $replaceImages = [];
    $replaceVideos = [];
    foreach ($_FILES as $key => $f) {
        // array style: replace_images[123]
        if ($key === 'replace_images' && isset($f['name']) && is_array($f['name'])) {
            foreach ($f['name'] as $sub => $n) {
                if (!isset($f['tmp_name'][$sub]) || !$f['tmp_name'][$sub]) continue;
                $replaceImages[(int)$sub] = [
                    'tmp_name' => $f['tmp_name'][$sub],
                    'name' => $n,
                    'size' => $f['size'][$sub] ?? 0,
                    'error' => $f['error'][$sub] ?? UPLOAD_ERR_OK
                ];
            }
        }
        if ($key === 'replace_videos' && isset($f['name']) && is_array($f['name'])) {
            foreach ($f['name'] as $sub => $n) {
                if (!isset($f['tmp_name'][$sub]) || !$f['tmp_name'][$sub]) continue;
                $replaceVideos[(int)$sub] = [
                    'tmp_name' => $f['tmp_name'][$sub],
                    'name' => $n,
                    'size' => $f['size'][$sub] ?? 0,
                    'error' => $f['error'][$sub] ?? UPLOAD_ERR_OK
                ];
            }
        }
        // flat keys: replace_images_123
        if (preg_match('#^replace_images_(\d+)$#', $key, $m)) {
            $id = (int)$m[1];
            if (!empty($f['tmp_name'])) $replaceImages[$id] = ['tmp_name' => $f['tmp_name'], 'name' => $f['name'], 'size' => $f['size'] ?? 0, 'error' => $f['error'] ?? UPLOAD_ERR_OK];
        }
        if (preg_match('#^replace_videos_(\d+)$#', $key, $m)) {
            $id = (int)$m[1];
            if (!empty($f['tmp_name'])) $replaceVideos[$id] = ['tmp_name' => $f['tmp_name'], 'name' => $f['name'], 'size' => $f['size'] ?? 0, 'error' => $f['error'] ?? UPLOAD_ERR_OK];
        }
    }

    // 6) Apply image replacements
    if (!empty($replaceImages)) {
        $sel = $conn->prepare("SELECT file_path FROM property_images WHERE id = ? AND property_id = ?");
        $upd = $conn->prepare("UPDATE property_images SET file_path = ? WHERE id = ? AND property_id = ?");
        foreach ($replaceImages as $imgId => $file) {
            $imgId = (int)$imgId;
            if ($imgId <= 0) continue;
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $sel->bind_param('ii', $imgId, $propertyId);
            $sel->execute();
            $res = $sel->get_result();
            if ($row = $res->fetch_assoc()) {
                $imgDir = __DIR__ . "/uploads/properties/{$propertyId}/images/";
                list($ok, $resPath) = saveUploadFile($file['tmp_name'], $imgDir, $file['name'], $MAX_IMG_BYTES, $ALLOWED_IMG_EXT);
                if (!$ok) continue; // skip failed file rather than abort whole transaction
                $old = __DIR__ . '/' . ltrim($row['file_path'], '/');
                if (file_exists($old)) @unlink($old);
                $upd->bind_param('sii', $resPath, $imgId, $propertyId);
                if (!$upd->execute()) throw new Exception('Update image row failed: ' . $upd->error);
            }
        }
        $sel->close();
        $upd->close();
    }

    // 7) Apply video replacements
    if (!empty($replaceVideos)) {
        $sel = $conn->prepare("SELECT file_path FROM property_videos WHERE id = ? AND property_id = ?");
        $upd = $conn->prepare("UPDATE property_videos SET file_path = ? WHERE id = ? AND property_id = ?");
        foreach ($replaceVideos as $vidId => $file) {
            $vidId = (int)$vidId;
            if ($vidId <= 0) continue;
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $sel->bind_param('ii', $vidId, $propertyId);
            $sel->execute();
            $res = $sel->get_result();
            if ($row = $res->fetch_assoc()) {
                $vidDir = __DIR__ . "/uploads/properties/{$propertyId}/videos/";
                list($ok, $resPath) = saveUploadFile($file['tmp_name'], $vidDir, $file['name'], $MAX_VID_BYTES, $ALLOWED_VID_EXT);
                if (!$ok) continue;
                $old = __DIR__ . '/' . ltrim($row['file_path'], '/');
                if (file_exists($old)) @unlink($old);
                $upd->bind_param('sii', $resPath, $vidId, $propertyId);
                if (!$upd->execute()) throw new Exception('Update video row failed: ' . $upd->error);
            }
        }
        $sel->close();
        $upd->close();
    }

    // 8) Insert new images (property_images[])
    if (!empty($_FILES['property_images']['name']) && is_array($_FILES['property_images']['name'])) {
        $imgDir = __DIR__ . "/uploads/properties/{$propertyId}/images/";
        if (!is_dir($imgDir)) @mkdir($imgDir, 0775, true);
        $insImg = $conn->prepare("INSERT INTO property_images (property_id, file_path) VALUES (?, ?)");
        for ($i = 0; $i < count($_FILES['property_images']['name']); $i++) {
            $err = $_FILES['property_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err !== UPLOAD_ERR_OK) continue;
            $tmp = $_FILES['property_images']['tmp_name'][$i];
            $orig = $_FILES['property_images']['name'][$i];
            list($ok, $resPath) = saveUploadFile($tmp, $imgDir, $orig, $MAX_IMG_BYTES, $ALLOWED_IMG_EXT);
            if (!$ok) continue;
            $insImg->bind_param('is', $propertyId, $resPath);
            if (!$insImg->execute()) throw new Exception('Insert property_images failed: ' . $insImg->error);
        }
        $insImg->close();
    }

    // 9) Insert new videos (property_videos[])
    if (!empty($_FILES['property_videos']['name']) && is_array($_FILES['property_videos']['name'])) {
        $vidDir = __DIR__ . "/uploads/properties/{$propertyId}/videos/";
        if (!is_dir($vidDir)) @mkdir($vidDir, 0775, true);
        $insVid = $conn->prepare("INSERT INTO property_videos (property_id, file_path) VALUES (?, ?)");
        for ($i = 0; $i < count($_FILES['property_videos']['name']); $i++) {
            $err = $_FILES['property_videos']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err !== UPLOAD_ERR_OK) continue;
            $tmp = $_FILES['property_videos']['tmp_name'][$i];
            $orig = $_FILES['property_videos']['name'][$i];
            list($ok, $resPath) = saveUploadFile($tmp, $vidDir, $orig, $MAX_VID_BYTES, $ALLOWED_VID_EXT);
            if (!$ok) continue;
            $insVid->bind_param('is', $propertyId, $resPath);
            if (!$insVid->execute()) throw new Exception('Insert property_videos failed: ' . $insVid->error);
        }
        $insVid->close();
    }

    // Commit
    $conn->commit();

    $resp = ['success' => true, 'message' => 'Property updated'];

    if ($debugMode) {
        $resp['debug_info'] = [
            'post_keys' => array_keys($_POST),
            'files_keys' => array_keys($_FILES),
            'deleted_images' => $deletedImages,
            'deleted_videos' => $deletedVideos,
            'replace_images_ids' => array_keys($replaceImages),
            'replace_videos_ids' => array_keys($replaceVideos)
        ];
        // include files detail
        $fd = [];
        foreach ($_FILES as $k => $f) {
            if (is_array($f['name'])) {
                $arr = [];
                foreach ($f['name'] as $i => $n) $arr[] = ['name' => $n, 'size' => $f['size'][$i] ?? 0];
                $fd[$k] = $arr;
            } else {
                $fd[$k] = ['name' => $f['name'], 'size' => $f['size'] ?? 0];
            }
        }
        $resp['debug_info']['files'] = $fd;
    }

    respond($resp);
} catch (Exception $e) {
    $conn->rollback();
    respond(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
