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

// --- AUTO-MIGRATION: Ensure site_content table exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS site_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content_key VARCHAR(50) NOT NULL UNIQUE,
        content_value TEXT,
        description VARCHAR(255)
    )
");

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

// --- AUTO-MIGRATION: social_links logo_image ---
$sl_cols = $conn->query("SHOW COLUMNS FROM social_links");
$sl_existing = [];
if ($sl_cols) {
    while ($c = $sl_cols->fetch_assoc())
        $sl_existing[] = $c['Field'];
}
if (!in_array('logo_image', $sl_existing)) {
    $conn->query("ALTER TABLE social_links ADD COLUMN logo_image VARCHAR(255) AFTER url");
}
if (!in_array('icon', $sl_existing) && !in_array('icon_class', $sl_existing)) {
    $conn->query("ALTER TABLE social_links ADD COLUMN icon VARCHAR(50) AFTER logo_image");
}

// --- AUTO-MIGRATION: about extra fields ---
$about_cols = $conn->query("SHOW COLUMNS FROM about");
$about_existing = [];
if ($about_cols) {
    while ($c = $about_cols->fetch_assoc())
        $about_existing[] = $c['Field'];
}
foreach (['job_title VARCHAR(100)', 'location VARCHAR(100)', 'years_exp INT DEFAULT 0', 'cv_url VARCHAR(255)'] as $coldef) {
    $cname = explode(' ', $coldef)[0];
    if (!in_array($cname, $about_existing)) {
        $conn->query("ALTER TABLE about ADD COLUMN $coldef");
    }
}

// --- AUTO-MIGRATION: timeline_entries table ---
$conn->query("
    CREATE TABLE IF NOT EXISTS timeline_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        icon VARCHAR(100) DEFAULT 'fas fa-circle',
        color VARCHAR(20) DEFAULT '#ffd60a',
        avatar_id VARCHAR(80) DEFAULT '',
        display_type ENUM('icon','avatar','image') DEFAULT 'icon',
        start_date DATE NOT NULL,
        end_date DATE DEFAULT NULL,
        link VARCHAR(255) DEFAULT '',
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
// Migration: Add display_type column if missing
$tl_cols = $conn->query("SHOW COLUMNS FROM timeline_entries LIKE 'display_type'");
if ($tl_cols && $tl_cols->num_rows == 0) {
    $conn->query("ALTER TABLE timeline_entries ADD COLUMN display_type ENUM('icon','avatar','image') DEFAULT 'icon' AFTER avatar_id");
}

// --- AUTO-MIGRATION: timeline_images table ---
$conn->query("
    CREATE TABLE IF NOT EXISTS timeline_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timeline_entry_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (timeline_entry_id) REFERENCES timeline_entries(id) ON DELETE CASCADE
    )
");

// --- AUTO-MIGRATION: timeline_avatars table ---
$conn->query("
    CREATE TABLE IF NOT EXISTS timeline_avatars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timeline_entry_id INT NOT NULL,
        avatar_id VARCHAR(80) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (timeline_entry_id) REFERENCES timeline_entries(id) ON DELETE CASCADE
    )
");

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

// --- NAVBAR LOGO UPDATE ---
if (isset($_POST['update_navbar_logo'])) {
    if (!empty($_FILES['navbar_logo_img']['name'])) {
        $up = upload_image($_FILES['navbar_logo_img']);
        if (isset($up['success'])) {
            $img_path = $up['success'];
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value, description) VALUES ('navbar_logo_img', ?, 'Navbar logo image') ON DUPLICATE KEY UPDATE content_value = ?");
            $stmt->bind_param("ss", $img_path, $img_path);
            if ($stmt->execute()) {
                $message = "Navbar logo updated.";
            } else {
                $error = "Error saving navbar logo.";
            }
        } else {
            $error = $up['error'];
        }
    } else {
        $error = "Please select an image file.";
    }
}

