<?php
// admin/dashboard.php
session_start();
require_once '../config/config.php';
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
        header("Location: ../login.php");
        exit();
    }
}

// Get Site Content (Dynamic Text)
function get_site_content($key)
{
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                return $result->fetch_assoc()['content_value'];
            }
        }
    } catch (Exception $e) {
    }
    return $key;
}

// Get Internships
function get_internships()
{
    global $conn;
    try {
        $result = $conn->query("SELECT * FROM internships ORDER BY created_at DESC");
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
    }
    return [];
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
        $new_width = (int) $max_width;
        $new_height = (int) round($max_width / $ratio);
    } else {
        $new_width = (int) $width;
        $new_height = (int) $height;
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
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            break;
        case 'gif':
            $source_image = imagecreatefromgif($source);
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
            $saved = imagepng($thumb, $target_path, 8);
            break;
        case 'gif':
            $saved = imagegif($thumb, $target_path);
            break;
        case 'webp':
            $saved = imagewebp($thumb, $target_path, $quality);
            break;
    }

    imagedestroy($thumb);
    imagedestroy($source_image);

    if ($saved) {
        return ["success" => $new_filename];
    } else {
        return ["error" => "Sorry, there was an error uploading/optimizing your file."];
    }
}

check_login();

// --- AUTO-MIGRATION: Ensure skills table has description and tags columns ---
$columns = $conn->query("SHOW COLUMNS FROM skills");
$existing_cols = [];
if ($columns) {
    while ($col = $columns->fetch_assoc()) {
        $existing_cols[] = $col['Field'];
    }
}
if (!in_array('description', $existing_cols)) {
    $conn->query("ALTER TABLE skills ADD COLUMN description TEXT AFTER percentage");
}
if (!in_array('tags', $existing_cols)) {
    $conn->query("ALTER TABLE skills ADD COLUMN tags VARCHAR(255) AFTER description");
}

// --- AUTO-MIGRATION: Ensure internships & fellowship tables exist ---
$conn->query("
    CREATE TABLE IF NOT EXISTS internships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(100) NOT NULL,
        role VARCHAR(100) NOT NULL,
        duration VARCHAR(50) NOT NULL,
        description TEXT,
        company_logo VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS fellowship_skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        internship_id INT NOT NULL,
        skill_name VARCHAR(100) NOT NULL,
        proficiency ENUM('Beginner','Intermediate','Advanced') DEFAULT 'Beginner',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS fellowship_frameworks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        internship_id INT NOT NULL,
        framework_name VARCHAR(100) NOT NULL,
        category VARCHAR(50),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS fellowship_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        internship_id INT NOT NULL,
        project_name VARCHAR(150) NOT NULL,
        description TEXT,
        tech_used VARCHAR(255),
        github_link VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
    )
");

// --- AUTO-MIGRATION: fellowship_image column ---
$fi_cols = $conn->query("SHOW COLUMNS FROM internships");
$fi_existing = [];
if ($fi_cols) {
    while ($c = $fi_cols->fetch_assoc()) {
        $fi_existing[] = $c['Field'];
    }
}
if (!in_array('fellowship_image', $fi_existing)) {
    $conn->query("ALTER TABLE internships ADD COLUMN fellowship_image VARCHAR(255) AFTER company_logo");
}

// Handle Form Submissions
$message = "";
$error = "";

// --- HERO SECTION UPDATE ---
if (isset($_POST['update_hero'])) {
    $title = clean_input($_POST['hero_title']);
    $subtitle = clean_input($_POST['hero_subtitle']);

    // Check if record exists
    $check = $conn->query("SELECT id FROM hero LIMIT 1");
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE hero SET title=?, subtitle=? WHERE id=1"); // Asuming single record or id=1
        // If id isn't 1, we might need to fetch the id first, but for single row tables, usually acceptable to just update.
        // Better: UPDATE hero SET ... (without WHERE if only 1 row) or limit 1.
        // Let's safe update:
        $id_row = $check->fetch_assoc();
        $id = $id_row['id'];
        $stmt = $conn->prepare("UPDATE hero SET title=?, subtitle=? WHERE id=?");
        $stmt->bind_param("ssi", $title, $subtitle, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO hero (title, subtitle) VALUES (?, ?)");
        $stmt->bind_param("ss", $title, $subtitle);
    }

    if ($stmt->execute()) {
        $message = "Hero section updated successfully.";
    } else {
        $error = "Error updating hero section.";
    }
}

// --- ABOUT SECTION UPDATE ---
if (isset($_POST['update_about'])) {
    $content = clean_input($_POST['about_content']);
    $image_update = "";

    if (!empty($_FILES['profile_image']['name'])) {
        $upload_result = upload_image($_FILES['profile_image']);
        if (isset($upload_result['success'])) {
            $image_path = $upload_result['success'];
            $image_update = ", profile_image='$image_path'";
        } else {
            $error = $upload_result['error'];
        }
    }

    if (empty($error)) {
        $check = $conn->query("SELECT id FROM about LIMIT 1");
        if ($check->num_rows > 0) {
            $id_row = $check->fetch_assoc();
            $id = $id_row['id'];
            $sql = "UPDATE about SET content=? $image_update WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $content, $id);
        } else {
            // Only insert if image is provided or handle default
            $img = isset($image_path) ? $image_path : '';
            $stmt = $conn->prepare("INSERT INTO about (content, profile_image) VALUES (?, ?)");
            $stmt->bind_param("ss", $content, $img);
        }

        if ($stmt->execute()) {
            $message = "About section updated successfully.";
        } else {
            $error = "Error updating about section.";
        }
    }
}

