<?php
// login.php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: admin/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Portfolio</title>
    <meta name="description" content="Secure admin login for Praveen's Portfolio dashboard">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="admin/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="login-body">

    <!-- Particle canvas for parallax effect -->
    <canvas class="login-particles" id="particleCanvas"></canvas>

    <div class="login-card fade-in" id="loginCard">
        <!-- Brand -->
        <div class="login-brand">
            <div class="login-avatar">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Welcome Back</h2>
        </div>
        <p>Enter your credentials to access the dashboard</p>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="admin/auth.php" method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required
                    autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required
                    autocomplete="current-password">
            </div>
            <button type="submit" class="btn-primary" id="loginBtn">
                <span>Sign In</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Portfolio
        </a>
    </div>

    <script>
        // ── Particle System with Parallax ──
        (function () {
            const canvas = document.getElementById('particleCanvas');
            const ctx = canvas.getContext('2d');
            let particles = [];
            let mouse = { x: 0, y: 0 };
            let animFrame;

            function resize() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }

            class Particle {
                constructor() {
                    this.reset();
                }
                reset() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 2.5 + 0.5;
                    this.speedX = (Math.random() - 0.5) * 0.4;
                    this.speedY = (Math.random() - 0.5) * 0.4;
                    this.opacity = Math.random() * 0.4 + 0.1;
                    this.depth = Math.random() * 0.5 + 0.5; // Parallax depth
                }
                update() {
                    // Parallax mouse influence
                    const dx = (mouse.x - canvas.width / 2) * 0.01 * this.depth;
                    const dy = (mouse.y - canvas.height / 2) * 0.01 * this.depth;

                    this.x += this.speedX + dx * 0.02;
                    this.y += this.speedY + dy * 0.02;

                    if (this.x < -10 || this.x > canvas.width + 10 ||
                        this.y < -10 || this.y > canvas.height + 10) {
                        this.reset();
                    }
                }
                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(99, 102, 241, ${this.opacity})`;
                    ctx.fill();
                }
            }

            function init() {
                resize();
                particles = [];
                const count = Math.min(80, Math.floor((canvas.width * canvas.height) / 12000));
                for (let i = 0; i < count; i++) {
                    particles.push(new Particle());
                }
            }

            function connectParticles() {
                for (let a = 0; a < particles.length; a++) {
                    for (let b = a + 1; b < particles.length; b++) {
                        const dx = particles[a].x - particles[b].x;
                        const dy = particles[a].y - particles[b].y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 150) {
                            const opacity = (1 - dist / 150) * 0.12;
                            ctx.beginPath();
                            ctx.strokeStyle = `rgba(99, 102, 241, ${opacity})`;
                            ctx.lineWidth = 0.5;
                            ctx.moveTo(particles[a].x, particles[a].y);
                            ctx.lineTo(particles[b].x, particles[b].y);
                            ctx.stroke();
                        }
                    }
                }
            }

            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach(p => { p.update(); p.draw(); });
                connectParticles();
                animFrame = requestAnimationFrame(animate);
            }

            window.addEventListener('resize', () => { resize(); init(); });
            window.addEventListener('mousemove', (e) => {
                mouse.x = e.clientX;
                mouse.y = e.clientY;
            });

            init();
            animate();
        })();

        // ── Card entrance animation ──
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.getElementById('loginCard').classList.add('visible');
            }, 100);

            // Focus animation on first input
            setTimeout(() => {
                document.getElementById('username').focus();
            }, 800);
        });

        // ── Button loading state on submit ──
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.8';
        });
    </script>
</body>

</html>