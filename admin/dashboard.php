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
    // Optional: Delete image file as well
    $conn->query("DELETE FROM projects WHERE id=$id");
    $message = "Project deleted.";
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

// --- INTERNSHIP ADD ---
if (isset($_POST['add_internship'])) {
    $company = clean_input($_POST['company_name']);
    $role = clean_input($_POST['role']);
    $duration = clean_input($_POST['duration']);
    $desc = clean_input($_POST['description']);
    $logo_path = '';

    if (!empty($_FILES['company_logo']['name'])) {
        $upload_result = upload_image($_FILES['company_logo']);
        if (isset($upload_result['success'])) {
            $logo_path = $upload_result['success'];
        } else {
            $error = $upload_result['error'];
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO internships (company_name, role, duration, description, company_logo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $company, $role, $duration, $desc, $logo_path);
        if ($stmt->execute()) {
            $message = "Internship added.";
        } else {
            $error = "Error adding internship.";
        }
    }
}

// --- INTERNSHIP DELETE ---
if (isset($_GET['delete_internship'])) {
    $id = (int) $_GET['delete_internship'];
    $conn->query("DELETE FROM internships WHERE id=$id");
    $message = "Internship deleted.";
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
                                        <?php echo htmlspecialchars($skill['description'] ?? ''); ?></td>
                                    <td>
                                        <?php
                                        $skill_tags = array_filter(array_map('trim', explode(',', $skill['tags'] ?? '')));
                                        foreach ($skill_tags as $tag) {
                                            echo '<span style="display:inline-block; padding:0.2rem 0.5rem; background:#e0f2fe; color:#0284c7; border-radius:4px; font-size:0.72rem; font-weight:600; margin:0.15rem;">' . htmlspecialchars($tag) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><a href="?tab=skills&delete_skill=<?php echo $skill['id']; ?>" class="btn-sm btn-danger"
                                            onclick="return confirm('Delete this skill?')"><i class="fas fa-trash"></i></a></td>
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
                                        <td><a href="?tab=projects&delete_project=<?php echo $proj['id']; ?>"
                                                class="btn-sm btn-danger" onclick="return confirm('Delete this project?')"><i
                                                    class="fas fa-trash"></i></a></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'internships'): ?>
            <div class="admin-card fade-in">
                <h2><i class="fas fa-graduation-cap"></i> Manage Internships</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" required>
                        </div>
                        <div class="form-group">
                            <label>Role / Position</label>
                            <input type="text" name="role" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Duration (e.g. Jan 2023 - Present)</label>
                        <input type="text" name="duration" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Company Logo</label>
                        <input type="file" name="company_logo">
                    </div>
                    <button type="submit" name="add_internship" class="btn-primary">Add Internship</button>
                </form>

                <div style="margin-top: 3rem;">
                    <h3>Experience History</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Role</th>
                                    <th>Duration</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($internships as $intern): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <?php if ($intern['company_logo']): ?>
                                                    <img src="../uploads/<?php echo $intern['company_logo']; ?>"
                                                        style="width: 30px; height: 30px; border-radius: 4px; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-building" style="color: var(--text-light);"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($intern['company_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($intern['role']); ?></td>
                                        <td><?php echo htmlspecialchars($intern['duration']); ?></td>
                                        <td><a href="?tab=internships&delete_internship=<?php echo $intern['id']; ?>"
                                                class="btn-sm btn-danger" onclick="return confirm('Delete this internship?')"><i
                                                    class="fas fa-trash"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
                                <tr>
                                    <td style="text-transform: capitalize; font-weight: 500;">
                                        <i class="fab fa-<?php echo strtolower($social['platform']); ?>"
                                            style="margin-right: 8px; color: var(--text-light);"></i>
                                        <?php echo htmlspecialchars($social['platform']); ?>
                                    </td>
                                    <td><a href="<?php echo $social['url']; ?>" target="_blank"
                                            style="color: var(--accent-dark); text-decoration: none;"><?php echo htmlspecialchars($social['url']); ?></a>
                                    </td>
                                    <td><a href="?tab=social&delete_social=<?php echo $social['id']; ?>"
                                            class="btn-sm btn-danger" onclick="return confirm('Delete this link?')"><i
                                                class="fas fa-trash"></i></a></td>
                                </tr>
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
        // Simple fade-in animation trigger if needed or just CSS
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElems = document.querySelectorAll('.fade-in');
            fadeElems.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('visible');
                }, index * 100);
            });
        });
    </script>
</body>

</html>