// --- SKILL ADD ---
if (isset($_POST['add_skill'])) {
    $name = clean_input($_POST['skill_name']);
    $percentage = (int) $_POST['skill_percentage'];
    $description = clean_input($_POST['skill_description'] ?? '');
    $tags = clean_input($_POST['skill_tags'] ?? '');

    $stmt = $conn->prepare("INSERT INTO skills (skill_name, percentage, description, tags) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $name, $percentage, $description, $tags);
    if ($stmt->execute()) {
        $message = "Skill added.";
    } else {
        $error = "Error adding skill.";
    }
}

// --- SKILL DELETE ---
if (isset($_GET['delete_skill'])) {
    $id = (int) $_GET['delete_skill'];
    $conn->query("DELETE FROM skills WHERE id=$id");
    $message = "Skill deleted.";
}

// --- SKILL UPDATE ---
if (isset($_POST['update_skill'])) {
    $id = (int) $_POST['edit_skill_id'];
    $name = clean_input($_POST['edit_skill_name']);
    $pct = (int) $_POST['edit_skill_percentage'];
    $desc = clean_input($_POST['edit_skill_description'] ?? '');
    $tags = clean_input($_POST['edit_skill_tags'] ?? '');
    $stmt = $conn->prepare("UPDATE skills SET skill_name=?, percentage=?, description=?, tags=? WHERE id=?");
    $stmt->bind_param("sissi", $name, $pct, $desc, $tags, $id);
    if ($stmt->execute()) {
        $message = "Skill updated.";
    } else {
        $error = "Error updating skill.";
    }
}

// --- PROJECT ADD ---
if (isset($_POST['add_project'])) {
    $title = clean_input($_POST['project_title']);
    $desc = clean_input($_POST['project_desc']);
    $tech = clean_input($_POST['project_tech']);
    $github = clean_input($_POST['project_github']);
    $demo = clean_input($_POST['project_demo']);

    $image_path = "";
    if (!empty($_FILES['project_image']['name'])) {
        $upload_result = upload_image($_FILES['project_image']);
        if (isset($upload_result['success'])) {
            $image_path = $upload_result['success'];
        } else {
            $error = $upload_result['error'];
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO projects (title, description, tech_stack, github_link, demo_link, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $title, $desc, $tech, $github, $demo, $image_path);
        if ($stmt->execute()) {
            $message = "Project added.";
        } else {
            $error = "Error adding project.";
        }
    }
}

// --- PROJECT DELETE ---
if (isset($_GET['delete_project'])) {
    $id = (int) $_GET['delete_project'];
    $conn->query("DELETE FROM projects WHERE id=$id");
    $message = "Project deleted.";
}

// --- PROJECT UPDATE ---
if (isset($_POST['update_project'])) {
    $id = (int) $_POST['edit_project_id'];
    $title = clean_input($_POST['edit_project_title']);
    $desc = clean_input($_POST['edit_project_desc']);
    $tech = clean_input($_POST['edit_project_tech']);
    $github = clean_input($_POST['edit_project_github']);
    $demo = clean_input($_POST['edit_project_demo']);
    $img_sql = '';
    if (!empty($_FILES['edit_project_image']['name'])) {
        $up = upload_image($_FILES['edit_project_image']);
        if (isset($up['success'])) {
            $img_sql = ", image='" . $conn->real_escape_string($up['success']) . "'";
        } else {
            $error = $up['error'];
        }
    }
    if (empty($error)) {
        $conn->query("UPDATE projects SET title='$title', description='$desc', tech_stack='$tech', github_link='$github', demo_link='$demo'$img_sql WHERE id=$id");
        $message = "Project updated.";
    }
}

// --- SITE CONTENT UPDATE ---
if (isset($_POST['update_content'])) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'content_') === 0) {
            $content_key = substr($key, 8); // Remove 'content_' prefix
            $clean_value = clean_input($value);

            $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = ?");
            $stmt->bind_param("ss", $clean_value, $content_key);
            $stmt->execute();
        }
    }
    $message = "Site content updated.";
}

