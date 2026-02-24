<?php
// login.php
session_start();
require_once 'config/config.php';

function clean_input($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    $sql = "SELECT id, username, password FROM admin_users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $db_username, $db_password);
            $stmt->fetch();
            if ($password === $db_password) {
                $_SESSION['admin_id'] = $id;
                $_SESSION['admin_username'] = $db_username;
                header("Location: admin/dashboard.php");
                exit();
            } else {
                $error_msg = "Invalid username or password.";
            }
        } else {
            $error_msg = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $error_msg = "Database error. Please try again later.";
    }
}

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
    <title>Admin Login — Praveen Portfolio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        /* LEFT PANEL */
        .login-left {
            flex: 1;
            background: linear-gradient(160deg, #0f172a 0%, #1e293b 55%, #0f172a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            position: relative;
            overflow: hidden;
        }

        .login-left .orb1 {
            position: absolute;
            top: -15%;
            left: -15%;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.22) 0%, transparent 70%);
            animation: orbFloat 9s ease-in-out infinite;
            pointer-events: none;
        }

        .login-left .orb2 {
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.18) 0%, transparent 70%);
            animation: orbFloat 12s ease-in-out infinite reverse;
            pointer-events: none;
        }

        .login-left .orb3 {
            position: absolute;
            top: 40%;
            right: 15%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.1) 0%, transparent 70%);
            animation: orbFloat 7s ease-in-out infinite 2s;
            pointer-events: none;
        }

        .grid-dots {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        @keyframes orbFloat {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            40% {
                transform: translate(25px, -20px) scale(1.06);
            }

            70% {
                transform: translate(-15px, 25px) scale(0.95);
            }
        }

        .brand-logo {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.03em;
            margin-bottom: 0.6rem;
            position: relative;
            z-index: 1;
        }

        .brand-logo span {
            background: linear-gradient(135deg, #6366f1, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-tagline {
            font-size: 1rem;
            color: #94a3b8;
            margin-bottom: 3rem;
            position: relative;
            z-index: 1;
            line-height: 1.65;
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            color: #cbd5e1;
            font-size: 0.92rem;
        }

        .feat-icon {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            background: rgba(99, 102, 241, 0.18);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #818cf8;
            font-size: 0.88rem;
        }

        .left-footer {
            position: absolute;
            bottom: 2rem;
            left: 4rem;
            color: #334155;
            font-size: 0.78rem;
            z-index: 1;
        }

        /* RIGHT PANEL */
        .login-right {
            width: 460px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 3.5rem;
        }

        .login-form-container {
            width: 100%;
            max-width: 370px;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.03em;
            margin-bottom: 0.35rem;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 0.92rem;
            margin-bottom: 2.5rem;
        }

        .lform-group {
            margin-bottom: 1.4rem;
        }

        .lform-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.55rem;
            letter-spacing: 0.01em;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.85rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrap input {
            width: 100%;
            padding: 0.82rem 1rem 0.82rem 2.7rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.92rem;
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #0f172a;
            transition: all 0.2s;
            outline: none;
        }

        .input-wrap input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .input-wrap input:focus~i {
            color: #6366f1;
        }

        .pass-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
            line-height: 1;
        }

        .pass-toggle:hover {
            color: #6366f1;
        }

        .btn-login {
            width: 100%;
            padding: 0.88rem;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.97rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            transition: all 0.3s;
            margin-top: 0.6rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.38);
            background: linear-gradient(135deg, #4f46e5, #6366f1);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            color: #64748b;
            text-decoration: none;
            font-size: 0.88rem;
            margin-top: 2rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #6366f1;
        }

        .lerror {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        @media (max-width: 860px) {
            .login-left {
                display: none;
            }

            .login-right {
                width: 100%;
                background: #fff;
            }

            body {
                background: #fff;
            }
        }
    </style>
</head>

<body>
    <!-- LEFT -->
    <div class="login-left">
        <div class="grid-dots"></div>
        <div class="orb1"></div>
        <div class="orb2"></div>
        <div class="orb3"></div>

        <div class="brand-logo">Praveen<span>.</span></div>
        <p class="brand-tagline">Your personal portfolio command center.<br>Manage everything from one place.</p>

        <ul class="feature-list">
            <li>
                <div class="feat-icon"><i class="fas fa-layer-group"></i></div> Manage projects, skills & experience
            </li>
            <li>
                <div class="feat-icon"><i class="fas fa-upload"></i></div> Upload images & media instantly
            </li>
            <li>
                <div class="feat-icon"><i class="fas fa-share-alt"></i></div> Control social links & branding
            </li>
            <li>
                <div class="feat-icon"><i class="fas fa-shield-alt"></i></div> Secure admin-only access
            </li>
        </ul>

        <div class="left-footer">&copy; <?php echo date('Y'); ?> Praveen Portfolio</div>
    </div>

    <!-- RIGHT -->
    <div class="login-right">
        <div class="login-form-container">
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to access the admin panel</p>

            <?php if (isset($error_msg)): ?>
                <div class="lerror"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="lform-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username" placeholder="Enter your username" required
                            autocomplete="username">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="lform-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" placeholder="••••••••" required
                            autocomplete="current-password" style="padding-right:3rem;">
                        <i class="fas fa-lock"></i>
                        <span class="pass-toggle" id="togglePassword"><i class="fas fa-eye" id="toggleIcon"></i></span>
                    </div>
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In</button>
            </form>

            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Portfolio</a>
        </div>
    </div>

    <script>
        const tog = document.getElementById('togglePassword');
        const pwd = document.getElementById('password');
        const ico = document.getElementById('toggleIcon');
        tog.addEventListener('click', () => {
            const show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            ico.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
        document.querySelectorAll('.input-wrap input').forEach(inp => {
            const icon = inp.nextElementSibling;
            if (!icon || !icon.classList.contains('fa-user') && !icon.classList.contains('fas')) return;
            inp.addEventListener('focus', () => { if (icon && icon.tagName === 'I') icon.style.color = '#6366f1'; });
            inp.addEventListener('blur', () => { if (icon && icon.tagName === 'I') icon.style.color = '#94a3b8'; });
        });
    </script>
</body>

</html>