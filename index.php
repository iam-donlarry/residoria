<?php
// login.php & index.php - Standalone Secure Login Page
require_once __DIR__ . '/config.php';

// If already logged in, redirect to main dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit;
}

$error = '';
$message = '';

if (isset($_GET['timeout'])) {
    $message = "Session timed out due to inactivity. Please sign in again.";
}

$db = Database::getInstance();
$setting = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'estate_name'");
$estate_name = !empty($setting['setting_value']) ? $setting['setting_value'] : 'Residoria Estate';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken();
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please provide both email address and password.";
        } else {
            $user = $db->fetchOne("SELECT id, person_id, name, email, password, role, status FROM users WHERE email = :email", [':email' => $email]);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'suspended' || $user['status'] === 'inactive') {
                    $error = "Your account is currently " . e($user['status']) . ". Please contact the estate manager.";
                } else {
                    // Standardized Session Setup
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['person_id'] = $user['person_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();

                    logAudit($conn, "User Login", "Auth", "Logged in successfully as " . $user['email']);

                    header("Location: dashboard");
                    exit;
                }
            } else {
                $error = "Invalid email address or password.";
            }
        }
    } catch (Exception $e) {
        $error = "Authentication Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e($estate_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-color: #12b1b0;
            --primary-hover: #0e8c8b;
            --primary-light: #e0f2f1;
            --border-color: #e5e7eb;
            --hover-bg: #f9fafb;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --body-bg: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            min-height: 100vh;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 1.25rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            max-width: 960px;
            width: 100%;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
        }

        .login-main {
            padding: 3rem 2.75rem;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
        }

        .login-logo-icon {
            width: 44px;
            height: 44px;
            background: var(--primary-light);
            color: var(--primary-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-logo-text {
            font-weight: 800;
            letter-spacing: -0.04em;
            font-size: 1.75rem;
            color: var(--primary-color);
            line-height: 1;
        }

        .login-title {
            font-weight: 700;
            font-size: 1.875rem;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .error-alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .info-alert {
            background: #e0f2f1;
            border: 1px solid #b2dfdb;
            color: #004d40;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .login-form-group {
            margin-bottom: 1.25rem;
        }

        .login-label-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.35rem;
            align-items: center;
        }

        .login-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .login-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .login-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(18, 177, 176, 0.12);
        }

        .login-checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .login-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .login-button {
            width: 100%;
            border-radius: 0.75rem;
            border: none;
            padding: 0.875rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            background: var(--primary-color);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(18, 177, 176, 0.3);
            background: var(--primary-hover);
        }

        .login-side {
            padding: 3rem 2.75rem;
            border-left: 1px solid var(--border-color);
            background: 
                radial-gradient(circle at top left, var(--primary-light), transparent 70%), 
                radial-gradient(circle at bottom right, #e2e8f0, transparent 70%),
                #fafaf7;
            position: relative;
        }

        .login-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.06);
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .login-side-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
        }

        .login-side-text {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
            font-weight: 400;
            line-height: 1.5;
        }

        .login-side-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .login-side-metric {
            padding: 0.85rem 0.9rem;
            border-radius: 0.9rem;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(148,163,184,0.3);
            backdrop-filter: blur(8px);
        }

        .login-side-metric-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.1rem;
            font-weight: 600;
        }

        .login-side-metric-value {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .login-side-badge {
            position: absolute;
            bottom: 2.25rem;
            right: 2.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.04);
            font-weight: 500;
        }

        @media (max-width: 900px) {
            .login-card {
                grid-template-columns: 1fr;
            }
            .login-side {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-main">
                <div class="login-logo">
                    <div class="login-logo-icon">
                        <i data-lucide="building-2" style="width: 26px; height: 26px;"></i>
                    </div>
                    <span class="login-logo-text"><?= e($estate_name) ?></span>
                </div>
                <h1 class="login-title">Sign in to your account</h1>
                <p class="login-subtitle">Use your credentials to access the estate management portal.</p>

                <?php if (!empty($error)): ?>
                <div class="error-alert">
                    <i data-lucide="alert-circle" style="width: 16px; height: 16px; margin-right: 6px; vertical-align: middle;"></i>
                    <?= e($error) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                <div class="info-alert">
                    <i data-lucide="info" style="width: 16px; height: 16px; margin-right: 6px; vertical-align: middle;"></i>
                    <?= e($message) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="login-form-group">
                        <div class="login-label-row">
                            <label class="login-label" for="email">Email Address</label>
                        </div>
                        <input type="email" class="login-input" id="email" name="email" placeholder="admin@estate.com" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="login-form-group">
                        <div class="login-label-row">
                            <label class="login-label" for="password">Password</label>
                        </div>
                        <input id="password" type="password" class="login-input" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="login-checkbox-row">
                        <label class="login-checkbox">
                            <input type="checkbox" name="remember" style="accent-color: var(--primary-color);">
                            <span>Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="login-button">
                        <i data-lucide="log-in" style="width: 18px; height: 18px; stroke-width: 2;"></i>
                        Sign in
                    </button>
                </form>

                <div style="margin-top: 2rem; font-size: 0.8125rem; color: var(--text-secondary); border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                    Default Admin: <code style="color: var(--primary-color); font-weight: 600;">admin@estate.com</code> / <code>admin123</code>
                </div>
            </div>

            <div class="login-side">
                <div class="login-tag">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                    Enterprise Estate Security
                </div>
                <h2 class="login-side-title"><?= e($estate_name) ?></h2>
                <p class="login-side-text">
                    Streamline residential & commercial property management, billing ledgers, digital visitor pre-authorization, and facility maintenance.
                </p>
                <div class="login-side-metrics">
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Properties</div>
                        <div class="login-side-metric-value">100+ Units</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Occupancy</div>
                        <div class="login-side-metric-value">98% Active</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Gate Security</div>
                        <div class="login-side-metric-value">24/7 Digital</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Satisfaction</div>
                        <div class="login-side-metric-value">4.9/5</div>
                    </div>
                </div>
                <div class="login-side-badge">
                    <span>Digital Estate Operations & Security</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
