<?php
include 'connect.php';

if (isset($_GET['deleteid'])) {
    $id = (int)$_GET['deleteid'];

    // Path: /uploads/properties/{id}/
    $baseDir = "uploads/properties/$id";

    // -------- 1) Delete all images from property_images table -------- //
    $sql1 = "SELECT file_path FROM property_images WHERE property_id = $id";
    $result1 = mysqli_query($conn, $sql1);

    while ($row = mysqli_fetch_assoc($result1)) {
        $img = $row['file_path'];

        // Try different possible paths
        $paths = [
            $img,
            "$baseDir/images/$img",
            "uploads/$img",
            "images/$img"
        ];

        foreach ($paths as $p) {
            if (file_exists($p) && is_file($p)) {
                unlink($p); // delete file
            }
        }
    }

    // Delete DB rows from property_images table
    mysqli_query($conn, "DELETE FROM property_images WHERE property_id = $id");

    // -------- 2) Delete entire folder uploads/properties/{id} -------- //
    function deleteFolder($folderPath)
    {
        if (!is_dir($folderPath)) return;

        $files = array_diff(scandir($folderPath), ['.', '..']);

        foreach ($files as $file) {
            $fullPath = $folderPath . '/' . $file;

            if (is_dir($fullPath)) {
                deleteFolder($fullPath); // recursive delete
            } else {
                unlink($fullPath); // delete file
            }
        }

        rmdir($folderPath); // delete EMPTY folder
    }

    // Remove uploads/properties/{id}
    deleteFolder($baseDir);

    // -------- 3) Delete the property from DB -------- //
    $sql2 = "DELETE FROM properties WHERE id = $id";
    $result2 = mysqli_query($conn, $sql2);

    if ($result2) {
        header("Location: dashboard-my-properties.php?deletestatus=1");
        exit;
    } else {
        die(mysqli_error($conn));
    }
}
