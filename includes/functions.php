<?php
// includes/functions.php

require_once __DIR__ . '/../config/config.php';

session_start();

// Sanitize user input
function clean_input($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Check if admin is logged in
function check_login()
{
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Upload Image Function (Optimized)
function upload_image($file, $target_dir = "../uploads/")
{
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $fileName = basename($file["name"]);
    $imageFileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Generate unique name
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_path = $target_dir . $new_filename;

    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ["error" => "File is not an image."];
    }

    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($imageFileType, $allowed_types)) {
        return ["error" => "Sorry, only JPG, JPEG, PNG, GIF & WEBP files are allowed."];
    }

    // Size validation (Max 5MB)
    if ($file["size"] > 5000000) {
        return ["error" => "Sorry, your file is too large. Max 5MB."];
    }

    // Optimization Logic
    $source = $file["tmp_name"];
    $max_width = 800; // Max width for optimization
    $quality = 75; // Compression quality

    list($width, $height) = getimagesize($source);
    $ratio = $width / $height;

    // Calculate new dimensions if resizing is needed
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    // Create a new true color image
    $thumb = imagecreatetruecolor($new_width, $new_height);

    // Load source image based on type
    switch ($imageFileType) {
        case 'jpg':
        case 'jpeg':
            $source_image = imagecreatefromjpeg($source);
            break;
        case 'png':
            $source_image = imagecreatefrompng($source);
            // Preserve transparency for PNG
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            break;
        case 'gif':
            $source_image = imagecreatefromgif($source);
            // Preserve transparency for GIF
            $transparent_index = imagecolortransparent($source_image);
            if ($transparent_index >= 0) {
                imagepalettecopy($thumb, $source_image);
                imagefill($thumb, 0, 0, $transparent_index);
                imagecolortransparent($thumb, $transparent_index);
                imagetruecolortopalette($thumb, true, 256);
            }
            break;
        case 'webp':
            $source_image = imagecreatefromwebp($source);
            break;
        default:
            return ["error" => "Unsupported file type for optimization."];
    }

    if (!$source_image) {
        // Fallback if GD fails to load
        if (move_uploaded_file($source, $target_path)) {
            return ["success" => $new_filename];
        }
        return ["error" => "Failed to process image."];
    }

    // Resize
    imagecopyresampled($thumb, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save optimized image
    $saved = false;
    switch ($imageFileType) {
        case 'jpg':
        case 'jpeg':
            $saved = imagejpeg($thumb, $target_path, $quality);
            break;
        case 'png':
            // PNG quality is 0-9 (inverted logic 0 is max quality, but here we use compression level)
            // A value of 9 is max compression. 
            $saved = imagepng($thumb, $target_path, 8);
            break;
        case 'gif':
            $saved = imagegif($thumb, $target_path);
            break;
        case 'webp':
            $saved = imagewebp($thumb, $target_path, $quality);
            break;
    }

    // Cleanup
    imagedestroy($thumb);
    imagedestroy($source_image);

    if ($saved) {
        return ["success" => $new_filename];
    } else {
        return ["error" => "Sorry, there was an error uploading/optimizing your file."];
    }
}

// Get Site Content (Dynamic Text)
function get_site_content($key)
{
    global $conn;
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['content_value'];
    }
    return ""; // Return empty string if not found
}

// Get Internships
function get_internships()
{
    global $conn;
    $result = $conn->query("SELECT * FROM internships ORDER BY created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get Hero Section Data
function get_hero_data()
{
    global $conn;
    $sql = "SELECT * FROM hero LIMIT 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return ['title' => 'Welcome', 'subtitle' => 'I am a Developer'];
}

// Get Social Links
function get_social_links()
{
    global $conn;
    $sql = "SELECT * FROM social_links";
    $result = $conn->query($sql);
    return $result;
}
?>