<?php
/**
 * Smart College Management Portal - Login
 * Provides secure authentication with CSRF protection, PDO prepared statements,
 * password hashing verification, and role-based redirection.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already authenticated, redirect straight to their role dashboard
if (isLoggedIn()) {
    redirectUserToDashboard();
}

$error = null;
$identifier = '';

// Handle POST login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $identifier     = trim($_POST['identifier'] ?? '');
    $password       = $_POST['password'] ?? '';

    // 1. Verify CSRF Token
    if (!verifyCsrfToken($submittedToken)) {
        $error = 'Security validation failed (invalid CSRF token). Please try submitting the form again.';
    } elseif (empty($identifier) || empty($password)) {
        $error = 'Please enter both your username/email and password.';
    } else {
        // 2. Query user via PDO Prepared Statement (using distinct named parameters)
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, username, email, password_hash, role, full_name, status 
                    FROM `users` 
                    WHERE `username` = :username OR `email` = :email 
                    LIMIT 1
                ");
                $stmt->execute([
                    ':username' => $identifier,
                    ':email'    => $identifier
                ]);
                $user = $stmt->fetch();

                // 3. Verify password hash using timing-safe bcrypt
                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['status'] !== 'active') {
                        $error = 'Your account is marked as ' . htmlspecialchars($user['status']) . '. Please contact the college administrator.';
                    } else {
                        // 4. Successful login: establish session & redirect
                        loginUser($user);
                        setFlash('success', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '! You have successfully logged in as ' . ucfirst($user['role']) . '.');
                        redirectUserToDashboard();
                    }
                } else {
                    $error = 'Invalid credentials. Please verify your username/email and password.';
                }
            } catch (PDOException $e) {
                error_log("Login Query Error: " . $e->getMessage());
                $error = 'A database error occurred: ' . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = 'Database connection is unavailable. Please check XAMPP MySQL.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Smart College Management Portal</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Portal CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            padding: 2rem 1.75rem 1.75rem;
            color: #ffffff;
            text-align: center;
        }
        .demo-credential-chip {
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }
        .demo-credential-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

<div class="login-card">
    
    <!-- Header with Branding -->
    <div class="login-header">
        <div class="brand-badge mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.5rem;">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <h4 class="fw-bold mb-1">Smart College Portal</h4>
        <p class="text-white-50 small mb-0">Sign in to your academic account</p>
    </div>

    <div class="p-4">
        
        <!-- Flash messages & Login Error Alerts -->
        <?= renderFlashes() ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div class="small"><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="login.php" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Username or Email -->
            <div class="mb-3">
                <label for="identifier" class="form-label small fw-semibold text-secondary">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0 ps-0" 
                           id="identifier" 
                           name="identifier" 
                           placeholder="e.g. admin or aarav.patel@student..." 
                           value="<?= htmlspecialchars($identifier) ?>" 
                           required 
                           autofocus>
                </div>
            </div>

            <!-- Password with Show/Hide Toggle -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control border-start-0 border-end-0 ps-0" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password" 
                           required>
                    <button class="btn btn-outline-secondary border-start-0 text-muted bg-white" 
                            type="button" 
                            id="togglePassword" 
                            aria-label="Toggle password visibility">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Sign In Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
            </button>
        </form>

        <!-- Quick Demo Fill Section -->
        <div class="border-top pt-3 mt-3">
            <div class="small text-muted fw-semibold mb-2 text-center text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                Quick Demo Credentials (Click to Fill)
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger demo-credential-chip text-start px-3 py-2 d-flex justify-content-between align-items-center" onclick="fillDemo('admin', 'Admin@123')">
                    <span><i class="bi bi-shield-lock-fill me-2"></i><strong>Admin:</strong> admin</span>
                    <span class="badge bg-danger-subtle text-danger">Admin@123</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary demo-credential-chip text-start px-3 py-2 d-flex justify-content-between align-items-center" onclick="fillDemo('teacher_sharma', 'Teacher@123')">
                    <span><i class="bi bi-person-video3 me-2"></i><strong>Teacher:</strong> teacher_sharma</span>
                    <span class="badge bg-primary-subtle text-primary">Teacher@123</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-success demo-credential-chip text-start px-3 py-2 d-flex justify-content-between align-items-center" onclick="fillDemo('student_aarav', 'Student@123')">
                    <span><i class="bi bi-backpack-fill me-2"></i><strong>Student:</strong> student_aarav</span>
                    <span class="badge bg-success-subtle text-success">Student@123</span>
                </button>
            </div>
        </div>

        <!-- Back to Overview -->
        <div class="text-center mt-3 pt-2">
            <a href="index.php" class="text-decoration-none small text-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to System Overview
            </a>
        </div>

    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (togglePassword && passwordInput && toggleIcon) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    }

    // Quick fill helper for testing synthetic credentials
    function fillDemo(username, password) {
        document.getElementById('identifier').value = username;
        document.getElementById('password').value = password;
        // Optionally submit immediately or let user inspect
        document.getElementById('identifier').focus();
    }
</script>

</body>
</html>
