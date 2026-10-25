<?php
require_once __DIR__ . '/config/db.php';

$error = '';
$notice = '';

// Check for session timeout notice or unauthorized switch
if (!empty($_SESSION['auth_notice'])) {
    $notice = $_SESSION['auth_notice'];
    unset($_SESSION['auth_notice']);
} elseif (isset($_GET['reason']) && $_GET['reason'] === 'expired') {
    $notice = 'Your session has expired due to 30 minutes of inactivity. Please sign in again.';
} elseif (isset($_GET['error']) && $_GET['error'] === 'unauthorized_switch') {
    $error = 'Unauthorized operation. You must be an authenticated administrator to switch active roles.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both your official government email and password.';
    } else {
        $user = db_row("
            SELECT u.*, r.slug AS role_slug, r.name AS role_name, o.name as org_name, o.code as org_code
            FROM users u 
            LEFT JOIN roles r ON r.id = u.role_id 
            LEFT JOIN organizations o ON o.id = u.organization_id
            WHERE u.email = ?", 
            [$email]
        );

        if ($user && ($user['status'] ?? 'active') !== 'active') {
            $error = 'Your government account is currently ' . htmlspecialchars($user['status']) . '. Please contact the system administrator.';
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            // Prevent Session Fixation attack
            session_regenerate_id(true);

            $_SESSION['user_id']          = (int)$user['id'];
            $_SESSION['user_name']        = $user['name'];
            $_SESSION['user_email']       = $user['email'];
            $_SESSION['user_role']        = $user['role_slug'] ?? 'super_admin';
            $_SESSION['user_role_name']   = $user['role_name'] ?? 'Administrator';
            $_SESSION['user_org_id']      = $user['organization_id'] ?? 2;
            $_SESSION['user_org_name']    = $user['org_name'] ?? 'Ministry of Finance & Economic Development';
            $_SESSION['user_org_code']    = $user['org_code'] ?? 'MOFED';
            $_SESSION['user_designation'] = $user['designation'] ?? 'Officer';
            $_SESSION['last_activity']    = time();

            db_execute("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);
            record_audit('Successful Login', 'Authentication', 'users', (string)$user['id'], null, "User logged in ({$user['role_slug']})");

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid official email or password credentials.';
            record_audit('Failed Login Attempt', 'Authentication', 'users', null, null, "Failed login attempt for email: {$email}");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Somaliland Government Financial Management Platform (SFGMIS)</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            /* Somaliland Government Official Green Theme (Matching Sidebar #123D2B) */
            --gov-green: #176838;
            --gov-green-dark: #0f4625;
            --gov-green-surface: #1e7a43;
            --gov-green-hover: #1b7a42;
            --gov-gold: #C8A84E;
            --gov-gold-hover: #b6953e;
            --gold-500: #C8A84E;
            --gold-600: #b6953e;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 24px;
        }
        .login-container {
            display: flex;
            background: linear-gradient(145deg, #176838 0%, #0f4625 100%);
            border: 1px solid rgba(200, 168, 78, 0.2);
            border-radius: 24px;
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 40px rgba(23, 104, 56, 0.35);
            min-height: 600px;
        }
        .login-left {
            flex: 1;
            padding: 56px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-right {
            flex: 1;
            background: linear-gradient(145deg, #176838 0%, #0f4625 100%);
            padding: 56px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            border-left: 1px solid rgba(255, 255, 255, 0.08);
        }
        .login-right::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at center, rgba(200, 168, 78, 0.14) 0%, transparent 70%);
            pointer-events: none;
        }
        .logo-box {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            text-align: center;
        }
        .brand-logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border-radius: 50%;
            clip-path: circle(49% at 50% 50%);
            background: transparent;
            filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.45));
        }
        h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.3rem;
            margin-bottom: 8px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 0.98rem;
            margin-bottom: 32px;
            line-height: 1.5;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #cbd5e1;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            width: 20px;
            height: 20px;
        }
        .form-control {
            width: 100%;
            background: rgba(8, 26, 18, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 16px 16px 16px 48px;
            color: #fff;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--gold-500);
            box-shadow: 0 0 0 4px rgba(200, 168, 78, 0.18);
            background: rgba(8, 26, 18, 0.85);
        }
        .form-control::placeholder {
            color: #6d8478;
        }
        .form-footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        .remember-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #94a3b8;
            cursor: pointer;
        }
        .remember-wrap input {
            accent-color: var(--gold-500);
        }
        .btn-login {
            background: var(--gold-500);
            color: #0c2a1e;
            border: none;
            border-radius: 12px;
            padding: 16px;
            width: 100%;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
        }
        .btn-login:hover {
            background: var(--gold-600);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -4px rgba(200, 168, 78, 0.35);
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .forgot-pass {
            color: var(--gold-500);
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-pass:hover {
            text-decoration: underline;
        }
        
        /* Right side visuals */
        .feature-icon {
            width: 64px;
            height: 64px;
            background: rgba(200, 168, 78, 0.12);
            border: 1px solid rgba(200, 168, 78, 0.22);
            color: var(--gold-500);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        .feature-icon svg {
            width: 32px;
            height: 32px;
        }
        .right-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.2rem;
            margin-bottom: 6px;
        }
        .right-sub {
            color: var(--gold-500);
            font-size: 0.88rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }
        .security-badge {
            margin-top: 28px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: rgba(18, 61, 43, 0.5);
            border: 1px solid rgba(200, 168, 78, 0.2);
            border-radius: 999px;
            font-size: 0.75rem;
            color: #d1fae5;
        }

        @media (max-width: 900px) {
            .login-right { display: none; }
            .login-container { max-width: 500px; margin: 24px; min-height: auto; }
            .login-left { padding: 40px 28px; }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Left Side: Login Form -->
        <div class="login-left">
            <div class="logo-box">
                <img id="loginBrandLogo" src="assets/images/LOGO.webp" alt="Somaliland National Emblem" class="brand-logo" onerror="this.style.display='none'">
            </div>
            
            <h1>Welcome Back</h1>
            <p class="subtitle">Please sign in to your official government workspace.</p>

            <?php if ($notice): ?>
            <div style="background:rgba(200,168,78,0.12); border:1px solid rgba(200,168,78,0.3); color:#fef08a; padding:12px 16px; border-radius:8px; font-size:0.875rem; margin-bottom:24px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="clock" style="width:18px;height:18px;color:var(--gold-500);flex-shrink:0;"></i>
                <span><?= htmlspecialchars($notice) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="error-msg">
                <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === '1'): ?>
            <div style="background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.3); color:#86efac; padding:12px 16px; border-radius:8px; font-size:0.875rem; margin-bottom:24px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="check-circle" style="width:18px;height:18px;color:#22c55e;"></i>
                <span>Password has been successfully updated. You may now sign in.</span>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label>Official Government Email</label>
                    <div class="input-wrap">
                        <i data-lucide="mail"></i>
                        <input type="email" name="email" class="form-control" placeholder="minister@sfgmis.gov.so" autocomplete="email" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i data-lucide="lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Enter assigned password" autocomplete="current-password" required>
                    </div>
                    <div class="form-footer-row">
                        <label class="remember-wrap">
                            <input type="checkbox" name="remember" checked>
                            <span>Remember terminal</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-pass">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Sign In <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
                </button>
            </form>

            <div style="text-align:center; margin-top: 24px;">
                <a href="transparency.php" style="color:var(--gold-500); font-size:0.82rem; text-decoration:none; font-weight:500; display:inline-flex; align-items:center; gap:6px;">
                    <i data-lucide="globe" style="width:14px;height:14px;"></i> Citizen Budget Transparency Portal &rarr;
                </a>
            </div>
        </div>

        <!-- Right Side: Visual/Branding -->
        <div class="login-right">
            <div class="feature-icon">
                <i data-lucide="landmark"></i>
            </div>
            <h2 class="right-title">SFGMIS</h2>
            <div class="right-sub">
                Ministry of Finance & Economic Development
            </div>
            <div class="security-badge">
                <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--gold-500);"></i>
                <span>Public Finance Management Act (PFMA) Compliant</span>
            </div>
            
            <div style="margin-top: 48px; display: flex; gap: 8px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--gold-500);"></div>
                <div style="width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.2);"></div>
                <div style="width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.2);"></div>
            </div>
        </div>
    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Automatic transparent flood-fill for outer white margins/sides
        (function() {
            var img = document.getElementById('loginBrandLogo');
            if (!img) return;
            function cleanWhiteSides() {
                try {
                    var c = document.createElement('canvas');
                    c.width = img.naturalWidth || 240;
                    c.height = img.naturalHeight || 240;
                    var ctx = c.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    var imgData = ctx.getImageData(0, 0, c.width, c.height);
                    var d = imgData.data;
                    var w = c.width, h = c.height;

                    // BFS flood-fill outer perimeter white pixels to alpha=0
                    var visited = new Uint8Array(w * h);
                    var queue = [];

                    for (var x = 0; x < w; x++) {
                        queue.push(x, 0);
                        queue.push(x, h - 1);
                    }
                    for (var y = 0; y < h; y++) {
                        queue.push(0, y);
                        queue.push(w - 1, y);
                    }

                    var qIdx = 0;
                    while (qIdx < queue.length) {
                        var qx = queue[qIdx++];
                        var qy = queue[qIdx++];
                        var idx = qy * w + qx;
                        if (visited[idx]) continue;
                        visited[idx] = 1;

                        var p = (qy * w + qx) * 4;
                        var r = d[p], g = d[p + 1], b = d[p + 2], a = d[p + 3];

                        if (a > 0 && r >= 220 && g >= 220 && b >= 220) {
                            d[p + 3] = 0;
                            if (qx > 0 && !visited[idx - 1]) queue.push(qx - 1, qy);
                            if (qx < w - 1 && !visited[idx + 1]) queue.push(qx + 1, qy);
                            if (qy > 0 && !visited[idx - w]) queue.push(qx, qy - 1);
                            if (qy < h - 1 && !visited[idx + w]) queue.push(qx, qy + 1);
                        }
                    }

                    ctx.putImageData(imgData, 0, 0);
                    img.src = c.toDataURL('image/png');
                } catch (e) {
                    // Graceful fallback to CSS circular clipping
                }
            }
            if (img.complete && img.naturalWidth > 0) {
                cleanWhiteSides();
            } else {
                img.addEventListener('load', cleanWhiteSides);
            }
        })();
    </script>
</body>
</html>