// --- FELLOWSHIP / INTERNSHIP ADD ---
if (isset($_POST['add_internship'])) {
    $company = clean_input($_POST['company_name']);
    $role = clean_input($_POST['role']);
    $duration = clean_input($_POST['duration']);
    $desc = clean_input($_POST['description']);
    $logo_path = '';
    $fi_path = '';

    if (!empty($_FILES['company_logo']['name'])) {
        $upload_result = upload_image($_FILES['company_logo']);
        if (isset($upload_result['success'])) {
            $logo_path = $upload_result['success'];
        } else {
            $error = $upload_result['error'];
        }
    }

    if (empty($error) && !empty($_FILES['fellowship_image']['name'])) {
        $upload_result2 = upload_image($_FILES['fellowship_image']);
        if (isset($upload_result2['success'])) {
            $fi_path = $upload_result2['success'];
        } else {
            $error = $upload_result2['error'];
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO internships (company_name, role, duration, description, company_logo, fellowship_image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $company, $role, $duration, $desc, $logo_path, $fi_path);
        if ($stmt->execute()) {
            $message = "Fellowship entry added.";
        } else {
            $error = "Error adding fellowship entry.";
        }
    }
}

// --- FELLOWSHIP DELETE ---
if (isset($_GET['delete_internship'])) {
    $id = (int) $_GET['delete_internship'];
    $conn->query("DELETE FROM internships WHERE id=$id");
    $message = "Fellowship entry deleted.";
}

// --- FELLOWSHIP UPDATE ---
if (isset($_POST['update_internship'])) {
    $id = (int) $_POST['edit_intern_id'];
    $company = clean_input($_POST['edit_company_name']);
    $role = clean_input($_POST['edit_role']);
    $duration = clean_input($_POST['edit_duration']);
    $desc = clean_input($_POST['edit_description']);
    $logo_sql = $fi_sql = '';
    if (!empty($_FILES['edit_company_logo']['name'])) {
        $up = upload_image($_FILES['edit_company_logo']);
        if (isset($up['success'])) {
            $logo_sql = ", company_logo='" . $conn->real_escape_string($up['success']) . "'";
        } else {
            $error = $up['error'];
        }
    }
    if (empty($error) && !empty($_FILES['edit_fellowship_image']['name'])) {
        $up2 = upload_image($_FILES['edit_fellowship_image']);
        if (isset($up2['success'])) {
            $fi_sql = ", fellowship_image='" . $conn->real_escape_string($up2['success']) . "'";
        } else {
            $error = $up2['error'];
        }
    }
    if (empty($error)) {
        $conn->query("UPDATE internships SET company_name='$company', role='$role', duration='$duration', description='$desc'$logo_sql$fi_sql WHERE id=$id");
        $message = "Fellowship entry updated.";
    }
}

// --- FELLOWSHIP SKILL ADD ---
if (isset($_POST['add_fellowship_skill'])) {
    $iid = (int) $_POST['fellowship_internship_id'];
    $name = clean_input($_POST['fs_skill_name']);
    $prof = clean_input($_POST['fs_proficiency']);
    $desc = clean_input($_POST['fs_description']);
    $stmt = $conn->prepare("INSERT INTO fellowship_skills (internship_id, skill_name, proficiency, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $iid, $name, $prof, $desc);
    if ($stmt->execute()) {
        $message = "Skill added to fellowship.";
    } else {
        $error = "Error adding skill.";
    }
}

// --- FELLOWSHIP SKILL DELETE ---
if (isset($_GET['delete_fs'])) {
    $id = (int) $_GET['delete_fs'];
    $conn->query("DELETE FROM fellowship_skills WHERE id=$id");
    $message = "Skill removed from fellowship.";
}

// --- FELLOWSHIP SKILL UPDATE ---
if (isset($_POST['update_fellowship_skill'])) {
    $id = (int) $_POST['edit_fs_id'];
    $name = clean_input($_POST['edit_fs_skill_name']);
    $prof = clean_input($_POST['edit_fs_proficiency']);
    $desc = clean_input($_POST['edit_fs_description']);
    $stmt = $conn->prepare("UPDATE fellowship_skills SET skill_name=?, proficiency=?, description=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $prof, $desc, $id);
    if ($stmt->execute()) {
        $message = "Fellowship skill updated.";
    } else {
        $error = "Error updating fellowship skill.";
    }
}

// --- FELLOWSHIP FRAMEWORK ADD ---
if (isset($_POST['add_fellowship_fw'])) {
    $iid = (int) $_POST['fw_internship_id'];
    $name = clean_input($_POST['fw_name']);
    $cat = clean_input($_POST['fw_category']);
    $desc = clean_input($_POST['fw_description']);
    $stmt = $conn->prepare("INSERT INTO fellowship_frameworks (internship_id, framework_name, category, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $iid, $name, $cat, $desc);
    if ($stmt->execute()) {
        $message = "Framework added to fellowship.";
    } else {
        $error = "Error adding framework.";
    }
}

// --- FELLOWSHIP FRAMEWORK DELETE ---
if (isset($_GET['delete_fw'])) {
    $id = (int) $_GET['delete_fw'];
    $conn->query("DELETE FROM fellowship_frameworks WHERE id=$id");
    $message = "Framework removed from fellowship.";
}

// --- FELLOWSHIP FRAMEWORK UPDATE ---
if (isset($_POST['update_fellowship_fw'])) {
    $id = (int) $_POST['edit_fw_id'];
    $name = clean_input($_POST['edit_fw_name']);
    $cat = clean_input($_POST['edit_fw_category']);
    $desc = clean_input($_POST['edit_fw_description']);
    $stmt = $conn->prepare("UPDATE fellowship_frameworks SET framework_name=?, category=?, description=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $cat, $desc, $id);
    if ($stmt->execute()) {
        $message = "Fellowship framework updated.";
    } else {
        $error = "Error updating fellowship framework.";
    }
}

// --- FELLOWSHIP PROJECT ADD ---
if (isset($_POST['add_fellowship_proj'])) {
    $iid = (int) $_POST['proj_internship_id'];
    $name = clean_input($_POST['proj_name']);
    $desc = clean_input($_POST['proj_description']);
    $tech = clean_input($_POST['proj_tech_used']);
    $gh = clean_input($_POST['proj_github']);
    $stmt = $conn->prepare("INSERT INTO fellowship_projects (internship_id, project_name, description, tech_used, github_link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $iid, $name, $desc, $tech, $gh);
    if ($stmt->execute()) {
        $message = "Project added to fellowship.";
    } else {
        $error = "Error adding project.";
    }
}

// --- FELLOWSHIP PROJECT DELETE ---
if (isset($_GET['delete_fproj'])) {
    $id = (int) $_GET['delete_fproj'];
    $conn->query("DELETE FROM fellowship_projects WHERE id=$id");
    $message = "Project removed from fellowship.";
}

// --- FELLOWSHIP PROJECT UPDATE ---
if (isset($_POST['update_fellowship_proj'])) {
    $id = (int) $_POST['edit_fproj_id'];
    $name = clean_input($_POST['edit_proj_name']);
    $desc = clean_input($_POST['edit_proj_description']);
    $tech = clean_input($_POST['edit_proj_tech_used']);
    $gh = clean_input($_POST['edit_proj_github']);
    $stmt = $conn->prepare("UPDATE fellowship_projects SET project_name=?, description=?, tech_used=?, github_link=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $desc, $tech, $gh, $id);
    if ($stmt->execute()) {
        $message = "Fellowship project updated.";
    } else {
        $error = "Error updating fellowship project.";
    }
}

// --- SOCIAL LINK ADD ---
if (isset($_POST['add_social'])) {
    $platform = clean_input($_POST['social_platform']);
    $url = clean_input($_POST['social_url']);

    $stmt = $conn->prepare("INSERT INTO social_links (platform, url) VALUES (?, ?)");
    $stmt->bind_param("ss", $platform, $url);
    if ($stmt->execute()) {
        $message = "Social link added.";
    } else {
        $error = "Error adding social link.";
    }
}

// --- SOCIAL LINK DELETE ---
if (isset($_GET['delete_social'])) {
    $id = (int) $_GET['delete_social'];
    $conn->query("DELETE FROM social_links WHERE id=$id");
    $message = "Social link deleted.";
}

// --- SOCIAL LINK UPDATE ---
if (isset($_POST['update_social'])) {
    $id = (int) $_POST['edit_social_id'];
    $platform = clean_input($_POST['edit_social_platform']);
    $url = clean_input($_POST['edit_social_url']);
    $icon = clean_input($_POST['edit_social_icon'] ?? '');
    $stmt = $conn->prepare("UPDATE social_links SET platform=?, url=?, icon=? WHERE id=?");
    $stmt->bind_param("sssi", $platform, $url, $icon, $id);
    if ($stmt->execute()) {
        $message = "Social link updated.";
    } else {
        $error = "Error updating social link.";
    }
}

// --- PROFILE UPDATE ---
if (isset($_POST['update_admin_profile'])) {
    $new_username = clean_input($_POST['new_username']);
    $new_password = $_POST['new_password']; // Plain text as requested
    $id = $_SESSION['admin_id'];

    $updates = [];
    $types = "";
    $params = [];

    if (!empty($new_username)) {
        $updates[] = "username=?";
        $types .= "s";
        $params[] = $new_username;
        $_SESSION['admin_username'] = $new_username;
    }

    if (!empty($new_password)) {
        $updates[] = "password=?";
        $types .= "s";
        $params[] = $new_password;
    }

    if (!empty($updates)) {
        $sql = "UPDATE admin_users SET " . implode(", ", $updates) . " WHERE id=?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
        } else {
            $error = "Error updating profile.";
        }
    }
}


// --- FETCH DATA FOR VIEW ---
$hero = $conn->query("SELECT * FROM hero LIMIT 1")->fetch_assoc();
$about = $conn->query("SELECT * FROM about LIMIT 1")->fetch_assoc();
$skills = $conn->query("SELECT * FROM skills");
$projects = $conn->query("SELECT * FROM projects");
$socials = $conn->query("SELECT * FROM social_links");

// Safe Fetch for Internships
$internships = [];
try {
    $intern_check = $conn->query("SHOW TABLES LIKE 'internships'");
    if ($intern_check && $intern_check->num_rows > 0) {
        $internships = get_internships();
    }
} catch (Exception $e) { /* Ignore */
}

// Safe Fetch for Site Content
$site_content = [];
try {
    $content_check = $conn->query("SHOW TABLES LIKE 'site_content'");
    if ($content_check && $content_check->num_rows > 0) {
        $site_content_result = $conn->query("SELECT * FROM site_content");
        if ($site_content_result) {
            while ($row = $site_content_result->fetch_assoc()) {
                $site_content[$row['content_key']] = $row;
            }
        }
    }
} catch (Exception $e) { /* Ignore */
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'hero';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-circle"></i> Admin</h3>
        </div>
        <ul class="nav-links-admin">
            <li><a href="?tab=hero" class="<?php echo $active_tab == 'hero' ? 'active' : ''; ?>"><i
                        class="fas fa-home"></i> Hero</a></li>
            <li><a href="?tab=about" class="<?php echo $active_tab == 'about' ? 'active' : ''; ?>"><i
                        class="fas fa-user"></i> About</a></li>
            <li><a href="?tab=skills" class="<?php echo $active_tab == 'skills' ? 'active' : ''; ?>"><i
                        class="fas fa-code"></i> Skills</a></li>
            <li><a href="?tab=projects" class="<?php echo $active_tab == 'projects' ? 'active' : ''; ?>"><i
                        class="fas fa-briefcase"></i> Projects</a></li>
            <li><a href="?tab=internships" class="<?php echo $active_tab == 'internships' ? 'active' : ''; ?>"><i
                        class="fas fa-graduation-cap"></i> Internships</a></li>
            <li><a href="?tab=settings" class="<?php echo $active_tab == 'settings' ? 'active' : ''; ?>"><i
                        class="fas fa-cogs"></i> General Settings</a></li>
            <li><a href="?tab=social" class="<?php echo $active_tab == 'social' ? 'active' : ''; ?>"><i
                        class="fas fa-share-alt"></i> Socials</a></li>
            <li><a href="?tab=profile" class="<?php echo $active_tab == 'profile' ? 'active' : ''; ?>"><i
                        class="fas fa-id-card"></i> Profile</a></li>
            <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
            <li><a href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">

        <?php if ($message): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'hero'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-home"></i> Manage Hero Section</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Headline</label>
                        <input type="text" name="hero_title"
                            value="<?php echo isset($hero['title']) ? $hero['title'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Subtitle</label>
                        <input type="text" name="hero_subtitle"
                            value="<?php echo isset($hero['subtitle']) ? $hero['subtitle'] : ''; ?>" required>
                    </div>
                    <button type="submit" name="update_hero" class="btn-primary">Update Hero</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'about'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-user"></i> Manage About Section</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>About Content</label>
                        <textarea name="about_content" rows="6"
                            required><?php echo isset($about['content']) ? $about['content'] : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Profile Image</label>
                        <input type="file" name="profile_image">
                        <?php if (isset($about['profile_image'])): ?>
                            <div style="margin-top: 1rem;">
                                <p style="margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-light);">Current Image:
                                </p>
                                <img src="../uploads/<?php echo $about['profile_image']; ?>"
                                    style="height: 100px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="update_about" class="btn-primary">Update About</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'skills'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-code"></i> Manage Skills</h2>
                <form method="POST">
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
                        <div class="form-group" style="flex: 2; min-width: 200px;">
                            <label>Skill Name</label>
                            <input type="text" name="skill_name" placeholder="e.g. React" required>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 120px;">
                            <label>Percentage (%)</label>
                            <input type="number" name="skill_percentage" min="0" max="100" placeholder="85" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label>Description</label>
                        <textarea name="skill_description" rows="2"
                            placeholder="Brief description of your expertise..."></textarea>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 2; min-width: 200px;">
                            <label>Tags <span style="font-weight: normal; color: var(--text-light);">(comma
                                    separated)</span></label>
                            <input type="text" name="skill_tags" placeholder="e.g. Framework, CMS, Backend">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_skill" class="btn-primary">Add Skill</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Percentage</th>
                                <th>Description</th>
                                <th>Tags</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($skill = $skills->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div
                                                style="width: 100px; height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                                                <div
                                                    style="width: <?php echo $skill['percentage']; ?>%; height: 100%; background: var(--accent-dark);">
                                                </div>
                                            </div>
                                            <span style="font-size: 0.9rem;"><?php echo $skill['percentage']; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.85rem; color: var(--text-light); max-width: 200px;">
                                        <?php echo htmlspecialchars($skill['description'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $skill_tags = array_filter(array_map('trim', explode(',', $skill['tags'] ?? '')));
                                        foreach ($skill_tags as $tag) {
                                            echo '<span style="display:inline-block; padding:0.2rem 0.5rem; background:#e0f2fe; color:#0284c7; border-radius:4px; font-size:0.72rem; font-weight:600; margin:0.15rem;">' . htmlspecialchars($tag) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:0.5rem;align-items:center;">
                                            <button type="button" class="btn-sm btn-edit"
                                                onclick="toggleEditRow('sk-<?php echo $skill['id']; ?>')"><i
                                                    class="fas fa-edit"></i></button>
                                            <a href="?tab=skills&delete_skill=<?php echo $skill['id']; ?>"
                                                class="btn-sm btn-danger" onclick="return confirm('Delete this skill?')"><i
                                                    class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Inline Edit Row -->
                                <tr id="sk-<?php echo $skill['id']; ?>" style="display:none;background:#f8fafc;">
                                    <td colspan="5" style="padding:1.2rem;">
                                        <form method="POST"
                                            style="display:grid;grid-template-columns:2fr 1fr 2fr 2fr auto;gap:0.8rem;align-items:flex-end;">
                                            <input type="hidden" name="edit_skill_id" value="<?php echo $skill['id']; ?>">
                                            <div class="form-group" style="margin:0;">
                                                <label style="font-size:0.78rem;">Skill Name</label>
                                                <input type="text" name="edit_skill_name"
                                                    value="<?php echo htmlspecialchars($skill['skill_name']); ?>" required
                                                    style="font-size:0.85rem;">
                                            </div>
                                            <div class="form-group" style="margin:0;">
                                                <label style="font-size:0.78rem;">%</label>
                                                <input type="number" name="edit_skill_percentage" min="0" max="100"
                                                    value="<?php echo $skill['percentage']; ?>" required
                                                    style="font-size:0.85rem;">
                                            </div>
                                            <div class="form-group" style="margin:0;">
                                                <label style="font-size:0.78rem;">Description</label>
                                                <input type="text" name="edit_skill_description"
                                                    value="<?php echo htmlspecialchars($skill['description'] ?? ''); ?>"
                                                    style="font-size:0.85rem;">
                                            </div>
                                            <div class="form-group" style="margin:0;">
                                                <label style="font-size:0.78rem;">Tags</label>
                                                <input type="text" name="edit_skill_tags"
                                                    value="<?php echo htmlspecialchars($skill['tags'] ?? ''); ?>"
                                                    style="font-size:0.85rem;">
                                            </div>
                                            <button type="submit" name="update_skill" class="btn-primary"
                                                style="padding:0.6rem 1rem;font-size:0.85rem;"><i class="fas fa-save"></i>
                                                Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'projects'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-briefcase"></i> Manage Projects</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Project Title</label>
                        <input type="text" name="project_title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="project_desc" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tech Stack (comma separated)</label>
                        <input type="text" name="project_tech" placeholder="PHP, MySQL, JS">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>GitHub Link</label>
                            <input type="text" name="project_github" placeholder="https://github.com/...">
                        </div>
                        <div class="form-group">
                            <label>Live Demo Link</label>
                            <input type="text" name="project_demo" placeholder="https://...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Project Image</label>
                        <input type="file" name="project_image" required>
                    </div>
                    <button type="submit" name="add_project" class="btn-primary">Add Project</button>
                </form>

                <div style="margin-top: 3rem;">
                    <h3>Existing Projects</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Tech Stack</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($proj = $projects->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?php echo htmlspecialchars($proj['title']); ?></td>
                                        <td>
                                            <?php
                                            $techs = explode(',', $proj['tech_stack']);
                                            foreach ($techs as $tech) {
                                                echo '<span style="display: inline-block; background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px;">' . trim($tech) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:0.5rem;align-items:center;">
                                                <button type="button" class="btn-sm btn-edit"
                                                    onclick="toggleEditRow('pr-<?php echo $proj['id']; ?>')"><i
                                                        class="fas fa-edit"></i></button>
                                                <a href="?tab=projects&delete_project=<?php echo $proj['id']; ?>"
                                                    class="btn-sm btn-danger"
                                                    onclick="return confirm('Delete this project?')"><i
                                                        class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Inline Edit Row -->
                                    <tr id="pr-<?php echo $proj['id']; ?>" style="display:none;background:#f8fafc;">
                                        <td colspan="3" style="padding:1.2rem;">
                                            <form method="POST" enctype="multipart/form-data"
                                                style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                                                <input type="hidden" name="edit_project_id" value="<?php echo $proj['id']; ?>">
                                                <div class="form-group" style="margin:0;">
                                                    <label style="font-size:0.78rem;">Title</label>
                                                    <input type="text" name="edit_project_title"
                                                        value="<?php echo htmlspecialchars($proj['title']); ?>" required
                                                        style="font-size:0.85rem;">
                                                </div>
                                                <div class="form-group" style="margin:0;">
                                                    <label style="font-size:0.78rem;">Tech Stack</label>
                                                    <input type="text" name="edit_project_tech"
                                                        value="<?php echo htmlspecialchars($proj['tech_stack']); ?>"
                                                        style="font-size:0.85rem;">
                                                </div>
                                                <div class="form-group" style="grid-column:1/-1;margin:0;">
                                                    <label style="font-size:0.78rem;">Description</label>
                                                    <textarea name="edit_project_desc" rows="2"
                                                        style="font-size:0.85rem;"><?php echo htmlspecialchars($proj['description']); ?></textarea>
                                                </div>
                                                <div class="form-group" style="margin:0;">
                                                    <label style="font-size:0.78rem;">GitHub Link</label>
                                                    <input type="text" name="edit_project_github"
                                                        value="<?php echo htmlspecialchars($proj['github_link']); ?>"
                                                        style="font-size:0.85rem;">
                                                </div>
                                                <div class="form-group" style="margin:0;">
                                                    <label style="font-size:0.78rem;">Demo Link</label>
                                                    <input type="text" name="edit_project_demo"
                                                        value="<?php echo htmlspecialchars($proj['demo_link']); ?>"
                                                        style="font-size:0.85rem;">
                                                </div>
                                                <div class="form-group" style="grid-column:1/-1;margin:0;">
                                                    <label style="font-size:0.78rem;">New Image <small
                                                            style="font-weight:normal;">(leave blank to keep
                                                            current)</small></label>
                                                    <input type="file" name="edit_project_image" accept="image/*"
                                                        style="font-size:0.85rem;">
                                                </div>
                                                <div style="grid-column:1/-1;">
                                                    <button type="submit" name="update_project" class="btn-primary"
                                                        style="padding:0.6rem 1rem;font-size:0.85rem;"><i
                                                            class="fas fa-save"></i> Save Changes</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'internships'): ?>
            <?php
            // Fetch fellowship data grouped by internship
            $all_fs = $conn->query("SELECT * FROM fellowship_skills ORDER BY internship_id, created_at");
            $all_fw = $conn->query("SELECT * FROM fellowship_frameworks ORDER BY internship_id, created_at");
            $all_fp = $conn->query("SELECT * FROM fellowship_projects ORDER BY internship_id, created_at");
            $fs_map = $fw_map = $fp_map = [];
            if ($all_fs) {
                foreach ($all_fs->fetch_all(MYSQLI_ASSOC) as $r) {
                    $fs_map[$r['internship_id']][] = $r;
                }
            }
            if ($all_fw) {
                foreach ($all_fw->fetch_all(MYSQLI_ASSOC) as $r) {
                    $fw_map[$r['internship_id']][] = $r;
                }
            }
            if ($all_fp) {
                foreach ($all_fp->fetch_all(MYSQLI_ASSOC) as $r) {
                    $fp_map[$r['internship_id']][] = $r;
                }
            }
            ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-graduation-cap"></i> Manage Fellowship & Experience</h2>

                <!-- ADD FELLOWSHIP FORM -->
                <div
                    style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1.8rem; margin-bottom:2rem;">
                    <h3 style="margin-bottom:1.2rem; font-size:1.1rem;"><i class="fas fa-plus-circle"
                            style="color:var(--accent-dark);"></i> Add New Fellowship / Internship</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Company / Organization Name</label>
                                <input type="text" name="company_name" placeholder="e.g. Google, NASSCOM" required>
                            </div>
                            <div class="form-group">
                                <label>Role / Position</label>
                                <input type="text" name="role" placeholder="e.g. Software Engineering Fellow" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Duration</label>
                            <input type="text" name="duration" placeholder="e.g. Jun 2024 – Aug 2024" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"
                                placeholder="Brief overview of the fellowship..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Company Logo <small style="font-weight:normal;color:var(--text-light);">(optional, shown
                                    in row header)</small></label>
                            <input type="file" name="company_logo" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Fellowship Image <small style="font-weight:normal;color:var(--text-light);">(hero image
                                    shown on front-end, optional)</small></label>
                            <input type="file" name="fellowship_image" accept="image/*">
                        </div>
                        <button type="submit" name="add_internship" class="btn-primary"><i class="fas fa-plus"></i> Add
                            Fellowship</button>
                    </form>
                </div>

                <!-- PER-FELLOWSHIP DETAIL MANAGER -->
                <?php foreach ($internships as $intern): ?>
                    <div style="border:2px solid #e2e8f0; border-radius:20px; margin-bottom:2.5rem; overflow:hidden;">
                        <!-- Header Bar -->
                        <div
                            style="background:linear-gradient(135deg,#1e293b,#0f172a); padding:1.2rem 1.8rem; display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; align-items:center; gap:0.8rem;">
                                <?php if ($intern['company_logo']): ?>
                                    <img src="../uploads/<?php echo $intern['company_logo']; ?>"
                                        style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:2px solid rgba(255,255,255,0.2);">
                                <?php else: ?>
                                    <div
                                        style="width:38px;height:38px;background:rgba(255,255,255,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-building" style="color:#94a3b8;"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="color:#fff;font-weight:700;font-size:1.05rem;">
                                        <?php echo htmlspecialchars($intern['company_name']); ?>
                                    </div>
                                    <div style="color:#94a3b8;font-size:0.82rem;">
                                        <?php echo htmlspecialchars($intern['role']); ?> &bull;
                                        <?php echo htmlspecialchars($intern['duration']); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;gap:0.6rem;">
                                <button type="button" class="btn-sm" onclick="toggleEditRow('fi-<?php echo $intern['id']; ?>')"
                                    style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.3);"><i
                                        class="fas fa-edit"></i> Edit</button>
                                <a href="?tab=internships&delete_internship=<?php echo $intern['id']; ?>"
                                    class="btn-sm btn-danger"
                                    onclick="return confirm('Delete this entire fellowship and all its data?')"><i
                                        class="fas fa-trash"></i> Delete</a>
                            </div>
                        </div>
                        <!-- Inline Edit Panel -->
                        <div id="fi-<?php echo $intern['id']; ?>"
                            style="display:none;background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:1.5rem;">
                            <form method="POST" enctype="multipart/form-data"
                                style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                <input type="hidden" name="edit_intern_id" value="<?php echo $intern['id']; ?>">
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.8rem;">Company Name</label>
                                    <input type="text" name="edit_company_name"
                                        value="<?php echo htmlspecialchars($intern['company_name']); ?>" required
                                        style="font-size:0.85rem;">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.8rem;">Role</label>
                                    <input type="text" name="edit_role" value="<?php echo htmlspecialchars($intern['role']); ?>"
                                        required style="font-size:0.85rem;">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.8rem;">Duration</label>
                                    <input type="text" name="edit_duration"
                                        value="<?php echo htmlspecialchars($intern['duration']); ?>" style="font-size:0.85rem;">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label style="font-size:0.8rem;">New Company Logo <small
                                            style="font-weight:normal;">(optional)</small></label>
                                    <input type="file" name="edit_company_logo" accept="image/*" style="font-size:0.85rem;">
                                </div>
                                <div class="form-group" style="grid-column:1/-1;margin:0;">
                                    <label style="font-size:0.8rem;">Description</label>
                                    <textarea name="edit_description" rows="3"
                                        style="font-size:0.85rem;"><?php echo htmlspecialchars($intern['description']); ?></textarea>
                                </div>
                                <div class="form-group" style="grid-column:1/-1;margin:0;">
                                    <label style="font-size:0.8rem;">New Fellowship Image <small
                                            style="font-weight:normal;">(optional)</small></label>
                                    <input type="file" name="edit_fellowship_image" accept="image/*" style="font-size:0.85rem;">
                                </div>
                                <div style="grid-column:1/-1;">
                                    <button type="submit" name="update_internship" class="btn-primary"
                                        style="font-size:0.85rem;"><i class="fas fa-save"></i> Save Changes</button>
                                </div>
                            </form>
                        </div>

                        <!-- Sub-Tabs -->
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid #e2e8f0;">
                            <?php
                            $tab_styles = [
                                'skills' => ['icon' => 'fa-bolt', 'label' => 'Skills Learned', 'color' => '#059669', 'bg' => '#ecfdf5'],
                                'frameworks' => ['icon' => 'fa-layer-group', 'label' => 'Frameworks', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
                                'projects' => ['icon' => 'fa-code-branch', 'label' => 'Projects Built', 'color' => '#0284c7', 'bg' => '#eff6ff'],
                            ];
                            foreach ($tab_styles as $tkey => $tval): ?>
                                <div
                                    style="padding:1rem;text-align:center;border-right:1px solid #e2e8f0;background:#fafafa;font-weight:600;font-size:0.88rem;color:<?php echo $tval['color']; ?>;">
                                    <i class="fas <?php echo $tval['icon']; ?>"
                                        style="margin-right:0.4rem;"></i><?php echo $tval['label']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;">

                            <!-- SKILLS COLUMN -->
                            <div style="padding:1.5rem;border-right:1px solid #e2e8f0;">
                                <form method="POST" style="margin-bottom:1rem;">
                                    <input type="hidden" name="fellowship_internship_id" value="<?php echo $intern['id']; ?>">
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="fs_skill_name" placeholder="Skill name (e.g. Python)" required
                                            style="font-size:0.88rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <select name="fs_proficiency"
                                            style="width:100%;padding:0.55rem 0.8rem;border:1px solid #e2e8f0;border-radius:8px;font-size:0.87rem;background:#f8fafc;color:var(--text-color);">
                                            <option value="Beginner">Beginner</option>
                                            <option value="Intermediate">Intermediate</option>
                                            <option value="Advanced">Advanced</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="fs_description" placeholder="Brief description"
                                            style="font-size:0.88rem;">
                                    </div>
                                    <button type="submit" name="add_fellowship_skill"
                                        style="width:100%;padding:0.5rem;background:#ecfdf5;color:#059669;border:1px solid #6ee7b7;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;"><i
                                            class="fas fa-plus"></i> Add Skill</button>
                                </form>
                                <?php if (!empty($fs_map[$intern['id']])): ?>
                                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                        <?php foreach ($fs_map[$intern['id']] as $fs): ?>
                                            <div
                                                style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.5rem 0.8rem;display:flex;justify-content:space-between;align-items:center;">
                                                <div>
                                                    <span
                                                        style="font-weight:600;font-size:0.85rem;color:#065f46;"><?php echo htmlspecialchars($fs['skill_name']); ?></span>
                                                    <span
                                                        style="font-size:0.72rem;background:#d1fae5;color:#047857;padding:0.1rem 0.4rem;border-radius:4px;margin-left:0.4rem;"><?php echo $fs['proficiency']; ?></span>
                                                </div>
                                                <a href="?tab=internships&delete_fs=<?php echo $fs['id']; ?>"
                                                    style="color:#ef4444;font-size:0.8rem;" onclick="return confirm('Remove skill?')"><i
                                                        class="fas fa-times"></i></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size:0.82rem;color:#94a3b8;text-align:center;">No skills added yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- FRAMEWORKS COLUMN -->
                            <div style="padding:1.5rem;border-right:1px solid #e2e8f0;">
                                <form method="POST" style="margin-bottom:1rem;">
                                    <input type="hidden" name="fw_internship_id" value="<?php echo $intern['id']; ?>">
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="fw_name" placeholder="Framework (e.g. Django)" required
                                            style="font-size:0.88rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <select name="fw_category"
                                            style="width:100%;padding:0.55rem 0.8rem;border:1px solid #e2e8f0;border-radius:8px;font-size:0.87rem;background:#f8fafc;color:var(--text-color);">
                                            <option value="Frontend">Frontend</option>
                                            <option value="Backend">Backend</option>
                                            <option value="Mobile">Mobile</option>
                                            <option value="DevOps">DevOps</option>
                                            <option value="Database">Database</option>
                                            <option value="AI/ML">AI/ML</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="fw_description" placeholder="Brief description"
                                            style="font-size:0.88rem;">
                                    </div>
                                    <button type="submit" name="add_fellowship_fw"
                                        style="width:100%;padding:0.5rem;background:#f5f3ff;color:#7c3aed;border:1px solid #c4b5fd;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;"><i
                                            class="fas fa-plus"></i> Add Framework</button>
                                </form>
                                <?php if (!empty($fw_map[$intern['id']])): ?>
                                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                        <?php foreach ($fw_map[$intern['id']] as $fw): ?>
                                            <div
                                                style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:0.5rem 0.8rem;display:flex;justify-content:space-between;align-items:center;">
                                                <div>
                                                    <span
                                                        style="font-weight:600;font-size:0.85rem;color:#5b21b6;"><?php echo htmlspecialchars($fw['framework_name']); ?></span>
                                                    <span
                                                        style="font-size:0.72rem;background:#ede9fe;color:#6d28d9;padding:0.1rem 0.4rem;border-radius:4px;margin-left:0.4rem;"><?php echo $fw['category']; ?></span>
                                                </div>
                                                <a href="?tab=internships&delete_fw=<?php echo $fw['id']; ?>"
                                                    style="color:#ef4444;font-size:0.8rem;"
                                                    onclick="return confirm('Remove framework?')"><i class="fas fa-times"></i></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size:0.82rem;color:#94a3b8;text-align:center;">No frameworks added yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- PROJECTS COLUMN -->
                            <div style="padding:1.5rem;">
                                <form method="POST" style="margin-bottom:1rem;">
                                    <input type="hidden" name="proj_internship_id" value="<?php echo $intern['id']; ?>">
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="proj_name" placeholder="Project name" required
                                            style="font-size:0.88rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="proj_description" placeholder="What you built"
                                            style="font-size:0.88rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="proj_tech_used" placeholder="Tech used (PHP, MySQL...)"
                                            style="font-size:0.88rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0.6rem;">
                                        <input type="text" name="proj_github" placeholder="GitHub link (optional)"
                                            style="font-size:0.88rem;">
                                    </div>
                                    <button type="submit" name="add_fellowship_proj"
                                        style="width:100%;padding:0.5rem;background:#eff6ff;color:#0284c7;border:1px solid #bae6fd;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;"><i
                                            class="fas fa-plus"></i> Add Project</button>
                                </form>
                                <?php if (!empty($fp_map[$intern['id']])): ?>
                                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                        <?php foreach ($fp_map[$intern['id']] as $fp): ?>
                                            <div
                                                style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:0.5rem 0.8rem;display:flex;justify-content:space-between;align-items:center;">
                                                <div>
                                                    <span
                                                        style="font-weight:600;font-size:0.85rem;color:#0369a1;"><?php echo htmlspecialchars($fp['project_name']); ?></span>
                                                    <?php if ($fp['github_link']): ?>
                                                        <a href="<?php echo htmlspecialchars($fp['github_link']); ?>" target="_blank"
                                                            style="font-size:0.72rem;margin-left:0.4rem;color:#0ea5e9;"><i
                                                                class="fab fa-github"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="?tab=internships&delete_fproj=<?php echo $fp['id']; ?>"
                                                    style="color:#ef4444;font-size:0.8rem;"
                                                    onclick="return confirm('Remove project?')"><i class="fas fa-times"></i></a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size:0.82rem;color:#94a3b8;text-align:center;">No projects added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($internships)): ?>
                    <div style="text-align:center;padding:3rem;color:var(--text-light);">
                        <i class="fas fa-graduation-cap"
                            style="font-size:2.5rem;margin-bottom:1rem;display:block;opacity:0.3;"></i>
                        No fellowship entries yet. Add one above!
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <?php if ($active_tab == 'settings'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-cogs"></i> General Site Settings</h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Update global text and labels across the website.
                </p>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($site_content as $key => $data): ?>
                            <div class="form-group">
                                <label style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $key); ?>
                                    <small
                                        style="font-weight: normal; color: var(--text-light);">(<?php echo $data['description']; ?>)</small>
                                </label>
                                <input type="text" name="content_<?php echo $key; ?>"
                                    value="<?php echo htmlspecialchars($data['content_value']); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 2rem;">
                        <button type="submit" name="update_content" class="btn-primary">Save All Settings</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'social'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-share-alt"></i> Manage Social Links</h2>
                <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 150px;">
                        <label>Platform</label>
                        <input type="text" name="social_platform" placeholder="LinkedIn" required>
                    </div>
                    <div class="form-group" style="flex: 2; min-width: 200px;">
                        <label>URL</label>
                        <input type="text" name="social_url" placeholder="https://..." required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_social" class="btn-primary">Add Link</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>URL</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($social = $socials->fetch_assoc()): ?>
                                    <td style="text-transform: capitalize; font-weight: 500;">
                                        <i class="fab fa-<?php echo strtolower($social['platform']); ?>"
                                            style="margin-right: 8px; color: var(--text-light);"></i>
                                        <?php echo htmlspecialchars($social['platform']); ?>
                                    </td>
                                    <td><a href="<?php echo $social['url']; ?>" target="_blank"
                                            style="color: var(--accent-dark); text-decoration: none;"><?php echo htmlspecialchars($social['url']); ?></a>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:0.5rem;">
                                            <button type="button" class="btn-sm btn-edit" onclick="toggleEditRow('sl-<?php echo $social['id']; ?>')"><i class="fas fa-edit"></i></button>
                                            <a href="?tab=social&delete_social=<?php echo $social['id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this link?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Inline Edit Row -->
                                <tr id="sl-<?php echo $social['id']; ?>" style="display:none;background:#f8fafc;">
                                    <td colspan="3" style="padding:1rem;">
                                        <form method="POST" style="display:flex;gap:0.8rem;flex-wrap:wrap;align-items:flex-end;">
                                            <input type="hidden" name="edit_social_id" value="<?php echo $social['id']; ?>">
                                            <div class="form-group" style="flex:1;min-width:120px;margin:0;">
                                                <label style="font-size:0.78rem;">Platform</label>
                                                <input type="text" name="edit_social_platform" value="<?php echo htmlspecialchars($social['platform']); ?>" style="font-size:0.85rem;">
                                            </div>
                                            <div class="form-group" style="flex:3;min-width:200px;margin:0;">
                                                <label style="font-size:0.78rem;">URL</label>
                                                <input type="text" name="edit_social_url" value="<?php echo htmlspecialchars($social['url']); ?>" style="font-size:0.85rem;">
                                            </div>
                                            <div class="form-group" style="flex:1;min-width:140px;margin:0;">
                                                <label style="font-size:0.78rem;">Icon Class <small style="font-weight:normal;">(fa-...)</small></label>
                                                <input type="text" name="edit_social_icon" value="<?php echo htmlspecialchars($social['icon'] ?? ''); ?>" placeholder="fa-linkedin" style="font-size:0.85rem;">
                                            </div>
                                            <button type="submit" name="update_social" class="btn-primary" style="padding:0.6rem 1rem;font-size:0.85rem;"><i class="fas fa-save"></i> Save</button>
                                        </form>
                                    </td>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'profile'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-id-card"></i> Manage Admin Profile</h2>
                <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #dbeafe;">
                    <i class="fas fa-info-circle"></i> Update your admin credentials here. Passwords are stored in plain
                    text as requested.
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label>New Username</label>
                        <input type="text" name="new_username" placeholder="Leave blank to keep current"
                            value="<?php echo htmlspecialchars($_SESSION['admin_username']); ?>">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="text" name="new_password" placeholder="Leave blank to keep current">
                    </div>
                    <button type="submit" name="update_admin_profile" class="btn-primary">Update Profile</button>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Fade-in on load
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElems = document.querySelectorAll('.fade-in');
            fadeElems.forEach((el, index) => {
                setTimeout(() => { el.classList.add('visible'); }, index * 100);
            });
        });

        // Toggle inline edit rows
        function toggleEditRow(id) {
            const row = document.getElementById(id);
            if (!row) return;
            const isHidden = row.style.display === 'none' || row.style.display === '';
            row.style.display = isHidden ? 'table-row' : 'none';
            // For block-level panels (fellowship header)
            if (row.tagName !== 'TR') {
                row.style.display = isHidden ? 'block' : 'none';
            }
        }
    </script>
</body>

</html>