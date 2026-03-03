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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ── All login styles inline: zero external CSS dependency ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #06081a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }

        /* Animated gradient orbs */
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.5;
            z-index: 0;
        }
        .bg-orb-1 {
            width: 500px; height: 500px;
            background: rgba(99, 102, 241, 0.25);
            top: -15%; left: -10%;
            animation: orbFloat1 12s ease-in-out infinite alternate;
        }
        .bg-orb-2 {
            width: 400px; height: 400px;
            background: rgba(139, 92, 246, 0.2);
            bottom: -10%; right: -10%;
            animation: orbFloat2 14s ease-in-out infinite alternate;
        }
        .bg-orb-3 {
            width: 300px; height: 300px;
            background: rgba(59, 130, 246, 0.15);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation: orbFloat3 10s ease-in-out infinite alternate;
        }

        @keyframes orbFloat1 {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(60px, 40px) scale(1.1); }
        }
        @keyframes orbFloat2 {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(-50px, -30px) scale(1.15); }
        }
        @keyframes orbFloat3 {
            0% { transform: translate(-50%, -50%) scale(1); }
            100% { transform: translate(-40%, -60%) scale(0.9); }
        }

        /* Particle canvas */
        #particleCanvas {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }

        /* Grid pattern overlay */
        .grid-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
        }

        /* Login card */
        .login-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            margin: 1.5rem;
            padding: 3rem 2.5rem 2.5rem;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.5),
                0 0 100px rgba(99, 102, 241, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            color: #fff;
            opacity: 0;
            transform: translateY(30px) scale(0.97);
            animation: cardEntry 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards;
        }

        @keyframes cardEntry {
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Glow border */
        .login-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 29px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.4), transparent 40%, transparent 60%, rgba(139, 92, 246, 0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.5s;
        }
        .login-card:hover::before { opacity: 1; }

        /* Brand */
        .login-brand { text-align: center; margin-bottom: 2rem; }

        .login-avatar {
            width: 68px; height: 68px;
            margin: 0 auto 1.2rem;
            border-radius: 20px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem; color: #fff;
            position: relative;
            animation: avatarFloat 4s ease-in-out infinite;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4);
        }
        @keyframes avatarFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .login-avatar::after {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 24px;
            border: 2px solid rgba(99, 102, 241, 0.25);
            animation: ringPulse 3s ease-in-out infinite;
        }
        @keyframes ringPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.1); opacity: 0.15; }
        }

        .login-card h2 {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #ffffff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.88rem;
            margin-top: 0.5rem;
            text-align: center;
            margin-bottom: 0;
            line-height: 1.5;
        }

        /* Divider */
        .login-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
            margin: 1.8rem 0;
        }

        /* Form */
        .login-form-group {
            margin-bottom: 1.3rem;
        }
        .login-form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            transition: color 0.3s;
        }
        .login-input-wrap {
            position: relative;
        }
        .login-input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.2);
            font-size: 0.9rem;
            transition: color 0.3s;
            z-index: 2;
        }
        .login-input-wrap input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            color: #fff;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .login-input-wrap input::placeholder {
            color: rgba(255, 255, 255, 0.18);
        }
        .login-input-wrap input:focus {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.06);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12), 0 4px 20px rgba(99, 102, 241, 0.08);
        }
        .login-input-wrap input:focus + i,
        .login-input-wrap:focus-within i {
            color: #818cf8;
        }
        .login-form-group:focus-within label {
            color: #818cf8;
        }

        /* Button */
        .login-btn {
            width: 100%;
            padding: 0.95rem 1.5rem;
            margin-top: 0.8rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.35);
            letter-spacing: 0.02em;
        }
        .login-btn::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.12), transparent);
            animation: shimmer 3.5s ease-in-out infinite;
        }
        @keyframes shimmer {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 36px rgba(99, 102, 241, 0.5);
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }
        .login-btn:active {
            transform: translateY(0) scale(0.98);
        }

        /* Error alert */
        .login-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.3rem;
            animation: shakeIn 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97);
        }
        @keyframes shakeIn {
            0% { transform: translateX(-10px); opacity: 0; }
            20% { transform: translateX(8px); }
            40% { transform: translateX(-6px); }
            60% { transform: translateX(4px); }
            80% { transform: translateX(-2px); }
            100% { transform: translateX(0); opacity: 1; }
        }

        /* Back link */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.8rem;
            padding-top: 1.3rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        .back-link:hover { color: rgba(255, 255, 255, 0.7); }
        .back-link i { margin-right: 0.3rem; }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card { padding: 2.2rem 1.5rem 2rem; margin: 1rem; }
            .login-avatar { width: 56px; height: 56px; font-size: 1.4rem; }
            .login-card h2 { font-size: 1.4rem; }
        }
    </style>
