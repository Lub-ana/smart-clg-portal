<?php
/**
 * Smart College Management Portal - Sidebar Navigation Component
 * Provides role-aware navigation links for Admin, Teacher, Student, and Guest users.
 */

if (!function_exists('isLoggedIn')) {
    $auth_path = __DIR__ . '/auth.php';
    if (file_exists($auth_path)) {
        require_once $auth_path;
    }
}

$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
$baseUrl = function_exists('getBaseUrl') ? getBaseUrl() : '/smart-clg-portal/';
$active_nav = $active_nav ?? 'dashboard';
?>
<!-- Sidebar Navigation -->
<aside class="portal-sidebar" id="portalSidebar">

    <!-- Role Context Banner -->
    <?php if ($currentUser): ?>
        <div class="p-2 mb-3 rounded bg-light border d-flex align-items-center gap-2">
            <div class="stat-icon-wrapper <?= match($currentUser['role']) { 'admin' => 'stat-icon-rose', 'teacher' => 'stat-icon-blue', default => 'stat-icon-green' } ?>" style="width: 36px; height: 36px; font-size: 1rem;">
                <i class="bi <?= match($currentUser['role']) { 'admin' => 'bi-shield-lock-fill', 'teacher' => 'bi-person-video3', default => 'bi-backpack-fill' } ?>"></i>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold small text-truncate"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                <div class="text-muted text-uppercase" style="font-size: 0.68rem; font-weight: 700;">
                    <?= htmlspecialchars($currentUser['role']) ?> Portal
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Based on Current Role -->
    <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
        
        <div class="sidebar-heading">Admin Center</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'admin_dashboard') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/admin/dashboard.php">
                <i class="bi bi-speedometer2 text-danger"></i>
                <span>Admin Dashboard</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'overview') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                <i class="bi bi-grid"></i>
                <span>System Overview</span>
            </a>
        </nav>

        <div class="sidebar-heading">Academic Masters</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'departments') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/admin/departments.php">
                <i class="bi bi-building text-info"></i>
                <span>Departments</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'classes') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/admin/classes.php">
                <i class="bi bi-diagram-2 text-primary"></i>
                <span>Classes & Batches</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'subjects') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/admin/subjects.php">
                <i class="bi bi-book text-warning"></i>
                <span>Subjects Catalog</span>
            </a>
        </nav>

        <div class="sidebar-heading">User Management</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'teachers') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/admin/teachers.php">
                <i class="bi bi-person-badge text-success"></i>
                <span>Faculty Directory</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'students') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/admin/students.php">
                <i class="bi bi-people text-secondary"></i>
                <span>Student Directory</span>
            </a>
        </nav>

    <?php elseif ($currentUser && $currentUser['role'] === 'teacher'): ?>

        <div class="sidebar-heading">Teacher Center</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'teacher_dashboard') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/teacher/dashboard.php">
                <i class="bi bi-speedometer2 text-primary"></i>
                <span>Teacher Dashboard</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'overview') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                <i class="bi bi-grid"></i>
                <span>System Overview</span>
            </a>
        </nav>

        <div class="sidebar-heading">Academic Operations</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'attendance') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/teacher/attendance.php">
                <i class="bi bi-calendar2-check-fill text-success"></i>
                <span>Mark Attendance</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'notes') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/teacher/notes.php">
                <i class="bi bi-journal-arrow-up text-info"></i>
                <span>Upload Notes</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'assignments') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/teacher/assignments.php">
                <i class="bi bi-pencil-square text-warning"></i>
                <span>Assignments & Grades</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'doubts') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/teacher/doubts.php">
                <i class="bi bi-chat-quote-fill text-danger"></i>
                <span>Resolve Doubts</span>
            </a>
        </nav>

    <?php elseif ($currentUser && $currentUser['role'] === 'student'): ?>

        <div class="sidebar-heading">Student Center</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'student_dashboard') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/student/dashboard.php">
                <i class="bi bi-speedometer2 text-success"></i>
                <span>Student Dashboard</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'overview') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                <i class="bi bi-grid"></i>
                <span>System Overview</span>
            </a>
        </nav>

        <div class="sidebar-heading">My Learning</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'attendance') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/student/attendance.php">
                <i class="bi bi-pie-chart-fill text-primary"></i>
                <span>My Attendance %</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'notes') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/student/notes.php">
                <i class="bi bi-file-earmark-arrow-down-fill text-info"></i>
                <span>Class Notes</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'assignments') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/student/assignments.php">
                <i class="bi bi-journal-check text-warning"></i>
                <span>My Assignments</span>
            </a>
            <a class="sidebar-link <?= ($active_nav === 'doubts') ? 'active' : '' ?>" href="<?= $baseUrl ?>modules/student/doubts.php">
                <i class="bi bi-patch-question-fill text-secondary"></i>
                <span>Ask a Doubt</span>
            </a>
        </nav>

    <?php else: ?>

        <!-- Guest / Public Overview -->
        <div class="sidebar-heading">Portal Exploration</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link <?= ($active_nav === 'dashboard') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                <i class="bi bi-speedometer2"></i>
                <span>Overview & Status</span>
            </a>
            <a class="sidebar-link" href="<?= $baseUrl ?>login.php">
                <i class="bi bi-box-arrow-in-right text-primary"></i>
                <span>Sign In to Portal</span>
            </a>
        </nav>

        <div class="sidebar-heading">Portal Previews</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link" href="<?= $baseUrl ?>index.php#portal-cards">
                <i class="bi bi-shield-lock-fill text-danger"></i>
                <span>Admin Portal</span>
            </a>
            <a class="sidebar-link" href="<?= $baseUrl ?>index.php#portal-cards">
                <i class="bi bi-person-video3 text-primary"></i>
                <span>Teacher Portal</span>
            </a>
            <a class="sidebar-link" href="<?= $baseUrl ?>index.php#portal-cards">
                <i class="bi bi-backpack-fill text-success"></i>
                <span>Student Portal</span>
            </a>
        </nav>

        <div class="sidebar-heading">System & Config</div>
        <nav class="nav flex-column gap-1">
            <a class="sidebar-link" href="<?= $baseUrl ?>index.php#schema-preview">
                <i class="bi bi-database-fill text-purple"></i>
                <span>Database Tables (15)</span>
            </a>
            <a class="sidebar-link" href="<?= $baseUrl ?>index.php#sample-accounts">
                <i class="bi bi-key-fill text-warning"></i>
                <span>Sample Logins</span>
            </a>
            <a class="sidebar-link" href="<?= $baseUrl ?>index.php#setup-guide">
                <i class="bi bi-file-text-fill text-secondary"></i>
                <span>Setup Guide</span>
            </a>
        </nav>

    <?php endif; ?>

    <!-- Session Sign Out (If logged in) -->
    <?php if ($currentUser): ?>
        <div class="mt-4 pt-3 border-top px-2">
            <a href="<?= $baseUrl ?>logout.php" class="btn btn-outline-danger btn-sm w-100 text-start d-flex align-items-center justify-content-between">
                <span><i class="bi bi-box-arrow-right me-2"></i>Sign Out</span>
                <i class="bi bi-chevron-right small"></i>
            </a>
        </div>
    <?php endif; ?>

</aside>
