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

// Get Internships + fellowship details
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

function get_fellowship_skills()
{
    global $conn;
    try {
        $r = $conn->query("SELECT fs.*, i.company_name, i.role FROM fellowship_skills fs JOIN internships i ON i.id=fs.internship_id ORDER BY i.created_at DESC, fs.created_at ASC");
        if ($r)
            return $r->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
    }
    return [];
}

function get_fellowship_frameworks()
{
    global $conn;
    try {
        $r = $conn->query("SELECT fw.*, i.company_name, i.role FROM fellowship_frameworks fw JOIN internships i ON i.id=fw.internship_id ORDER BY i.created_at DESC, fw.created_at ASC");
        if ($r)
            return $r->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
    }
    return [];
}

function get_fellowship_projects()
{
    global $conn;
    try {
        $r = $conn->query("SELECT fp.*, i.company_name, i.role FROM fellowship_projects fp JOIN internships i ON i.id=fp.internship_id ORDER BY i.created_at DESC, fp.created_at ASC");
        if ($r)
            return $r->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
    }
    return [];
}

// Fetch Data
$hero = get_hero_data();
$socials = get_social_links();
$internships = get_internships();
$fellowship_skills = get_fellowship_skills();
$fellowship_frameworks = get_fellowship_frameworks();
$fellowship_projects = get_fellowship_projects();
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
            <li><a href="#fellowship">Fellowship</a></li>
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

    <!-- Fellowship Section -->
    <section id="fellowship">
        <h2 class="fade-in">Fellowship <span style="color:var(--accent-dark)">&amp; Experience</span></h2>

        <?php if (!empty($internships)):
            // Build per-fellowship lookup maps
            $fs_by_intern = $fw_by_intern = $fp_by_intern = [];
            foreach ($fellowship_skills     as $r) { $fs_by_intern[$r['internship_id']][] = $r; }
            foreach ($fellowship_frameworks as $r) { $fw_by_intern[$r['internship_id']][] = $r; }
            foreach ($fellowship_projects   as $r) { $fp_by_intern[$r['internship_id']][] = $r; }

            foreach ($internships as $fidx => $intern):
                $fid      = $intern['id'];
                $f_skills = $fs_by_intern[$fid] ?? [];
                $f_fworks = $fw_by_intern[$fid] ?? [];
                $f_projs  = $fp_by_intern[$fid] ?? [];
                $has_data = !empty($f_skills) || !empty($f_fworks) || !empty($f_projs);
                $uid      = 'f' . $fid; // unique prefix per entry
        ?>
        <div class="fellowship-hero-row fade-in<?php echo $fidx % 2 === 1 ? ' fellowship-hero-row--reverse' : ''; ?>">

            <!-- LEFT: Fellowship Image -->
            <div class="fellowship-img-col">
                <div class="fellowship-img-wrap">
                    <?php if (!empty($intern['fellowship_image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($intern['fellowship_image']); ?>"
                             alt="<?php echo htmlspecialchars($intern['company_name']); ?> fellowship"
                             class="fellowship-img">
                    <?php elseif (!empty($intern['company_logo'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($intern['company_logo']); ?>"
                             alt="<?php echo htmlspecialchars($intern['company_name']); ?>"
                             class="fellowship-img fellowship-img--logo">
                    <?php else: ?>
                        <div class="fellowship-img-placeholder">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                    <?php endif; ?>
                    <div class="fellowship-img-badge">
                        <i class="fas fa-star"></i>
                        <span>Fellowship</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Content -->
            <div class="fellowship-content-col">
                <div class="fellowship-meta-row">
                    <?php if (!empty($intern['company_logo'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($intern['company_logo']); ?>"
                             class="fellowship-company-logo" alt="logo">
                    <?php endif; ?>
                    <span class="fellowship-company-name"><?php echo htmlspecialchars($intern['company_name']); ?></span>
                    <span class="fellowship-duration-pill"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($intern['duration']); ?></span>
                </div>

                <h3 class="fellowship-role"><?php echo htmlspecialchars($intern['role']); ?></h3>

                <?php if (!empty($intern['description'])): ?>
                <p class="fellowship-description"><?php echo nl2br(htmlspecialchars($intern['description'])); ?></p>
                <?php endif; ?>

                <?php if ($has_data): ?>
                <!-- Filter Tabs -->
                <div class="fellowship-tabs" id="tabs-<?php echo $uid; ?>">
                    <button class="ftab active" data-group="<?php echo $uid; ?>" data-target="<?php echo $uid; ?>-skills">
                        <span class="ftab-icon">&#9889;</span><span>Skills</span>
                        <span class="ftab-count"><?php echo count($f_skills); ?></span>
                    </button>
                    <button class="ftab" data-group="<?php echo $uid; ?>" data-target="<?php echo $uid; ?>-frameworks">
                        <span class="ftab-icon">&#128193;</span><span>Frameworks</span>
                        <span class="ftab-count"><?php echo count($f_fworks); ?></span>
                    </button>
                    <button class="ftab" data-group="<?php echo $uid; ?>" data-target="<?php echo $uid; ?>-projects">
                        <span class="ftab-icon">&#128187;</span><span>Projects</span>
                        <span class="ftab-count"><?php echo count($f_projs); ?></span>
                    </button>
                </div>

                <!-- SKILLS PANEL -->
                <div class="ftab-panel active" id="<?php echo $uid; ?>-skills">
                    <?php if (!empty($f_skills)): ?>
                    <div class="fs-skills-grid">
                        <?php foreach ($f_skills as $fs):
                            $prof_pct   = ['Beginner'=>33,'Intermediate'=>66,'Advanced'=>100][$fs['proficiency']] ?? 33;
                            $prof_color = ['Beginner'=>'#f59e0b','Intermediate'=>'#3b82f6','Advanced'=>'#10b981'][$fs['proficiency']] ?? '#94a3b8';
                        ?>
                        <div class="fs-skill-card">
                            <div class="fs-skill-glow" style="--glow:<?php echo $prof_color; ?>;">
                                <div class="fs-skill-top">
                                    <span class="fs-skill-name"><?php echo htmlspecialchars($fs['skill_name']); ?></span>
                                    <span class="fs-prof-badge" style="background:<?php echo $prof_color; ?>20;color:<?php echo $prof_color; ?>;border:1px solid <?php echo $prof_color; ?>40;"><?php echo $fs['proficiency']; ?></span>
                                </div>
                                <?php if (!empty($fs['description'])): ?>
                                <p class="fs-skill-desc"><?php echo htmlspecialchars($fs['description']); ?></p>
                                <?php endif; ?>
                                <div class="fs-prof-bar"><div class="fs-prof-fill" style="width:<?php echo $prof_pct; ?>%;background:<?php echo $prof_color; ?>;box-shadow:0 0 8px <?php echo $prof_color; ?>60;"></div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="ftab-empty">No skills added yet.</p><?php endif; ?>
                </div>

                <!-- FRAMEWORKS PANEL -->
                <div class="ftab-panel" id="<?php echo $uid; ?>-frameworks">
                    <?php if (!empty($f_fworks)): ?>
                    <div class="fs-fw-grid">
                        <?php foreach ($f_fworks as $fw):
                            $cat_colors=['Frontend'=>['bg'=>'#fef3c7','dot'=>'#f59e0b'],'Backend'=>['bg'=>'#dbeafe','dot'=>'#3b82f6'],'Mobile'=>['bg'=>'#fce7f3','dot'=>'#ec4899'],'DevOps'=>['bg'=>'#dcfce7','dot'=>'#22c55e'],'Database'=>['bg'=>'#ede9fe','dot'=>'#8b5cf6'],'AI/ML'=>['bg'=>'#fee2e2','dot'=>'#ef4444'],'Other'=>['bg'=>'#f1f5f9','dot'=>'#64748b']];
                            $cat=$fw['category']??'Other'; $cc=$cat_colors[$cat]??$cat_colors['Other'];
                        ?>
                        <div class="fs-fw-card">
                            <div class="fs-fw-terminal">
                                <div class="fs-fw-dots"><span></span><span></span><span></span></div>
                                <div class="fs-fw-category" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['dot']; ?>;"><span class="fs-fw-cat-dot" style="background:<?php echo $cc['dot']; ?>"></span><?php echo htmlspecialchars($cat); ?></div>
                            </div>
                            <div class="fs-fw-body">
                                <div class="fs-fw-name"><?php echo htmlspecialchars($fw['framework_name']); ?></div>
                                <?php if (!empty($fw['description'])): ?><p class="fs-fw-desc"><?php echo htmlspecialchars($fw['description']); ?></p><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="ftab-empty">No frameworks added yet.</p><?php endif; ?>
                </div>

                <!-- PROJECTS PANEL -->
                <div class="ftab-panel" id="<?php echo $uid; ?>-projects">
                    <?php if (!empty($f_projs)): ?>
                    <div class="fs-proj-list">
                        <?php foreach ($f_projs as $idx => $fp): ?>
                        <div class="fs-proj-row">
                            <div class="fs-proj-num">0<?php echo $idx+1; ?></div>
                            <div class="fs-proj-content">
                                <div class="fs-proj-top">
                                    <h4 class="fs-proj-name"><?php echo htmlspecialchars($fp['project_name']); ?></h4>
                                    <div class="fs-proj-actions">
                                        <?php if (!empty($fp['github_link'])): ?><a href="<?php echo htmlspecialchars($fp['github_link']); ?>" target="_blank" class="fs-proj-gh"><i class="fab fa-github"></i> GitHub</a><?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($fp['description'])): ?><p class="fs-proj-desc"><?php echo htmlspecialchars($fp['description']); ?></p><?php endif; ?>
                                <?php if (!empty($fp['tech_used'])): ?>
                                <div class="fs-proj-techs">
                                    <?php foreach (array_filter(array_map('trim',explode(',',$fp['tech_used']))) as $t): ?><span class="fs-proj-tech"><?php echo htmlspecialchars($t); ?></span><?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p class="ftab-empty">No projects added yet.</p><?php endif; ?>
                </div>
                <?php endif; // has_data ?>

            </div><!-- .fellowship-content-col -->
        </div><!-- .fellowship-hero-row -->
        <?php endforeach; endif; ?>
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
                        <span class="skill-label">Expertise</span>
                        <h3><?php echo htmlspecialchars($skill['skill_name']); ?></h3>
                        <?php if (!empty($skill['description'])): ?>
                            <p class="skill-desc"><?php echo htmlspecialchars($skill['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($skill['tags'])): ?>
                            <div class="skill-tags">
                                <?php
                                $stags = array_filter(array_map('trim', explode(',', $skill['tags'])));
                                foreach ($stags as $stag) {
                                    echo '<span class="skill-tag">' . htmlspecialchars($stag) . '</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <span class="skill-percentage"><?php echo $skill['percentage']; ?>% Proficiency</span>
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
