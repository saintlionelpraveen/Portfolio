<?php
// index.php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Fetch Data
$hero = get_hero_data();
$socials = get_social_links();
$about = $conn->query("SELECT * FROM about LIMIT 1")->fetch_assoc();

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
        <a href="#home" class="logo">Praveen</a>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
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
                <a href="#contact" class="btn-primary">Hire Me! <i class="fas fa-arrow-right"></i></a>

                <div class="client-stats">
                    <div class="avatars">
                        <!-- Placeholders - simple colored circles or unsplash user images -->
                        <img src="https://ui-avatars.com/api/?name=John+Doe&background=0D8ABC&color=fff" alt="Client">
                        <img src="https://ui-avatars.com/api/?name=Jane+Smith&background=3b82f6&color=fff" alt="Client">
                        <img src="https://ui-avatars.com/api/?name=Mike+Ross&background=6366f1&color=fff" alt="Client">
                    </div>
                    <div class="stats-text">
                        1K+ Clients
                        <span>Worldwide</span>
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
                <i class="fas fa-palette"></i> Praveen
            </div>
            <div class="floating-badge badge-2">
                <i class="fas fa-code"></i> ci
            </div>
            <div class="floating-badge badge-3">
                <i class="fas fa-layer-group"></i> cd
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
                    $socials->data_seek(0);
                    while ($link = $socials->fetch_assoc()):
                        ?>
                        <a href="<?php echo $link['url']; ?>" target="_blank"
                            style="font-size: 1.5rem; color: var(--text-color);"><i
                                class="fab fa-<?php echo strtolower($link['platform']); ?>"></i></a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Skills Section -->
    <section id="skills">
        <h2 class="fade-in">My Expertise</h2>
        <div class="skills-grid">
            <?php
            $skills_query = $conn->query("SELECT * FROM skills ORDER BY percentage DESC");
            while ($skill = $skills_query->fetch_assoc()):
                ?>
                <div class="skill-card fade-in">
                    <h3><?php echo htmlspecialchars($skill['skill_name']); ?></h3>
                    <div class="progress-bar">
                        <div class="progress" data-width="<?php echo $skill['percentage']; ?>"></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- Projects Section -->
    <section id="projects">
        <h2 class="fade-in">Featured Works</h2>
        <div class="projects-grid">
            <?php
            $projects_query = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");
            while ($proj = $projects_query->fetch_assoc()):
                ?>
                <div class="project-card fade-in">
                    <?php if ($proj['image']): ?>
                        <img src="uploads/<?php echo $proj['image']; ?>" alt="<?php echo htmlspecialchars($proj['title']); ?>"
                            class="project-img">
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($proj['title']); ?></h3>
                    <p><?php echo htmlspecialchars($proj['description']); ?></p>
                    <div style="margin-top: 1rem;">
                        <?php
                        $techs = explode(',', $proj['tech_stack']);
                        foreach ($techs as $tech) {
                            echo '<span style="background: #e0f2fe; padding: 0.2rem 0.8rem; border-radius: 50px; font-size: 0.8rem; margin-right: 0.5rem; color: #0284c7; font-weight: 600;">' . trim($tech) . '</span>';
                        }
                        ?>
                    </div>
                    <div class="project-links">
                        <?php if ($proj['github_link']): ?>
                            <a href="<?php echo $proj['github_link']; ?>" target="_blank" class="btn">GitHub</a>
                        <?php endif; ?>
                        <?php if ($proj['demo_link']): ?>
                            <a href="<?php echo $proj['demo_link']; ?>" target="_blank" class="btn">View Live</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
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
                    <?php echo $msg_error; ?></p>
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