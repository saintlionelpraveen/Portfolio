<?php
// index.php
require_once 'config/config.php';
// Sanitize user input
function clean_input($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Get Hero Section Data
function get_hero_data()
{
    global $conn;
    $sql = "SELECT * FROM hero LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
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
    return $result ? $result : null;
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
        if ($result && $result->num_rows > 0) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
    }
    return [];
}

// Fetch Data
$hero = get_hero_data();
$socials = get_social_links();
$internships = get_internships();
$about_query = $conn->query("SELECT * FROM about LIMIT 1");
$about = ($about_query && $about_query->num_rows > 0) ? $about_query->fetch_assoc() : ['content' => '', 'profile_image' => ''];

// Handle Contact Form
$msg_sent = false;
$msg_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $message = clean_input($_POST['message']);

    if (!empty($name) && !empty($email) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);
        if ($stmt->execute()) {
            $msg_sent = true;
        } else {
            $msg_error = "Error sending message.";
        }
    } else {
        $msg_error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $hero['title']; ?> - Portfolio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <!-- Navbar -->
    <nav>
        <a href="#home" class="logo"><?php echo get_site_content('navbar_logo'); ?></a>
        <ul class="nav-links">

            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#internships">Internships</a></li>
            <li><a href="#projects">Works</a></li>
            <li><a href="#contact" class="nav-btn">Contact <i class="fas fa-arrow-right"></i></a></li>
        </ul>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-text fade-in">
            <h1><?php echo htmlspecialchars($hero['title']); ?></h1>
            <p><?php echo htmlspecialchars($hero['subtitle']); ?></p>

            <div class="cta-group">
                <a href="#contact" class="btn-primary"><?php echo get_site_content('hire_me_text'); ?> <i
                        class="fas fa-arrow-right"></i></a>

                <div class="client-stats">
                    <div class="avatars">
                        <!-- Placeholders - simple colored circles or unsplash user images -->
                        <img src="https://ui-avatars.com/api/?name=John+Doe&background=0D8ABC&color=fff" alt="Client">
                        <img src="https://ui-avatars.com/api/?name=Jane+Smith&background=3b82f6&color=fff" alt="Client">
                        <img src="https://ui-avatars.com/api/?name=Mike+Ross&background=6366f1&color=fff" alt="Client">
                    </div>
                    <div class="stats-text">
                        <?php echo get_site_content('clients_count'); ?>
                        <span><?php echo get_site_content('clients_subtext'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-visual fade-in">
            <div class="hero-bg-blob"></div>
            <?php if (!empty($about['profile_image'])): ?>
                <img src="uploads/<?php echo $about['profile_image']; ?>" alt="Profile" class="profile-img">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=Praveen&size=500&background=random" alt="Profile"
                    class="profile-img">
            <?php endif; ?>

            <!-- Floating Badges -->
            <div class="floating-badge badge-1">
                <i class="fas fa-palette"></i> <?php echo get_site_content('hero_badge_1'); ?>
            </div>
            <div class="floating-badge badge-2">
                <i class="fas fa-code"></i> <?php echo get_site_content('hero_badge_2'); ?>
            </div>
            <div class="floating-badge badge-3">
                <i class="fas fa-layer-group"></i> <?php echo get_site_content('hero_badge_3'); ?>
            </div>
        </div>
    </section>

    <!-- Marquee Strip -->
    <div class="marquee-container">
        <div class="marquee-content">
            <div class="marquee-item"><i class="fas fa-star"></i> Design</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Develop</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Discover</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Design</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Develop</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Discover</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Design</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Develop</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Discover</div>
            <!-- Duplicate for seamless scroll -->
            <div class="marquee-item"><i class="fas fa-star"></i> Design</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Develop</div>
            <div class="marquee-item"><i class="fas fa-star"></i> Discover</div>
        </div>
    </div>

    <!-- About Section -->
    <section id="about">
        <h2 class="fade-in">About Me</h2>
        <div class="about-container fade-in">
            <div class="about-text">
                <p style="font-size: 1.2rem; line-height: 1.8; color: var(--text-light);">
                    <?php echo nl2br(htmlspecialchars(isset($about['content']) ? $about['content'] : '')); ?>
                </p>
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <?php
                    if ($socials):
                        $socials->data_seek(0);
                        while ($link = $socials->fetch_assoc()):
                            ?>
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"
                                style="font-size: 1.5rem; color: var(--text-color);"><i
                                    class="fab fa-<?php echo strtolower($link['platform']); ?>"></i></a>
                        <?php endwhile;
                    endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Internships Section -->
    <section id="internships" style="background: var(--bg-hover);">
        <h2 class="fade-in">Internships & Experience</h2>
        <div class="projects-grid"
            style="display: flex; flex-direction: column; max-width: 800px; margin: 0 auto; gap: 1.5rem;">
            <?php foreach ($internships as $intern): ?>
                <div class="project-card fade-in"
                    style="display: flex; gap: 1.5rem; text-align: left; align-items: flex-start;">
                    <?php if ($intern['company_logo']): ?>
                        <img src="uploads/<?php echo $intern['company_logo']; ?>" alt="Logo"
                            style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div
                            style="width: 60px; height: 60px; background: #e0f2fe; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--accent-color); font-size: 1.5rem;">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    <?php endif; ?>

                    <div style="flex: 1;">
                        <h3 style="margin-bottom: 0.2rem; color: var(--text-color);">
                            <?php echo htmlspecialchars($intern['company_name']); ?>
                        </h3>
                        <p style="font-weight: 600; color: var(--accent-color); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($intern['role']); ?> <span
                                style="font-weight: normal; color: var(--text-light); font-size: 0.9rem;">•
                                <?php echo htmlspecialchars($intern['duration']); ?></span>
                        </p>
                        <p style="font-size: 0.95rem; color: var(--text-light); line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($intern['description'])); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Skills Section -->
    <section id="skills">
        <h2 class="fade-in">My Expertise</h2>
        <div class="skills-grid">
            <?php
            $skills_query = $conn->query("SELECT * FROM skills ORDER BY percentage DESC");
            if ($skills_query && $skills_query->num_rows > 0):
                while ($skill = $skills_query->fetch_assoc()):
                    ?>
                    <div class="skill-card fade-in">
                        <h3><?php echo htmlspecialchars($skill['skill_name']); ?></h3>
                        <div class="progress-bar">
                            <div class="progress" data-width="<?php echo $skill['percentage']; ?>"></div>
                        </div>
                    </div>
                <?php endwhile;
            else: ?>
                <p>Skills information coming soon.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Projects Section -->
    <section id="projects">
        <h2 class="fade-in">Featured Works</h2>
        <div class="projects-grid">
            <?php
            $projects_query = $conn->query("SELECT *, YEAR(created_at) as project_year FROM projects ORDER BY created_at DESC");
            if ($projects_query && $projects_query->num_rows > 0):
                while ($proj = $projects_query->fetch_assoc()):
                    ?>
                    <div class="project-card fade-in">
                        <div class="project-icon-wrapper">
                            <?php if ($proj['image']): ?>
                                <img src="uploads/<?php echo $proj['image']; ?>"
                                    alt="<?php echo htmlspecialchars($proj['title']); ?>">
                            <?php else: ?>
                                <i class="fas fa-cube" style="font-size: 2rem; color: var(--accent-dark);"></i>
                            <?php endif; ?>
                        </div>

                        <div class="project-header">
                            <h3><?php echo htmlspecialchars($proj['title']); ?></h3>
                            <span class="project-year"><?php echo $proj['project_year']; ?></span>
                        </div>

                        <p><?php echo htmlspecialchars($proj['description']); ?></p>

                        <div class="project-tags">
                            <?php
                            $techs = explode(',', $proj['tech_stack']);
                            foreach ($techs as $tech) {
                                if (trim($tech)) {
                                    echo '<span class="project-tag">' . htmlspecialchars(trim($tech)) . '</span>';
                                }
                            }
                            ?>
                        </div>

                        <div class="project-links">
                            <?php if ($proj['github_link']): ?>
                                <a href="<?php echo $proj['github_link']; ?>" target="_blank" class="project-link">
                                    <i class="fab fa-github"></i> Code
                                </a>
                            <?php endif; ?>
                            <?php if ($proj['demo_link']): ?>
                                <a href="<?php echo $proj['demo_link']; ?>" target="_blank" class="project-link">
                                    <i class="fas fa-external-link-alt"></i> Live Demo
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile;
            else: ?>
                <p style="text-align: center; width: 100%; color: var(--text-light);">Projects showcase coming soon.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact">
        <h2 class="fade-in">Let's Work Together</h2>
        <div class="contact-form fade-in">
            <?php if ($msg_sent): ?>
                <p style="color: #10b981; text-align: center; margin-bottom: 1rem; font-weight: 600;">Message sent
                    successfully!</p>
            <?php elseif ($msg_error): ?>
                <p style="color: #ef4444; text-align: center; margin-bottom: 1rem; font-weight: 600;">
                    <?php echo $msg_error; ?>
                </p>
            <?php endif; ?>
            <form method="POST" action="#contact">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5" required></textarea>
                </div>
                <button type="submit" name="send_message" class="btn-primary"
                    style="width: 100%; border: none; cursor: pointer;">Send Message</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="fade-in">
        <h1 class="logo" style="margin-bottom: 1rem; display: inline-block;">MGR.</h1>
        <p style="color: var(--text-light);">&copy; <?php echo date('Y'); ?>. All rights reserved.</p>
    </footer>

    <script src="assets/js/main.js"></script>
</body>

</html>