// --- ABOUT SECTION UPDATE ---
if (isset($_POST['update_about'])) {
    $content = clean_input($_POST['about_content']);
    $job_title = clean_input($_POST['about_job_title'] ?? '');
    $location = clean_input($_POST['about_location'] ?? '');
    $years_exp = (int) ($_POST['about_years_exp'] ?? 0);
    $cv_url = clean_input($_POST['about_cv_url'] ?? '');
    $image_update = "";

    if (!empty($_FILES['profile_image']['name'])) {
        $upload_result = upload_image($_FILES['profile_image']);
        if (isset($upload_result['success'])) {
            $image_path = $upload_result['success'];
            $image_update = ", profile_image='" . $conn->real_escape_string($image_path) . "'";
        } else {
            $error = $upload_result['error'];
        }
    }

    if (empty($error)) {
        $check = $conn->query("SELECT id FROM about LIMIT 1");
        if ($check->num_rows > 0) {
            $id_row = $check->fetch_assoc();
            $id = $id_row['id'];
            $sql = "UPDATE about SET content=?, job_title=?, location=?, years_exp=?, cv_url=? $image_update WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssisi", $content, $job_title, $location, $years_exp, $cv_url, $id);
        } else {
            $img = isset($image_path) ? $image_path : '';
            $stmt = $conn->prepare("INSERT INTO about (content, profile_image, job_title, location, years_exp, cv_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $content, $img, $job_title, $location, $years_exp, $cv_url);
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
    $icon = clean_input($_POST['social_icon'] ?? '');
    $logo_path = '';
    if (!empty($_FILES['social_logo_img']['name'])) {
        $up = upload_image($_FILES['social_logo_img']);
        if (isset($up['success'])) {
            $logo_path = $up['success'];
        } else {
            $error = $up['error'];
        }
    }
    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO social_links (platform, url, icon_class, logo_image) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $platform, $url, $icon, $logo_path);
        if ($stmt->execute()) {
            $message = "Social link added.";
        } else {
            $error = "Error adding social link.";
        }
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
    $logo_sql = '';
    if (!empty($_FILES['edit_social_logo']['name'])) {
        $up = upload_image($_FILES['edit_social_logo']);
        if (isset($up['success'])) {
            $logo_sql = ", logo_image='" . $conn->real_escape_string($up['success']) . "'";
        } else {
            $error = $up['error'];
        }
    }
    if (empty($error)) {
        $conn->query("UPDATE social_links SET platform='$platform', url='$url', icon_class='$icon'$logo_sql WHERE id=$id");
        $message = "Social link updated.";
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

// --- TIMELINE ENTRY ADD ---
if (isset($_POST['add_timeline'])) {
    $title = clean_input($_POST['tl_title']);
    $desc = clean_input($_POST['tl_description'] ?? '');
    $icon = clean_input($_POST['tl_icon'] ?? 'fas fa-circle');
    $color = clean_input($_POST['tl_color'] ?? '#ffd60a');
    $avatar_id = clean_input($_POST['tl_avatar_id'] ?? '');
    $display_type = clean_input($_POST['tl_display_type'] ?? 'icon');
    $start = clean_input($_POST['tl_start_date']);
    $end = !empty($_POST['tl_end_date']) ? clean_input($_POST['tl_end_date']) : null;
    $link = clean_input($_POST['tl_link'] ?? '');
    $is_active = isset($_POST['tl_is_active']) ? 1 : 0;
    $sort = (int) ($_POST['tl_sort_order'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO timeline_entries (title, description, icon, color, avatar_id, display_type, start_date, end_date, link, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssii", $title, $desc, $icon, $color, $avatar_id, $display_type, $start, $end, $link, $is_active, $sort);
    if ($stmt->execute()) {
        $new_tl_id = $conn->insert_id;

        // Handle multi-avatar selection
        if ($display_type === 'avatar' && !empty($_POST['tl_avatars'])) {
            $avatars = array_slice(array_filter($_POST['tl_avatars']), 0, 10);
            foreach ($avatars as $si => $av_id) {
                $av_clean = clean_input($av_id);
                $avs = $conn->prepare("INSERT INTO timeline_avatars (timeline_entry_id, avatar_id, sort_order) VALUES (?, ?, ?)");
                $avs->bind_param("isi", $new_tl_id, $av_clean, $si);
                $avs->execute();
            }
        }

        // Handle multi-image upload
        if ($display_type === 'image' && !empty($_FILES['tl_images']['name'][0])) {
            $file_count = min(count($_FILES['tl_images']['name']), 10);
            for ($fi = 0; $fi < $file_count; $fi++) {
                if (empty($_FILES['tl_images']['name'][$fi])) continue;
                $single_file = [
                    'name' => $_FILES['tl_images']['name'][$fi],
                    'type' => $_FILES['tl_images']['type'][$fi],
                    'tmp_name' => $_FILES['tl_images']['tmp_name'][$fi],
                    'error' => $_FILES['tl_images']['error'][$fi],
                    'size' => $_FILES['tl_images']['size'][$fi],
                ];
                $up = upload_image($single_file);
                if (isset($up['success'])) {
                    $img_path = $up['success'];
                    $imgs = $conn->prepare("INSERT INTO timeline_images (timeline_entry_id, image_path, sort_order) VALUES (?, ?, ?)");
                    $imgs->bind_param("isi", $new_tl_id, $img_path, $fi);
                    $imgs->execute();
                }
            }
        }

        $message = "Timeline entry added.";
    } else {
        $error = "Error adding timeline entry.";
    }
}

// --- TIMELINE ENTRY DELETE ---
if (isset($_GET['delete_timeline'])) {
    $id = (int) $_GET['delete_timeline'];
    // Delete associated images from filesystem
    $del_imgs = $conn->query("SELECT image_path FROM timeline_images WHERE timeline_entry_id=$id");
    if ($del_imgs) {
        while ($di = $del_imgs->fetch_assoc()) {
            @unlink('../uploads/' . $di['image_path']);
        }
    }
    $conn->query("DELETE FROM timeline_entries WHERE id=$id");
    $message = "Timeline entry deleted.";
}

// --- TIMELINE ENTRY UPDATE ---
if (isset($_POST['update_timeline'])) {
    $id = (int) $_POST['edit_tl_id'];
    $title = clean_input($_POST['edit_tl_title']);
    $desc = clean_input($_POST['edit_tl_description'] ?? '');
    $icon = clean_input($_POST['edit_tl_icon'] ?? 'fas fa-circle');
    $color = clean_input($_POST['edit_tl_color'] ?? '#ffd60a');
    $avatar_id = clean_input($_POST['edit_tl_avatar_id'] ?? '');
    $display_type = clean_input($_POST['edit_tl_display_type'] ?? 'icon');
    $start = clean_input($_POST['edit_tl_start_date']);
    $end = !empty($_POST['edit_tl_end_date']) ? clean_input($_POST['edit_tl_end_date']) : null;
    $link = clean_input($_POST['edit_tl_link'] ?? '');
    $is_active = isset($_POST['edit_tl_is_active']) ? 1 : 0;
    $sort = (int) ($_POST['edit_tl_sort_order'] ?? 0);

    $stmt = $conn->prepare("UPDATE timeline_entries SET title=?, description=?, icon=?, color=?, avatar_id=?, display_type=?, start_date=?, end_date=?, link=?, is_active=?, sort_order=? WHERE id=?");
    $stmt->bind_param("sssssssssiis", $title, $desc, $icon, $color, $avatar_id, $display_type, $start, $end, $link, $is_active, $sort, $id);
    if ($stmt->execute()) {
        // Update multi-avatars if display type is avatar
        if ($display_type === 'avatar' && isset($_POST['edit_tl_avatars'])) {
            $conn->query("DELETE FROM timeline_avatars WHERE timeline_entry_id=$id");
            $avatars = array_slice(array_filter($_POST['edit_tl_avatars']), 0, 10);
            foreach ($avatars as $si => $av_id) {
                $av_clean = clean_input($av_id);
                $avs = $conn->prepare("INSERT INTO timeline_avatars (timeline_entry_id, avatar_id, sort_order) VALUES (?, ?, ?)");
                $avs->bind_param("isi", $id, $av_clean, $si);
                $avs->execute();
            }
        }

        // Handle new image uploads if display type is image
        if ($display_type === 'image' && !empty($_FILES['edit_tl_images']['name'][0])) {
            // Get current count
            $cnt_q = $conn->query("SELECT COUNT(*) as cnt FROM timeline_images WHERE timeline_entry_id=$id");
            $current_count = $cnt_q ? $cnt_q->fetch_assoc()['cnt'] : 0;
            $remaining = 10 - $current_count;
            $file_count = min(count($_FILES['edit_tl_images']['name']), $remaining);
            for ($fi = 0; $fi < $file_count; $fi++) {
                if (empty($_FILES['edit_tl_images']['name'][$fi])) continue;
                $single_file = [
                    'name' => $_FILES['edit_tl_images']['name'][$fi],
                    'type' => $_FILES['edit_tl_images']['type'][$fi],
                    'tmp_name' => $_FILES['edit_tl_images']['tmp_name'][$fi],
                    'error' => $_FILES['edit_tl_images']['error'][$fi],
                    'size' => $_FILES['edit_tl_images']['size'][$fi],
                ];
                $up = upload_image($single_file);
                if (isset($up['success'])) {
                    $img_path = $up['success'];
                    $srt = $current_count + $fi;
                    $imgs = $conn->prepare("INSERT INTO timeline_images (timeline_entry_id, image_path, sort_order) VALUES (?, ?, ?)");
                    $imgs->bind_param("isi", $id, $img_path, $srt);
                    $imgs->execute();
                }
            }
        }

        $message = "Timeline entry updated.";
    } else {
        $error = "Error updating timeline entry.";
    }
}

// --- TIMELINE IMAGE DELETE ---
if (isset($_GET['delete_tl_image'])) {
    $img_id = (int) $_GET['delete_tl_image'];
    $img_q = $conn->query("SELECT image_path FROM timeline_images WHERE id=$img_id");
    if ($img_q && $img_q->num_rows > 0) {
        $img_row = $img_q->fetch_assoc();
        @unlink('../uploads/' . $img_row['image_path']);
    }
    $conn->query("DELETE FROM timeline_images WHERE id=$img_id");
    $message = "Timeline image deleted.";
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
    <title>Admin Dashboard— Praveen Portfolio</title>
    <link rel="stylesheet" href="admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-avatar"><?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?>
            </div>
            <div>
                <div class="sidebar-username"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                </div>
                <div class="sidebar-role">Portfolio Admin</div>
            </div>
        </div>
        <ul class="nav-links-admin">
            <li class="nav-section-label">Content</li>
            <li><a href="?tab=hero" class="<?php echo $active_tab == 'hero' ? 'active' : ''; ?>"><i
                        class="fas fa-home"></i> Hero</a></li>
            <li><a href="?tab=about" class="<?php echo $active_tab == 'about' ? 'active' : ''; ?>"><i
                        class="fas fa-user"></i> About Me</a></li>
            <li><a href="?tab=skills" class="<?php echo $active_tab == 'skills' ? 'active' : ''; ?>"><i
                        class="fas fa-code"></i> Skills</a></li>
            <li><a href="?tab=projects" class="<?php echo $active_tab == 'projects' ? 'active' : ''; ?>"><i
                        class="fas fa-briefcase"></i> Projects</a></li>
            <li><a href="?tab=internships" class="<?php echo $active_tab == 'internships' ? 'active' : ''; ?>"><i
                        class="fas fa-graduation-cap"></i> Experience</a></li>
            <li><a href="?tab=timeline" class="<?php echo $active_tab == 'timeline' ? 'active' : ''; ?>"><i
                        class="fas fa-stream"></i> Timeline</a></li>
            <li class="nav-section-label">Settings</li>
            <li><a href="?tab=settings" class="<?php echo $active_tab == 'settings' ? 'active' : ''; ?>"><i
                        class="fas fa-cogs"></i> General Settings</a></li>
            <li><a href="?tab=social" class="<?php echo $active_tab == 'social' ? 'active' : ''; ?>"><i
                        class="fas fa-share-alt"></i> Social Links</a></li>
            <li><a href="?tab=profile" class="<?php echo $active_tab == 'profile' ? 'active' : ''; ?>"><i
                        class="fas fa-id-card"></i> Profile</a></li>
        </ul>
        <div class="sidebar-bottom">
            <a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
            <a href="logout.php" class="nav-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">

        <!-- Top Header for Premium UX -->
        <div class="top-header fade-in">
            <div class="top-header-left">
                <h2><?php
                $titles = [
                    'hero' => 'Hero Section',
                    'about' => 'About Me',
                    'skills' => 'My Skills',
                    'projects' => 'Projects',
                    'internships' => 'Experience & Fellowship',
                    'timeline' => 'Career Timeline',
                    'settings' => 'General Settings',
                    'social' => 'Social Links',
                    'profile' => 'Profile'
                ];
                echo $titles[$active_tab] ?? 'Dashboard';
                ?></h2>
                <div class="breadcrumbs">
                    <span>Admin</span> <i class="fas fa-chevron-right"></i>
                    <span class="active-crumb"><?php echo $titles[$active_tab] ?? 'Dashboard'; ?></span>
                </div>
            </div>
            <div class="top-header-right">
                <div class="live-clock" id="liveClock"></div>
                <div class="header-user-badge">
                    <div class="header-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?></div>
                    <span>Hi, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </div>
        </div>

        <style>
            .top-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2rem;
                padding: 1.2rem 1.8rem;
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
                border: 1px solid rgba(0, 0, 0, 0.04);
            }

            .top-header h2 {
                font-size: 1.4rem;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 0.3rem 0;
                letter-spacing: -0.02em;
            }

            .breadcrumbs {
                font-size: 0.82rem;
                color: #64748b;
                display: flex;
                align-items: center;
                gap: 0.4rem;
                font-weight: 500;
            }

            .breadcrumbs i {
                font-size: 0.6rem;
                color: #cbd5e1;
            }

            .breadcrumbs .active-crumb {
                color: #6366f1;
                font-weight: 600;
            }

            .top-header-right {
                display: flex;
                align-items: center;
                gap: 1.5rem;
            }

            .live-clock {
                font-size: 0.88rem;
                font-weight: 600;
                color: #475569;
                background: #f8fafc;
                padding: 0.4rem 0.8rem;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
            }

            .header-user-badge {
                display: flex;
                align-items: center;
                gap: 0.7rem;
                font-size: 0.9rem;
                font-weight: 600;
                color: #1e293b;
                background: #f1f5f9;
                padding: 0.4rem 1rem 0.4rem 0.4rem;
                border-radius: 30px;
            }

            .header-avatar {
                width: 32px;
                height: 32px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                font-size: 0.85rem;
            }

            @media (max-width: 768px) {
                .top-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }

                .top-header-right {
                    width: 100%;
                    justify-content: space-between;
                }
            }
        </style>
        <script>
            setInterval(() => {
                const now = new Date();
                document.getElementById('liveClock').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }, 1000);
        </script>

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
                    <div class="section-header">
                        <div class="section-icon-badge" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);"><i
                                class="fas fa-home"></i></div>
                        <div>
                            <h2>Hero Section</h2>
                            <p class="section-subtitle">Main headline and subtitle shown at the top of your portfolio</p>
                        </div>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Headline</label>
                            <input type="text" name="hero_title"
                                value="<?php echo isset($hero['title']) ? htmlspecialchars($hero['title']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Subtitle</label>
                            <input type="text" name="hero_subtitle"
                                value="<?php echo isset($hero['subtitle']) ? htmlspecialchars($hero['subtitle']) : ''; ?>"
                                required>
                        </div>
                        <button type="submit" name="update_hero" class="btn-primary"><i class="fas fa-save"></i> Update
                            Hero</button>
                    </form>
                </div>
                <!-- Navbar Logo Upload -->
                <div class="admin-card fade-in">
                    <div class="section-header">
                        <div class="section-icon-badge" style="background:linear-gradient(135deg,#0ea5e9,#38bdf8);"><i
                                class="fas fa-image"></i></div>
                        <div>
                            <h2>Navbar Logo</h2>
                            <p class="section-subtitle">Upload an image logo for the navigation bar (replaces text logo)</p>
                        </div>
                    </div>
                    <?php $navbar_logo_img = isset($site_content['navbar_logo_img']) ? $site_content['navbar_logo_img']['content_value'] : ''; ?>
                    <?php if ($navbar_logo_img): ?>
                            <div class="img-preview-block">
                                <p>Current navbar logo:</p><img src="../uploads/<?php echo htmlspecialchars($navbar_logo_img); ?>"
                                    alt="Navbar Logo">
                            </div>
                    <?php else: ?>
                            <div class="upload-zone" style="margin-bottom:1.2rem;"><i class="fas fa-image"></i>
                                <p>No navbar logo uploaded yet</p>
                            </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" style="margin-top:1rem;">
                        <div class="form-group">
                            <label><i class="fas fa-upload"></i> Upload New Logo</label>
                            <input type="file" name="navbar_logo_img" accept="image/*">
                        </div>
                        <button type="submit" name="update_navbar_logo" class="btn-primary"><i class="fas fa-save"></i> Update
                            Navbar Logo</button>
                    </form>
                </div>
        <?php endif; ?>

        <?php if ($active_tab == 'about'): ?>
                <div class="admin-card fade-in">
                    <div class="section-header">
                        <div class="section-icon-badge" style="background:linear-gradient(135deg,#10b981,#34d399);"><i
                                class="fas fa-user"></i></div>
                        <div>
                            <h2>About Me</h2>
                            <p class="section-subtitle">Your profile info, bio, and photo displayed on the portfolio</p>
                        </div>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Photo + Meta -->
                        <div class="profile-upload-row">
                            <div class="profile-upload-zone" id="profileDropZone"
                                onclick="document.getElementById('profileImageInput').click()" title="Click to change photo">
                                <?php if (!empty($about['profile_image'])): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($about['profile_image']); ?>" alt="Profile"
                                            id="profilePreview">
                                <?php else: ?>
                                        <div class="upload-placeholder" id="uploadPlaceholder"><i
                                                class="fas fa-user-circle"></i><span>Upload Photo</span></div>
                                <?php endif; ?>
                                <div class="upload-overlay"><i class="fas fa-camera"></i><span>Change</span></div>
                                <input type="file" name="profile_image" id="profileImageInput" accept="image/*"
                                    class="hidden-file-input">
                            </div>
                            <div class="profile-meta-fields">
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-briefcase"></i> Job Title</label>
                                        <input type="text" name="about_job_title" placeholder="e.g. Full Stack Developer"
                                            value="<?php echo htmlspecialchars($about['job_title'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-map-marker-alt"></i> Location</label>
                                        <input type="text" name="about_location" placeholder="e.g. Chennai, India"
                                            value="<?php echo htmlspecialchars($about['location'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-clock"></i> Years of Experience</label>
                                        <input type="number" name="about_years_exp" min="0" max="60" placeholder="3"
                                            value="<?php echo htmlspecialchars($about['years_exp'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-file-pdf"></i> CV / Resume URL</label>
                                        <input type="url" name="about_cv_url" placeholder="https://..."
                                            value="<?php echo htmlspecialchars($about['cv_url'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Bio <span class="char-count" id="bioCount"></span></label>
                            <textarea name="about_content" rows="6" id="bioTextarea" placeholder="Tell your story..."
                                required><?php echo isset($about['content']) ? htmlspecialchars($about['content']) : ''; ?></textarea>
                        </div>
                        <button type="submit" name="update_about" class="btn-primary"><i class="fas fa-save"></i> Save About
                            Section</button>
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
                    <div style="background:var(--bg-surface-raised); border:1px solid var(--border-strong); border-radius:var(--radius-lg); padding:2rem; margin-bottom:2.5rem; box-shadow:inset 0 1px 0 rgba(255,255,255,0.05);">
                        <h3 style="margin-bottom:1.5rem; font-size:1.15rem; color:var(--text-strong);"><i class="fas fa-plus-circle"
                                style="color:var(--accent-primary); margin-right:0.5rem;"></i> Add New Fellowship / Internship</h3>
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
                            <div style="background:var(--bg-surface); border:1px solid var(--border-strong); border-radius:var(--radius-xl); margin-bottom:2.5rem; overflow:hidden; box-shadow:var(--shadow-md);">
                                <!-- Header Bar -->
                                <div
                                    style="background:var(--bg-surface-highlight); padding:1.25rem 2rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-subtle);">
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
                                    <div style="display:flex;gap:0.75rem;">
                                        <button type="button" class="btn-sm btn-edit" onclick="toggleEditRow('fi-<?php echo $intern['id']; ?>')"><i
                                                class="fas fa-edit"></i> Edit</button>
                                        <a href="?tab=internships&delete_internship=<?php echo $intern['id']; ?>"
                                            class="btn-sm btn-danger"
                                            onclick="return confirm('Delete this entire fellowship and all its data?')"><i
                                                class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </div>
                                <!-- Inline Edit Panel -->
                                <div id="fi-<?php echo $intern['id']; ?>"
                                    style="display:none;background:var(--bg-surface-raised);border-bottom:1px solid var(--border-strong);padding:2rem;">
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
                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid var(--border-strong);">
                                    <?php
                                    $tab_styles = [
                                        'skills' => ['icon' => 'fa-bolt', 'label' => 'Skills Learned', 'color' => 'var(--success)', 'bg' => 'var(--success-bg)'],
                                        'frameworks' => ['icon' => 'fa-layer-group', 'label' => 'Frameworks', 'color' => 'var(--accent-secondary)', 'bg' => 'var(--accent-subtle)'],
                                        'projects' => ['icon' => 'fa-code-branch', 'label' => 'Projects Built', 'color' => 'var(--info)', 'bg' => 'var(--info-bg)'],
                                    ];
                                    foreach ($tab_styles as $tkey => $tval): ?>
                                            <div
                                                style="padding:1.2rem;text-align:center;border-right:1px solid var(--border-subtle);background:var(--bg-surface);font-weight:600;font-size:0.88rem;color:<?php echo $tval['color']; ?>;">
                                                <i class="fas <?php echo $tval['icon']; ?>"
                                                    style="margin-right:0.5rem; font-size:1.1rem;"></i><?php echo $tval['label']; ?>
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
                    <div class="section-header">
                        <div class="section-icon-badge" style="background:linear-gradient(135deg,#ec4899,#f472b6);"><i
                                class="fas fa-share-alt"></i></div>
                        <div>
                            <h2>Social Links</h2>
                            <p class="section-subtitle">Manage all your social media links shown in the footer</p>
                        </div>
                    </div>
                    <form method="POST" enctype="multipart/form-data"
                        style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;min-width:140px;">
                            <label>Platform</label>
                            <input type="text" name="social_platform" placeholder="LinkedIn" required>
                        </div>
                        <div class="form-group" style="flex:2;min-width:200px;">
                            <label>URL</label>
                            <input type="text" name="social_url" placeholder="https://..." required>
                        </div>
                        <div class="form-group" style="flex:1;min-width:130px;">
                            <label>Icon Class <small style="font-weight:400;color:var(--text-muted)">(fa-...)</small></label>
                            <input type="text" name="social_icon" placeholder="fa-linkedin">
                        </div>
                        <div class="form-group" style="flex:1;min-width:160px;">
                            <label>Logo Image <small style="font-weight:400;color:var(--text-muted)">(optional)</small></label>
                            <input type="file" name="social_logo_img" accept="image/*">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_social" class="btn-primary"><i class="fas fa-plus"></i> Add
                                Link</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Logo</th>
                                    <th>Platform</th>
                                    <th>URL</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($social = $socials->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($social['logo_image'])): ?>
                                                        <img src="../uploads/<?php echo htmlspecialchars($social['logo_image']); ?>"
                                                            class="social-logo-thumb" alt="logo">
                                                <?php else: ?>
                                                        <div
                                                            style="width:32px;height:32px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                                            <i class="fab fa-<?php echo strtolower(htmlspecialchars($social['platform'])); ?>"
                                                                style="color:var(--text-muted);"></i>
                                                        </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight:600;text-transform:capitalize;">
                                                <?php echo htmlspecialchars($social['platform']); ?>
                                            </td>
                                            <td><a href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank"
                                                    style="color:var(--accent);text-decoration:none;font-size:0.88rem;"><?php echo htmlspecialchars($social['url']); ?></a>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:0.5rem;">
                                                    <button type="button" class="btn-sm btn-edit"
                                                        onclick="toggleEditRow('sl-<?php echo $social['id']; ?>')"><i
                                                            class="fas fa-edit"></i></button>
                                                    <a href="?tab=social&delete_social=<?php echo $social['id']; ?>"
                                                        class="btn-sm btn-danger" onclick="return confirm('Delete this link?')"><i
                                                            class="fas fa-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Inline Edit Row -->
                                        <tr id="sl-<?php echo $social['id']; ?>" style="display:none;">
                                            <td colspan="4" style="padding:1rem;">
                                                <form method="POST" enctype="multipart/form-data"
                                                    style="display:flex;gap:0.8rem;flex-wrap:wrap;align-items:flex-end;">
                                                    <input type="hidden" name="edit_social_id" value="<?php echo $social['id']; ?>">
                                                    <div class="form-group" style="flex:1;min-width:120px;margin:0;">
                                                        <label style="font-size:0.78rem;">Platform</label>
                                                        <input type="text" name="edit_social_platform"
                                                            value="<?php echo htmlspecialchars($social['platform']); ?>"
                                                            style="font-size:0.85rem;">
                                                    </div>
                                                    <div class="form-group" style="flex:3;min-width:200px;margin:0;">
                                                        <label style="font-size:0.78rem;">URL</label>
                                                        <input type="text" name="edit_social_url"
                                                            value="<?php echo htmlspecialchars($social['url']); ?>"
                                                            style="font-size:0.85rem;">
                                                    </div>
                                                    <div class="form-group" style="flex:1;min-width:130px;margin:0;">
                                                        <label style="font-size:0.78rem;">Icon (fa-...)</label>
                                                        <input type="text" name="edit_social_icon"
                                                            value="<?php echo htmlspecialchars($social['icon'] ?? ''); ?>"
                                                            placeholder="fa-linkedin" style="font-size:0.85rem;">
                                                    </div>
                                                    <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                                                        <label style="font-size:0.78rem;">New Logo Image</label>
                                                        <input type="file" name="edit_social_logo" accept="image/*"
                                                            style="font-size:0.85rem;">
                                                    </div>
                                                    <button type="submit" name="update_social" class="btn-primary"
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

        <?php if ($active_tab == 'timeline'): ?>
            <?php
            $timeline_entries = $conn->query("SELECT * FROM timeline_entries ORDER BY sort_order ASC, start_date DESC");
            $all_tl_images = [];
            $tl_img_q = $conn->query("SELECT * FROM timeline_images ORDER BY sort_order ASC");
            if ($tl_img_q) { while ($r = $tl_img_q->fetch_assoc()) { $all_tl_images[$r['timeline_entry_id']][] = $r; } }
            $all_tl_avatars = [];
            $tl_av_q = $conn->query("SELECT * FROM timeline_avatars ORDER BY sort_order ASC");
            if ($tl_av_q) { while ($r = $tl_av_q->fetch_assoc()) { $all_tl_avatars[$r['timeline_entry_id']][] = $r; } }
            $male_seeds = []; $female_seeds = [];
            for ($i = 1; $i <= 50; $i++) { $male_seeds[] = "male-avatar-" . $i; $female_seeds[] = "female-avatar-" . $i; }
            ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-stream"></i> Add Timeline Entry</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group"><label>Title <span style="color:red">*</span></label><input type="text" name="tl_title" placeholder="e.g. Started at TinkerHub" required></div>
                        <div class="form-group"><label>Icon Class (FontAwesome)</label><input type="text" name="tl_icon" value="fas fa-circle" placeholder="fas fa-rocket"></div>
                    </div>
                    <div class="form-group"><label>Description</label><textarea name="tl_description" rows="2" placeholder="Brief description"></textarea></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                        <div class="form-group"><label>Start Date <span style="color:red">*</span></label><input type="date" name="tl_start_date" required></div>
                        <div class="form-group"><label>End Date <small style="color:#999">(empty = ongoing)</small></label><input type="date" name="tl_end_date"></div>
                        <div class="form-group"><label>Bar Color</label><div style="display:flex;gap:0.5rem;align-items:center;"><input type="color" name="tl_color" value="#ffd60a" style="width:50px;height:36px;border:1px solid #ddd;border-radius:4px;cursor:pointer;"><span style="color:#999;font-size:0.8rem;">Pick accent</span></div></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group"><label>Link (optional)</label><input type="url" name="tl_link" placeholder="https://example.com"></div>
                        <div class="form-group"><label>Sort Order</label><input type="number" name="tl_sort_order" value="0" min="0"></div>
                    </div>
                    <div class="form-group">
                        <label style="margin-bottom:0.5rem;display:block;font-weight:700;">Display Type</label>
                        <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
                            <button type="button" onclick="switchDisplayType('icon','add')" style="padding:0.5rem 1.2rem;font-size:0.82rem;background:#111;color:#fff;border:2px solid #111;border-radius:0;cursor:pointer;font-weight:700;" id="addTypeIcon"><i class="fas fa-icons"></i> Icon</button>
                            <button type="button" onclick="switchDisplayType('avatar','add')" style="padding:0.5rem 1.2rem;font-size:0.82rem;background:#fff;color:#111;border:2px solid #ddd;border-radius:0;cursor:pointer;font-weight:700;" id="addTypeAvatar"><i class="fas fa-user-circle"></i> Avatar (up to 10)</button>
                            <button type="button" onclick="switchDisplayType('image','add')" style="padding:0.5rem 1.2rem;font-size:0.82rem;background:#fff;color:#111;border:2px solid #ddd;border-radius:0;cursor:pointer;font-weight:700;" id="addTypeImage"><i class="fas fa-image"></i> Image (up to 10)</button>
                        </div>
                        <input type="hidden" name="tl_display_type" id="tl_display_type" value="icon">
                    </div>
                    <div id="addAvatarPanel" style="display:none;">
                        <div class="form-group">
                            <label>Choose Avatars <small style="color:#999;">(click up to 10)</small></label>
                            <input type="hidden" name="tl_avatar_id" id="tl_avatar_id" value="">
                            <div style="display:flex;gap:0.3rem;align-items:center;margin-bottom:0.5rem;">
                                <button type="button" onclick="showAvatarGender('male','add')" style="padding:0.3rem 0.8rem;font-size:0.75rem;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:4px;cursor:pointer;">&#x1F468; Male</button>
                                <button type="button" onclick="showAvatarGender('female','add')" style="padding:0.3rem 0.8rem;font-size:0.75rem;background:#fce7f3;color:#be185d;border:1px solid #fbcfe8;border-radius:4px;cursor:pointer;">&#x1F469; Female</button>
                                <span id="addAvatarCount" style="margin-left:auto;font-size:0.75rem;color:#999;">0/10 selected</span>
                            </div>
                            <div id="addAvatarGridMale" style="display:grid;grid-template-columns:repeat(10,1fr);gap:0.4rem;max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;padding:0.5rem;background:#f9fafb;">
                                <?php foreach ($male_seeds as $idx => $seed): ?>
                                <div class="avatar-pick-multi" data-avatar="m-<?php echo $idx+1; ?>" onclick="toggleMultiAvatar(this,'add')" style="cursor:pointer;border-radius:50%;border:2px solid transparent;padding:2px;transition:all 0.2s;">
                                    <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=<?php echo $seed; ?>&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf" style="width:100%;border-radius:50%;" alt="M<?php echo $idx+1; ?>" loading="lazy">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="addAvatarGridFemale" style="display:none;grid-template-columns:repeat(10,1fr);gap:0.4rem;max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;padding:0.5rem;background:#f9fafb;">
                                <?php foreach ($female_seeds as $idx => $seed): ?>
                                <div class="avatar-pick-multi" data-avatar="f-<?php echo $idx+1; ?>" onclick="toggleMultiAvatar(this,'add')" style="cursor:pointer;border-radius:50%;border:2px solid transparent;padding:2px;transition:all 0.2s;">
                                    <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=<?php echo $seed; ?>&backgroundColor=ffd5dc,ffdfbf,c0aede,b6e3f4,d1d4f9" style="width:100%;border-radius:50%;" alt="F<?php echo $idx+1; ?>" loading="lazy">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="addAvatarInputs"></div>
                        </div>
                    </div>
                    <div id="addImagePanel" style="display:none;">
                        <div class="form-group">
                            <label>Upload Images <small style="color:#999;">(up to 10, max 5MB each)</small></label>
                            <input type="file" name="tl_images[]" multiple accept="image/*" style="padding:0.6rem;border:2px dashed #ddd;border-radius:6px;width:100%;background:#f9fafb;cursor:pointer;" onchange="limitFiles(this,10)">
                        </div>
                    </div>
                    <div class="form-group"><label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;"><input type="checkbox" name="tl_is_active" checked style="width:16px;height:16px;"> Show on website</label></div>
                    <button type="submit" name="add_timeline" class="btn-primary"><i class="fas fa-plus"></i> Add Entry</button>
                </form>
            </div>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-list"></i> Timeline Entries</h2>
                <?php if ($timeline_entries && $timeline_entries->num_rows > 0): ?>
                <div class="table-responsive"><table><thead><tr><th>Preview</th><th>Title</th><th>Type</th><th>Duration</th><th>Color</th><th>Active</th><th>Actions</th></tr></thead><tbody>
                    <?php while ($tl = $timeline_entries->fetch_assoc()):
                        $tl_id = $tl['id']; $d_type = $tl['display_type'] ?? 'icon';
                        $entry_imgs = $all_tl_images[$tl_id] ?? []; $entry_avs = $all_tl_avatars[$tl_id] ?? [];
                        $preview_html = '<i class="'.htmlspecialchars($tl['icon']).'" style="font-size:1.2rem;color:'.htmlspecialchars($tl['color']).';"></i>';
                        if ($d_type === 'avatar' && !empty($entry_avs)) {
                            $p = explode('-', $entry_avs[0]['avatar_id']); $sp = ($p[0]??'m')==='f'?'female-avatar-':'male-avatar-';
                            $preview_html = '<img src="https://api.dicebear.com/7.x/adventurer/svg?seed='.$sp.($p[1]??1).'&backgroundColor=b6e3f4" style="width:36px;height:36px;border-radius:50%;border:2px solid '.htmlspecialchars($tl['color']).';">';
                            if (count($entry_avs)>1) $preview_html .= '<span style="font-size:0.7rem;color:#999;margin-left:4px;">+'.(count($entry_avs)-1).'</span>';
                        } elseif ($d_type === 'image' && !empty($entry_imgs)) {
                            $preview_html = '<img src="../uploads/'.htmlspecialchars($entry_imgs[0]['image_path']).'" style="width:36px;height:36px;border-radius:6px;object-fit:cover;border:2px solid '.htmlspecialchars($tl['color']).';">';
                            if (count($entry_imgs)>1) $preview_html .= '<span style="font-size:0.7rem;color:#999;margin-left:4px;">+'.(count($entry_imgs)-1).'</span>';
                        } elseif (!empty($tl['avatar_id'])) {
                            $p = explode('-', $tl['avatar_id']); $sp = ($p[0]??'m')==='f'?'female-avatar-':'male-avatar-';
                            $preview_html = '<img src="https://api.dicebear.com/7.x/adventurer/svg?seed='.$sp.($p[1]??1).'&backgroundColor=b6e3f4" style="width:36px;height:36px;border-radius:50%;border:2px solid '.htmlspecialchars($tl['color']).';">';
                        }
                        $type_labels = ['icon'=>'Icon','avatar'=>'Avatar','image'=>'Image'];
                    ?>
                    <tr>
                        <td style="display:flex;align-items:center;gap:4px;"><?php echo $preview_html; ?></td>
                        <td><strong><?php echo htmlspecialchars($tl['title']); ?></strong><?php if($tl['description']): ?><br><small style="color:#999;"><?php echo htmlspecialchars(substr($tl['description'],0,60)); ?>…</small><?php endif; ?></td>
                        <td><span style="font-size:0.75rem;padding:0.2rem 0.5rem;background:#f1f5f9;border-radius:4px;"><?php echo $type_labels[$d_type] ?? 'Icon'; ?></span></td>
                        <td style="white-space:nowrap;font-size:0.82rem;"><?php echo date('M Y', strtotime($tl['start_date'])); ?> → <?php echo $tl['end_date'] ? date('M Y', strtotime($tl['end_date'])) : '<span style="color:#22c55e;">Present</span>'; ?></td>
                        <td><span style="display:inline-block;width:20px;height:20px;background:<?php echo htmlspecialchars($tl['color']); ?>;border-radius:3px;border:1px solid rgba(0,0,0,0.1);"></span></td>
                        <td><?php echo $tl['is_active'] ? '&#x2705;' : '&#x274C;'; ?></td>
                        <td style="white-space:nowrap;">
                            <button type="button" onclick="toggleEditRow('editTL<?php echo $tl['id']; ?>')" class="btn-sm" style="background:#eef2ff;color:#4338ca;padding:0.3rem 0.6rem;border:none;border-radius:4px;cursor:pointer;font-size:0.75rem;"><i class="fas fa-edit"></i></button>
                            <a href="?tab=timeline&delete_timeline=<?php echo $tl['id']; ?>" onclick="return confirm('Delete?')" class="btn-sm" style="background:#fef2f2;color:#dc2626;padding:0.3rem 0.6rem;border:none;border-radius:4px;text-decoration:none;font-size:0.75rem;"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <tr id="editTL<?php echo $tl['id']; ?>" style="display:none;">
                        <td colspan="7" style="padding:1rem;background:#f8fafc;">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="edit_tl_id" value="<?php echo $tl['id']; ?>">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                                    <div class="form-group"><label>Title</label><input type="text" name="edit_tl_title" value="<?php echo htmlspecialchars($tl['title']); ?>" required></div>
                                    <div class="form-group"><label>Icon</label><input type="text" name="edit_tl_icon" value="<?php echo htmlspecialchars($tl['icon']); ?>"></div>
                                </div>
                                <div class="form-group"><label>Description</label><textarea name="edit_tl_description" rows="2"><?php echo htmlspecialchars($tl['description'] ?? ''); ?></textarea></div>
                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.8rem;">
                                    <div class="form-group"><label>Start</label><input type="date" name="edit_tl_start_date" value="<?php echo $tl['start_date']; ?>" required></div>
                                    <div class="form-group"><label>End</label><input type="date" name="edit_tl_end_date" value="<?php echo $tl['end_date'] ?? ''; ?>"></div>
                                    <div class="form-group"><label>Color</label><input type="color" name="edit_tl_color" value="<?php echo htmlspecialchars($tl['color']); ?>" style="width:50px;height:36px;"></div>
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                                    <div class="form-group"><label>Link</label><input type="url" name="edit_tl_link" value="<?php echo htmlspecialchars($tl['link'] ?? ''); ?>"></div>
                                    <div class="form-group"><label>Sort</label><input type="number" name="edit_tl_sort_order" value="<?php echo $tl['sort_order']; ?>"></div>
                                </div>
                                <div class="form-group"><label style="font-weight:700;">Display Type</label>
                                    <select name="edit_tl_display_type" style="padding:0.5rem;border:2px solid #ddd;border-radius:4px;font-size:0.85rem;width:200px;">
                                        <option value="icon" <?php echo $d_type==='icon'?'selected':''; ?>>Icon</option>
                                        <option value="avatar" <?php echo $d_type==='avatar'?'selected':''; ?>>Avatar</option>
                                        <option value="image" <?php echo $d_type==='image'?'selected':''; ?>>Image</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>Avatar ID</label>
                                    <input type="text" name="edit_tl_avatar_id" value="<?php echo htmlspecialchars($tl['avatar_id'] ?? ''); ?>" placeholder="e.g. m-3 or f-12">
                                    <?php if (!empty($entry_avs)): ?>
                                    <div style="margin-top:0.3rem;">
                                        <?php foreach ($entry_avs as $eav):
                                            $p = explode('-', $eav['avatar_id']); $sp = ($p[0]??'m')==='f'?'female-avatar-':'male-avatar-';
                                        ?>
                                        <input type="hidden" name="edit_tl_avatars[]" value="<?php echo htmlspecialchars($eav['avatar_id']); ?>">
                                        <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=<?php echo $sp.($p[1]??1); ?>&backgroundColor=b6e3f4" style="width:28px;height:28px;border-radius:50%;border:1px solid #ddd;margin-right:2px;">
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($entry_imgs)): ?>
                                <div class="form-group"><label>Current Images (<?php echo count($entry_imgs); ?>/10)</label>
                                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.3rem;">
                                        <?php foreach ($entry_imgs as $eimg): ?>
                                        <div style="position:relative;display:inline-block;">
                                            <img src="../uploads/<?php echo htmlspecialchars($eimg['image_path']); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:2px solid #ddd;">
                                            <a href="?tab=timeline&delete_tl_image=<?php echo $eimg['id']; ?>" onclick="return confirm('Delete image?')" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.6rem;text-decoration:none;border:2px solid #fff;">x</a>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="form-group"><label>Add Images <small style="color:#999;">(<?php echo max(0,10-count($entry_imgs)); ?> left)</small></label>
                                    <input type="file" name="edit_tl_images[]" multiple accept="image/*" style="padding:0.4rem;border:1px dashed #ddd;border-radius:4px;width:100%;font-size:0.82rem;" onchange="limitFiles(this,<?php echo max(0,10-count($entry_imgs)); ?>)">
                                </div>
                                <div class="form-group"><label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;"><input type="checkbox" name="edit_tl_is_active" <?php echo $tl['is_active']?'checked':''; ?> style="width:16px;height:16px;"> Show</label></div>
                                <button type="submit" name="update_timeline" class="btn-primary" style="font-size:0.85rem;"><i class="fas fa-save"></i> Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody></table></div>
                <?php else: ?>
                    <p style="text-align:center;color:#999;padding:2rem;">No timeline entries yet. Add your first one above!</p>
                <?php endif; ?>
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
                        <button type="submit" name="update_admin_profile" class="btn-primary">Update Now</button>
                    </form>
                </div>
        <?php endif; ?>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // ── IntersectionObserver for scroll-triggered fade-in ──
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('visible');
                        }, index * 60);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

            // ── Parallax depth on scroll ──
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                let ticking = false;
                mainContent.addEventListener('scroll', () => {
                    if (!ticking) {
                        requestAnimationFrame(() => {
                            const scrollY = mainContent.scrollTop || window.scrollY;
                            const cards = mainContent.querySelectorAll('.admin-card');
                            cards.forEach((card, i) => {
                                const depth = 0.02 + (i * 0.005);
                                card.style.transform = `translateY(${scrollY * depth * -1}px)`;
                            });
                            ticking = false;
                        });
                        ticking = true;
                    }
                });
            }

            // ── Mobile sidebar toggle ──
            const menuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuIcon = document.getElementById('menuIcon');

            if (menuBtn && sidebar && overlay) {
                menuBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('active');
                    menuIcon.classList.toggle('fa-bars');
                    menuIcon.classList.toggle('fa-times');
                });
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                    menuIcon.classList.add('fa-bars');
                    menuIcon.classList.remove('fa-times');
                });
            }

            // ── Profile image live preview ──
            const profileInput = document.getElementById('profileImageInput');
            if (profileInput) {
                profileInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            let preview = document.getElementById('profilePreview');
                            const placeholder = document.getElementById('uploadPlaceholder');
                            if (!preview) {
                                preview = document.createElement('img');
                                preview.id = 'profilePreview';
                                preview.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                                const zone = document.getElementById('profileDropZone');
                                if (placeholder) placeholder.style.display = 'none';
                                zone.insertBefore(preview, zone.querySelector('.upload-overlay'));
                            }
                            preview.src = e.target.result;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // ── Bio character counter ──
            const bioTextarea = document.getElementById('bioTextarea');
            const bioCount = document.getElementById('bioCount');
            if (bioTextarea && bioCount) {
                const update = () => bioCount.textContent = bioTextarea.value.length + ' chars';
                bioTextarea.addEventListener('input', update);
                update();
            }
        });

        // Toggle inline edit rows
        function toggleEditRow(id) {
            const row = document.getElementById(id);
            if (!row) return;
            const isHidden = row.style.display === 'none' || row.style.display === '';
            row.style.display = isHidden ? (row.tagName === 'TR' ? 'table-row' : 'block') : 'none';
        }

        // Timeline Display Type Switcher
        function switchDisplayType(type, prefix) {
            document.getElementById(prefix + 'TypeIcon').classList.remove('active');
            document.getElementById(prefix + 'TypeAvatar').classList.remove('active');
            document.getElementById(prefix + 'TypeImage').classList.remove('active');
            
            document.getElementById(prefix + 'TypeIcon').style.background = '#fff';
            document.getElementById(prefix + 'TypeIcon').style.color = '#111';
            document.getElementById(prefix + 'TypeAvatar').style.background = '#fff';
            document.getElementById(prefix + 'TypeAvatar').style.color = '#111';
            document.getElementById(prefix + 'TypeImage').style.background = '#fff';
            document.getElementById(prefix + 'TypeImage').style.color = '#111';

            const btn = document.getElementById(prefix + 'Type' + type.charAt(0).toUpperCase() + type.slice(1));
            btn.classList.add('active');
            btn.style.background = '#111';
            btn.style.color = '#fff';

            if(prefix === 'add') {
                document.getElementById('tl_display_type').value = type;
                document.getElementById('addAvatarPanel').style.display = type === 'avatar' ? 'block' : 'none';
                document.getElementById('addImagePanel').style.display = type === 'image' ? 'block' : 'none';
            }
        }

        // Avatar picker functions
        function showAvatarGender(gender, prefix = '') {
            const maleGrid = document.getElementById(prefix + 'AvatarGridMale');
            const femaleGrid = document.getElementById(prefix + 'AvatarGridFemale');
            if (!maleGrid || !femaleGrid) return;
            if (gender === 'male') {
                maleGrid.style.display = 'grid';
                femaleGrid.style.display = 'none';
            } else {
                maleGrid.style.display = 'none';
                femaleGrid.style.display = 'grid';
            }
        }

        // Multi Avatar Picker
        let selectedAvatars = [];
        function toggleMultiAvatar(el, prefix) {
            const avatarId = el.dataset.avatar;
            const index = selectedAvatars.indexOf(avatarId);

            if (index > -1) {
                // Deselect
                selectedAvatars.splice(index, 1);
                el.style.borderColor = 'transparent';
                el.style.transform = 'scale(1)';
            } else {
                // Select (Limit to 10)
                if (selectedAvatars.length >= 10) {
                    alert('You can select a maximum of 10 avatars.');
                    return;
                }
                selectedAvatars.push(avatarId);
                el.style.borderColor = '#ffd60a';
                el.style.transform = 'scale(1.15)';
            }

            // Update UI count
            document.getElementById(prefix + 'AvatarCount').textContent = selectedAvatars.length + '/10 selected';
            
            // Build hidden inputs
            const container = document.getElementById(prefix + 'AvatarInputs');
            container.innerHTML = '';
            selectedAvatars.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = prefix === 'add' ? 'tl_avatars[]' : 'edit_tl_avatars[]';
                input.value = id;
                container.appendChild(input);
            });
        }

        // Limit File Uploads
        function limitFiles(input, maxFiles) {
            if (input.files.length > maxFiles) {
                alert(`You can only upload a maximum of ${maxFiles} images.`);
                input.value = ''; // clear selection
            }
        }
    </script>
</body>

</html>