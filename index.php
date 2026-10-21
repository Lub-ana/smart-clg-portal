<?php
/**
 * Smart College Management Portal - Entry Page
 * Displays project initialization status, database connectivity check,
 * portal role previews, sample synthetic accounts, and setup verification.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'System Overview & Setup Verification';
$active_nav = 'dashboard';

// Perform live database connectivity check
$db_status = testDatabaseConnection();

// Fetch synthetic data metrics if database connection is active
$counts = [
    'users'        => 0,
    'departments'  => 0,
    'classes'      => 0,
    'subjects'     => 0,
    'sessions'     => 0,
    'notes'        => 0,
    'assignments'  => 0,
    'doubts'       => 0,
];

$sample_users = [];
$attendance_stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0];

if ($db_status['status'] && $pdo instanceof PDO) {
    try {
        $counts['users']       = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        $counts['departments'] = (int)$pdo->query("SELECT COUNT(*) FROM `departments`")->fetchColumn();
        $counts['classes']     = (int)$pdo->query("SELECT COUNT(*) FROM `classes`")->fetchColumn();
        $counts['subjects']    = (int)$pdo->query("SELECT COUNT(*) FROM `subjects`")->fetchColumn();
        $counts['sessions']    = (int)$pdo->query("SELECT COUNT(*) FROM `attendance_sessions`")->fetchColumn();
        $counts['notes']       = (int)$pdo->query("SELECT COUNT(*) FROM `notes`")->fetchColumn();
        $counts['assignments'] = (int)$pdo->query("SELECT COUNT(*) FROM `assignments`")->fetchColumn();
        $counts['doubts']      = (int)$pdo->query("SELECT COUNT(*) FROM `doubts`")->fetchColumn();

        // Fetch sample users for display
        $stmtUsers = $pdo->query("SELECT id, username, email, role, full_name, status FROM `users` ORDER BY id ASC LIMIT 6");
        $sample_users = $stmtUsers->fetchAll();

        // Fetch attendance distribution for Chart.js preview
        $stmtAtt = $pdo->query("SELECT status, COUNT(*) as count FROM `attendance_records` GROUP BY status");
        while ($row = $stmtAtt->fetch()) {
            if (isset($attendance_stats[$row['status']])) {
                $attendance_stats[$row['status']] = (int)$row['count'];
            }
        }
    } catch (PDOException $e) {
        // Tables might not be imported yet
        $db_status['status'] = false;
        $db_status['message'] = "Connected to MySQL, but tables are missing: " . $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Area -->
<main class="portal-main-content">
    
    <!-- Global Flash Alerts -->
    <?= renderFlashes() ?>

    <!-- User Logged-in Banner (if active) -->
    <?php if (isLoggedIn()): ?>
        <div class="alert alert-primary border-primary d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-check-fill fs-4 text-primary"></i>
                <div>
                    <strong>Currently signed in as:</strong> <?= htmlspecialchars($_SESSION['user']['full_name']) ?>
                    <span class="badge <?= match($_SESSION['user']['role']) { 'admin' => 'badge-role-admin', 'teacher' => 'badge-role-teacher', default => 'badge-role-student' } ?> ms-1">
                        <?= ucfirst($_SESSION['user']['role']) ?>
                    </span>
                    <span class="text-muted small ms-2">(<?= htmlspecialchars($_SESSION['user']['email']) ?>)</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="modules/<?= $_SESSION['user']['role'] ?>/dashboard.php" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-speedometer2 me-1"></i>Open <?= ucfirst($_SESSION['user']['role']) ?> Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sign Out
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-white text-primary px-3 py-1 rounded-pill fw-semibold mb-2">Stage 2 Active &bull; Authentication Enabled</span>
                <h1 class="h2 fw-bold text-white mb-2">Smart College Management Portal</h1>
                <p class="lead text-white-50 mb-3 fs-6">
                    A centralized multi-role web platform for Administrators, Teachers, and Students.
                    Built with PHP, MySQL/MariaDB, Bootstrap 5, and Chart.js on Windows XAMPP.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (isLoggedIn()): ?>
                        <a href="modules/<?= $_SESSION['user']['role'] ?>/dashboard.php" class="btn btn-light btn-sm fw-semibold text-primary px-3 shadow-sm">
                            <i class="bi bi-speedometer2 me-1"></i>My <?= ucfirst($_SESSION['user']['role']) ?> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-light btn-sm fw-semibold text-primary px-3 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In to Portal
                        </a>
                    <?php endif; ?>
                    <a href="#portal-cards" class="btn btn-outline-light btn-sm fw-semibold px-3">
                        <i class="bi bi-grid-fill me-1"></i>Explore Portals
                    </a>
                    <a href="#schema-preview" class="btn btn-outline-light btn-sm fw-semibold px-3">
                        <i class="bi bi-database me-1"></i>Database Schema (15 Tables)
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-end">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 text-start border border-white border-opacity-25">
                    <div class="small text-white-50 text-uppercase fw-bold mb-1">Environment Overview</div>
                    <div class="text-white small mb-1"><i class="bi bi-check2-circle text-success me-1"></i><strong>Stack:</strong> PHP <?= PHP_VERSION ?> + MariaDB</div>
                    <div class="text-white small mb-1"><i class="bi bi-check2-circle text-success me-1"></i><strong>Database:</strong> <?= htmlspecialchars(DB_NAME) ?></div>
                    <div class="text-white small mb-1"><i class="bi bi-check2-circle text-success me-1"></i><strong>UI Framework:</strong> Bootstrap 5.3</div>
                    <div class="text-white small"><i class="bi bi-check2-circle text-success me-1"></i><strong>Graphs:</strong> Chart.js 4.4</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live System Health Status Alert -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if ($db_status['status']): ?>
                        <div class="stat-icon-wrapper stat-icon-green flex-shrink-0">
                            <i class="bi bi-database-check"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="mb-0 fw-bold text-success">Database Connected & Initialized</h6>
                                <span class="badge bg-success-subtle text-success small">Active</span>
                            </div>
                            <small class="text-muted">
                                Connected to <code><?= htmlspecialchars(DB_NAME) ?></code> on <code><?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?></code> via PDO. All 15 relational tables detected with synthetic data.
                            </small>
                        </div>
                    <?php else: ?>
                        <div class="stat-icon-wrapper stat-icon-rose flex-shrink-0">
                            <i class="bi bi-database-exclamation"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="mb-0 fw-bold text-danger">Database Import Required</h6>
                                <span class="badge bg-danger-subtle text-danger small">Pending Import</span>
                            </div>
                            <small class="text-danger">
                                <?= htmlspecialchars($db_status['message']) ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex-shrink-0 text-md-end">
                    <?php if ($db_status['status']): ?>
                        <span class="badge bg-light text-secondary border px-3 py-2">
                            <i class="bi bi-shield-check text-success me-1"></i>PDO Prepared Statements Ready
                        </span>
                    <?php else: ?>
                        <a href="#setup-guide" class="btn btn-danger btn-sm">
                            <i class="bi bi-arrow-down-circle me-1"></i>How to Import database.sql
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Users</span>
                    <div class="stat-icon-wrapper stat-icon-blue">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $counts['users'] ?></div>
                <small class="text-muted">Admin, Teachers, Students</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Departments</span>
                    <div class="stat-icon-wrapper stat-icon-cyan">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $counts['departments'] ?></div>
                <small class="text-muted">CSE, IT, ECE</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Classes & Subjects</span>
                    <div class="stat-icon-wrapper stat-icon-amber">
                        <i class="bi bi-book-half"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $counts['classes'] ?> / <?= $counts['subjects'] ?></div>
                <small class="text-muted">Active Curriculum</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Attendance Sessions</span>
                    <div class="stat-icon-wrapper stat-icon-green">
                        <i class="bi bi-calendar2-check-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $counts['sessions'] ?></div>
                <small class="text-muted">Logged Sessions</small>
            </div>
        </div>
    </div>

    <!-- Attendance Chart & Modules Showcase -->
    <div class="row g-4 mb-4" id="attendance-preview">
        <!-- Attendance Visualization Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Attendance Statistics Overview</h6>
                        <small class="text-muted">Powered by Chart.js & MariaDB Relational Records</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Live Sample Data</span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 260px;">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-center gap-4 mt-3 small text-muted">
                        <span><i class="bi bi-circle-fill text-success me-1"></i>Present: <strong><?= $attendance_stats['Present'] ?></strong></span>
                        <span><i class="bi bi-circle-fill text-warning me-1"></i>Late: <strong><?= $attendance_stats['Late'] ?></strong></span>
                        <span><i class="bi bi-circle-fill text-danger me-1"></i>Absent: <strong><?= $attendance_stats['Absent'] ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Resource Metrics -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-layers-fill text-info me-2"></i>Academic Workflow Metrics</h6>
                    <small class="text-muted">Modules configured in Stage 1 database</small>
                </div>
                <div class="card-body d-flex flex-column justify-content-around">
                    
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-blue" style="width: 40px; height: 40px;">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Study Notes & PDFs</div>
                                <div class="text-muted small">Uploaded by faculty</div>
                            </div>
                        </div>
                        <span class="badge bg-primary rounded-pill px-3"><?= $counts['notes'] ?> Available</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-amber" style="width: 40px; height: 40px;">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Assignments & Grading</div>
                                <div class="text-muted small">Course assignments & submissions</div>
                            </div>
                        </div>
                        <span class="badge bg-warning text-dark rounded-pill px-3"><?= $counts['assignments'] ?> Active</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-cyan" style="width: 40px; height: 40px;">
                                <i class="bi bi-question-circle"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Doubt Discussion Q&A</div>
                                <div class="text-muted small">Student queries to teachers</div>
                            </div>
                        </div>
                        <span class="badge bg-info text-dark rounded-pill px-3"><?= $counts['doubts'] ?> Inquiries</span>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Role Portals Preview Cards -->
    <div class="mb-4" id="portal-cards">
        <h5 class="fw-bold mb-3"><i class="bi bi-person-badge-fill text-primary me-2"></i>Shared Database Portals Architecture</h5>
        <div class="row g-4">
            
            <!-- Admin Portal -->
            <div class="col-md-4">
                <div class="card-portal-access">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge badge-role-admin">Admin Portal</span>
                        <i class="bi bi-shield-lock-fill fs-3 text-danger"></i>
                    </div>
                    <h5 class="fw-bold mb-2">College Administration</h5>
                    <p class="text-secondary small mb-3">
                        Central management for departments, classes, subjects, faculty allocations, student enrollments, and broadcast notifications.
                    </p>
                    <ul class="list-unstyled small text-muted mb-4">
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Department & Class Master</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Faculty & Student Registrations</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>System-wide Attendance Analytics</li>
                    </ul>
                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="bi bi-person-fill me-1"></i>Sample: <code>admin</code></span>
                        <?php if (hasRole('admin')): ?>
                            <a href="modules/admin/dashboard.php" class="btn btn-sm btn-danger px-3">
                                <i class="bi bi-speedometer2 me-1"></i>Open Dashboard
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-sm btn-outline-danger px-3">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Teacher Portal -->
            <div class="col-md-4">
                <div class="card-portal-access">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge badge-role-teacher">Teacher Portal</span>
                        <i class="bi bi-person-video3 fs-3 text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Faculty & Teaching</h5>
                    <p class="text-secondary small mb-3">
                        Digital attendance sessions marking, lecture notes publishing, assignment homework creation, grading, and answering student doubts.
                    </p>
                    <ul class="list-unstyled small text-muted mb-4">
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>One-click Attendance Register</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Study Notes & PDF Uploads</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Assignment Grading & Feedback</li>
                    </ul>
                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="bi bi-person-fill me-1"></i>Sample: <code>teacher_sharma</code></span>
                        <?php if (hasRole('teacher')): ?>
                            <a href="modules/teacher/dashboard.php" class="btn btn-sm btn-primary px-3">
                                <i class="bi bi-speedometer2 me-1"></i>Open Dashboard
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-sm btn-outline-primary px-3">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Student Portal -->
            <div class="col-md-4">
                <div class="card-portal-access">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge badge-role-student">Student Portal</span>
                        <i class="bi bi-backpack-fill fs-3 text-success"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Student Learning Space</h5>
                    <p class="text-secondary small mb-3">
                        Subject-wise attendance percentage tracking with visual progress, study material downloads, online assignment submission, and doubt raising.
                    </p>
                    <ul class="list-unstyled small text-muted mb-4">
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Subject-wise Attendance % Graphs</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Class Notes Download Center</li>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Submit Assignments & View Grades</li>
                    </ul>
                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="bi bi-person-fill me-1"></i>Sample: <code>student_aarav</code></span>
                        <?php if (hasRole('student')): ?>
                            <a href="modules/student/dashboard.php" class="btn btn-sm btn-success px-3">
                                <i class="bi bi-speedometer2 me-1"></i>Open Dashboard
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-sm btn-outline-success px-3">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Synthetic Sample Accounts Reference -->
    <div class="card border-0 shadow-sm mb-4" id="sample-accounts">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-key-fill text-warning me-2"></i>Synthetic Sample Accounts</h6>
                <small class="text-muted">Pre-seeded in <code>database.sql</code> with secure bcrypt password hashes</small>
            </div>
            <span class="badge bg-secondary-subtle text-secondary">Demo Credentials</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Role</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Demo Password</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sample_users)): ?>
                            <?php foreach ($sample_users as $u): ?>
                                <tr>
                                    <td><code>#<?= $u['id'] ?></code></td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-role-admin">Admin</span>
                                        <?php elseif ($u['role'] === 'teacher'): ?>
                                            <span class="badge badge-role-teacher">Teacher</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-student">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></td>
                                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                                    <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?php 
                                            $sample_pw = match($u['role']) {
                                                'admin'   => 'Admin@123',
                                                'teacher' => 'Teacher@123',
                                                'student' => 'Student@123',
                                                default   => 'Password123'
                                            };
                                        ?>
                                        <code class="bg-light px-2 py-1 rounded text-dark border"><?= $sample_pw ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success"><?= htmlspecialchars(ucfirst($u['status'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle me-1"></i> No users loaded. Please import <code>database.sql</code> in phpMyAdmin.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Relational Database Schema Overview (15 Tables) -->
    <div class="card border-0 shadow-sm mb-4" id="schema-preview">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill text-purple me-2"></i>Relational Database Architecture (15 Tables)</h6>
            <small class="text-muted">Designed in <code>database.sql</code> with InnoDB engine and Foreign Key constraints</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Table Name</th>
                            <th>Description</th>
                            <th>Key Foreign Keys / Relationships</th>
                            <th>Role Scope</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><code>users</code></td>
                            <td>User authentication, password hash, role and status</td>
                            <td>Primary authentication entity</td>
                            <td><span class="badge bg-dark">Shared</span></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><code>departments</code></td>
                            <td>Academic departments (CSE, IT, ECE)</td>
                            <td>Referenced by classes, subjects, profiles</td>
                            <td><span class="badge bg-danger">Admin</span></td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><code>classes</code></td>
                            <td>Class sections (e.g. CSE-5A), semester, academic year</td>
                            <td><code>department_id &rarr; departments.id</code></td>
                            <td><span class="badge bg-danger">Admin</span></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><code>subjects</code></td>
                            <td>Curriculum course subjects and codes</td>
                            <td><code>department_id &rarr; departments.id</code></td>
                            <td><span class="badge bg-danger">Admin</span></td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td><code>student_profiles</code></td>
                            <td>Roll numbers, enrollment IDs, department, class, guardians</td>
                            <td><code>user_id &rarr; users.id</code>, <code>class_id &rarr; classes.id</code></td>
                            <td><span class="badge bg-success">Student</span></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td><code>teacher_profiles</code></td>
                            <td>Employee IDs, designation, qualifications, department</td>
                            <td><code>user_id &rarr; users.id</code>, <code>department_id &rarr; departments.id</code></td>
                            <td><span class="badge bg-primary">Teacher</span></td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td><code>teacher_assignments</code></td>
                            <td>Subject & class allocation for teachers per academic year</td>
                            <td><code>teacher_id</code>, <code>subject_id</code>, <code>class_id</code></td>
                            <td><span class="badge bg-danger">Admin</span></td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td><code>attendance_sessions</code></td>
                            <td>Lecture attendance sessions with date, time, topic</td>
                            <td><code>class_id</code>, <code>subject_id</code>, <code>teacher_id</code></td>
                            <td><span class="badge bg-primary">Teacher</span></td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td><code>attendance_records</code></td>
                            <td>Student attendance status (Present, Absent, Late, Excused)</td>
                            <td><code>session_id &rarr; attendance_sessions.id</code>, <code>student_id</code></td>
                            <td><span class="badge bg-primary">Teacher</span> <span class="badge bg-success">Student</span></td>
                        </tr>
                        <tr>
                            <td>10</td>
                            <td><code>notes</code></td>
                            <td>Lecture study materials, file paths, slides, PDFs</td>
                            <td><code>subject_id</code>, <code>class_id</code>, <code>teacher_id</code></td>
                            <td><span class="badge bg-primary">Teacher</span> <span class="badge bg-success">Student</span></td>
                        </tr>
                        <tr>
                            <td>11</td>
                            <td><code>assignments</code></td>
                            <td>Coursework assignments, due dates, max marks</td>
                            <td><code>subject_id</code>, <code>class_id</code>, <code>teacher_id</code></td>
                            <td><span class="badge bg-primary">Teacher</span></td>
                        </tr>
                        <tr>
                            <td>12</td>
                            <td><code>assignment_submissions</code></td>
                            <td>Student homework submissions, grading scores & feedback</td>
                            <td><code>assignment_id &rarr; assignments.id</code>, <code>student_id</code></td>
                            <td><span class="badge bg-success">Student</span> <span class="badge bg-primary">Teacher</span></td>
                        </tr>
                        <tr>
                            <td>13</td>
                            <td><code>doubts</code></td>
                            <td>Academic queries posted by students to subject teachers</td>
                            <td><code>student_id</code>, <code>subject_id</code></td>
                            <td><span class="badge bg-success">Student</span></td>
                        </tr>
                        <tr>
                            <td>14</td>
                            <td><code>doubt_responses</code></td>
                            <td>Clarifications and answers posted for queries</td>
                            <td><code>doubt_id &rarr; doubts.id</code>, <code>user_id &rarr; users.id</code></td>
                            <td><span class="badge bg-primary">Teacher</span></td>
                        </tr>
                        <tr>
                            <td>15</td>
                            <td><code>notifications</code></td>
                            <td>Role broadcasts, administrative notices, and personal alerts</td>
                            <td><code>user_id (optional)</code>, <code>target_role</code></td>
                            <td><span class="badge bg-dark">Shared</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Setup & Verification Instructions Guide -->
    <div class="card border-0 shadow-sm mb-4" id="setup-guide">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold"><i class="bi bi-tools text-primary me-2"></i>Setup & phpMyAdmin Import Guide</h6>
            <small class="text-muted">Simple instructions for Windows XAMPP environment</small>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="fw-bold text-primary mb-2">Step 1: Start XAMPP Modules</h6>
                    <ol class="small text-secondary ps-3 mb-3">
                        <li class="mb-1">Open <strong>XAMPP Control Panel</strong> on Windows.</li>
                        <li class="mb-1">Click <strong>Start</strong> next to <strong>Apache</strong> (should turn green).</li>
                        <li class="mb-1">Click <strong>Start</strong> next to <strong>MySQL</strong> (should turn green, port 3306).</li>
                    </ol>

                    <h6 class="fw-bold text-primary mb-2">Step 2: Import database.sql via phpMyAdmin</h6>
                    <ol class="small text-secondary ps-3 mb-0">
                        <li class="mb-1">Open your web browser and navigate to: <a href="http://localhost/phpmyadmin/" target="_blank"><code>http://localhost/phpmyadmin/</code></a></li>
                        <li class="mb-1">Click the <strong>Import</strong> tab at the top menu bar.</li>
                        <li class="mb-1">Click <strong>Choose File</strong> and browse to: <br><code>C:\xampp_new\htdocs\smart-clg-portal\database.sql</code></li>
                        <li class="mb-1">Scroll down and click the <strong>Import</strong> / <strong>Go</strong> button.</li>
                        <li class="mb-1">phpMyAdmin will execute the script, create <code>smart_clg_portal</code> database, all 15 tables, and insert sample data.</li>
                    </ol>
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold text-primary mb-2">Step 3: Test Application in Browser</h6>
                    <p class="small text-secondary mb-2">
                        Open your web browser and enter the local URL:
                    </p>
                    <div class="p-2 bg-light border rounded mb-3">
                        <a href="http://localhost/smart-clg-portal/" class="fw-bold text-decoration-none" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-1"></i>http://localhost/smart-clg-portal/
                        </a>
                    </div>
                    <p class="small text-secondary mb-3">
                        The top status badge will turn <span class="badge bg-success-subtle text-success">MySQL: Connected</span>, displaying live synthetic metrics for users, classes, subjects, and attendance charts.
                    </p>

                    <h6 class="fw-bold text-primary mb-2">Step 4: Next Stages Roadmap</h6>
                    <ul class="small text-secondary ps-3 mb-0">
                        <li><strong>Stage 2:</strong> Secure authentication system (login, logout, session management, password verify, role redirects).</li>
                        <li><strong>Stage 3:</strong> Admin portal CRUD (Departments, Classes, Subjects, Faculty & Student registration).</li>
                        <li><strong>Stage 4:</strong> Teacher attendance register & interactive Chart.js reports.</li>
                        <li><strong>Stage 5:</strong> Notes upload, assignments submission, and doubt resolution engine.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- Initialize Attendance Chart with Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('attendanceChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Late', 'Absent'],
                datasets: [{
                    label: 'Session Attendance',
                    data: [
                        <?= $attendance_stats['Present'] ?>,
                        <?= $attendance_stats['Late'] ?>,
                        <?= $attendance_stats['Absent'] ?>
                    ],
                    backgroundColor: [
                        '#10b981', // Present - emerald
                        '#f59e0b', // Late - amber
                        '#ef4444'  // Absent - rose
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 12,
                                family: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
                            },
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = <?= array_sum($attendance_stats) ?: 1 ?>;
                                const val = context.raw || 0;
                                const percentage = Math.round((val / total) * 100);
                                return ` ${context.label}: ${val} records (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
