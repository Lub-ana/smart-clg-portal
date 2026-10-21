<?php
/**
 * Smart College Management Portal - Header Component
 * Provides the global HTML head, Bootstrap 5 CDN, navigation bar, portal status indicator,
 * and user authentication profile controls.
 */

// Load database configuration
if (!defined('APP_NAME')) {
    $db_config_path = __DIR__ . '/../config/database.php';
    if (file_exists($db_config_path)) {
        require_once $db_config_path;
    }
}

// Load authentication helpers
if (!function_exists('isLoggedIn')) {
    $auth_path = __DIR__ . '/auth.php';
    if (file_exists($auth_path)) {
        require_once $auth_path;
    }
}

// Check database connectivity
$db_check = function_exists('testDatabaseConnection') ? testDatabaseConnection() : ['status' => false, 'message' => 'Config not loaded'];

$page_title = isset($page_title) ? $page_title . ' | Smart College Portal' : 'Smart College Management Portal';
$active_nav = $active_nav ?? 'dashboard';
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
$baseUrl = function_exists('getBaseUrl') ? getBaseUrl() : '/smart-clg-portal/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Portal Stylesheet -->
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg portal-navbar sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            
            <!-- Mobile Sidebar Toggle & Brand -->
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle Navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                
                <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="<?= $baseUrl ?>index.php">
                    <div class="brand-badge">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="brand-title">Smart College</span>
                        <span class="brand-subtitle d-none d-sm-inline">Management Portal</span>
                    </div>
                </a>
            </div>

            <!-- Navbar Right Items -->
            <div class="d-flex align-items-center gap-2 gap-md-3">
                
                <!-- Database Connection Status Indicator -->
                <?php if ($db_check['status']): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle d-none d-md-inline-flex align-items-center gap-1 px-2 py-1" title="<?= htmlspecialchars($db_check['message']) ?>">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span class="small font-monospace">MySQL: Connected</span>
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1 px-2 py-1" title="<?= htmlspecialchars($db_check['message']) ?>">
                        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                        <span class="small font-monospace">DB Pending</span>
                    </span>
                <?php endif; ?>

                <!-- User Session Menu / Login Button -->
                <?php if ($currentUser): ?>
                    <div class="dropdown">
                        <button class="btn btn-light border btn-sm dropdown-toggle d-flex align-items-center gap-2 px-2 py-1 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="badge <?= match($currentUser['role']) { 'admin' => 'badge-role-admin', 'teacher' => 'badge-role-teacher', default => 'badge-role-student' } ?>">
                                <?= ucfirst($currentUser['role']) ?>
                            </span>
                            <span class="fw-semibold small d-none d-sm-inline"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold small text-dark"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($currentUser['email']) ?></div>
                            </li>
                            <li>
                                <a class="dropdown-item small py-2" href="<?= $baseUrl ?>modules/<?= $currentUser['role'] ?>/dashboard.php">
                                    <i class="bi bi-speedometer2 text-primary me-2"></i>My Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item small py-2" href="<?= $baseUrl ?>index.php">
                                    <i class="bi bi-grid text-secondary me-2"></i>System Overview
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item small py-2 text-danger" href="<?= $baseUrl ?>logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>login.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                    </a>
                <?php endif; ?>

            </div>

        </div>
    </nav>

    <!-- App Wrapper for Sidebar + Content -->
    <div class="portal-wrapper">