</head>

<body>
    <!-- Background effects -->
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>
    <div class="grid-overlay"></div>
    <canvas id="particleCanvas"></canvas>

    <div class="login-card">
        <div class="login-brand">
            <div class="login-avatar">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Welcome Back</h2>
            <p class="login-subtitle">Sign in to your admin dashboard</p>
        </div>

        <div class="login-divider"></div>

        <?php if (isset($_GET['error'])): ?>
                <div class="login-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
        <?php endif; ?>

        <form action="admin/auth.php" method="POST" id="loginForm">
            <div class="login-form-group">
                <label for="username">Username</label>
                <div class="login-input-wrap">
                    <input type="text" id="username" name="username" placeholder="Enter your username" required
                        autocomplete="username">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="login-form-group">
                <label for="password">Password</label>
                <div class="login-input-wrap">
                    <input type="password" id="password" name="password" placeholder="••••••••" required
                        autocomplete="current-password">
                    <i class="fas fa-lock"></i>
                </div>
            </div>
            <button type="submit" class="login-btn" id="loginBtn">
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
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let particles = [];
            let mouse = { x: window.innerWidth / 2, y: window.innerHeight / 2 };

            function resize() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }

            class Particle {
                constructor() { this.reset(); }
                reset() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 2 + 0.5;
                    this.speedX = (Math.random() - 0.5) * 0.3;
                    this.speedY = (Math.random() - 0.5) * 0.3;
                    this.opacity = Math.random() * 0.5 + 0.1;
                    this.depth = Math.random() * 0.5 + 0.5;
                }
                update() {
                    const dx = (mouse.x - canvas.width / 2) * 0.008 * this.depth;
                    const dy = (mouse.y - canvas.height / 2) * 0.008 * this.depth;
                    this.x += this.speedX + dx * 0.03;
                    this.y += this.speedY + dy * 0.03;
                    if (this.x < -20 || this.x > canvas.width + 20 ||
                        this.y < -20 || this.y > canvas.height + 20) this.reset();
                }
                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(129, 140, 248, ${this.opacity})`;
                    ctx.fill();
                }
            }

            function init() {
                resize();
                particles = [];
                const count = Math.min(70, Math.floor((canvas.width * canvas.height) / 15000));
                for (let i = 0; i < count; i++) particles.push(new Particle());
            }

            function connectParticles() {
                for (let a = 0; a < particles.length; a++) {
                    for (let b = a + 1; b < particles.length; b++) {
                        const dx = particles[a].x - particles[b].x;
                        const dy = particles[a].y - particles[b].y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 130) {
                            ctx.beginPath();
                            ctx.strokeStyle = `rgba(129, 140, 248, ${(1 - dist / 130) * 0.08})`;
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
                requestAnimationFrame(animate);
            }

            window.addEventListener('resize', () => { resize(); init(); });
            document.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });

            init();
            animate();
        })();

        // Button loading state
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Signing in...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.75';
        });

        // Auto-focus
        setTimeout(() => document.getElementById('username').focus(), 600);
    </script>
</body>

</html>