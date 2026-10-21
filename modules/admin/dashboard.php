<?php
/**
 * Smart College Management Portal - Administrator Dashboard
 * Central command center for managing academic departments, classes,
 * faculty allocations, student registrations, and system configurations.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce admin server-side authorization
requireRole('admin');

$page_title = 'Admin Dashboard';
$active_nav = 'admin_dashboard';
$currentUser = getCurrentUser();

// Initialize metric variables
$stats = [
    'departments' => 0,
    'classes'     => 0,
    'subjects'    => 0,
    'teachers'    => 0,
    'students'    => 0,
    'sessions'    => 0,
];
$recentUsers = [];
$departments = [];

if ($pdo instanceof PDO) {
    try {
        $stats['departments'] = (int)$pdo->query("SELECT COUNT(*) FROM `departments`")->fetchColumn();
        $stats['classes']     = (int)$pdo->query("SELECT COUNT(*) FROM `classes`")->fetchColumn();
        $stats['subjects']    = (int)$pdo->query("SELECT COUNT(*) FROM `subjects`")->fetchColumn();
        $stats['teachers']    = (int)$pdo->query("SELECT COUNT(*) FROM `teacher_profiles`")->fetchColumn();
        $stats['students']    = (int)$pdo->query("SELECT COUNT(*) FROM `student_profiles`")->fetchColumn();
        $stats['sessions']    = (int)$pdo->query("SELECT COUNT(*) FROM `attendance_sessions`")->fetchColumn();

        // Fetch recent users
        $stmtUsers = $pdo->query("
            SELECT u.id, u.username, u.email, u.role, u.full_name, u.status, u.created_at 
            FROM `users` u 
            ORDER BY u.id DESC 
            LIMIT 6
        ");
        $recentUsers = $stmtUsers->fetchAll();

        // Fetch departments summary
        $stmtDepts = $pdo->query("
            SELECT d.id, d.department_name, d.department_code,
                   (SELECT COUNT(*) FROM `classes` c WHERE c.department_id = d.id) as class_count,
                   (SELECT COUNT(*) FROM `subjects` s WHERE s.department_id = d.id) as subject_count
            FROM `departments` d
            ORDER BY d.id ASC
        ");
        $departments = $stmtDepts->fetchAll();

    } catch (PDOException $e) {
        error_log("Admin Dashboard Query Error: " . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Main Content Area -->
<main class="portal-main-content">
    
    <!-- Flash Messages -->
    <?= renderFlashes() ?>

    <!-- Header Greeting -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-role-admin">Administrator</span>
                <span class="text-muted small">Academic Management Center</span>
            </div>
            <h2 class="h3 fw-bold text-dark mt-1 mb-0">Welcome, <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
            <p class="text-secondary small mb-0">System configuration, curriculum administration, and role directory.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid me-1"></i>System Overview
            </a>
            <a href="../../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Sign Out
            </a>
        </div>
    </div>

    <!-- Admin Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Depts</span>
                    <div class="stat-icon-wrapper stat-icon-blue" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['departments'] ?></div>
                <small class="text-muted">Departments</small>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Classes</span>
                    <div class="stat-icon-wrapper stat-icon-cyan" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-diagram-2"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['classes'] ?></div>
                <small class="text-muted">Active Sections</small>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Subjects</span>
                    <div class="stat-icon-wrapper stat-icon-amber" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-book"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['subjects'] ?></div>
                <small class="text-muted">Curriculum Courses</small>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Teachers</span>
                    <div class="stat-icon-wrapper stat-icon-green" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-person-video3"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['teachers'] ?></div>
                <small class="text-muted">Faculty Members</small>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Students</span>
                    <div class="stat-icon-wrapper stat-icon-blue" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-backpack-fill"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['students'] ?></div>
                <small class="text-muted">Enrolled Students</small>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Sessions</span>
                    <div class="stat-icon-wrapper stat-icon-rose" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-calendar2-check-fill"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['sessions'] ?></div>
                <small class="text-muted">Attendance Sessions</small>
            </div>
        </div>
    </div>

    <!-- Quick Operations & Department Overview -->
    <div class="row g-4 mb-4">
        
        <!-- Department Summary Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-building text-primary me-2"></i>Departments Overview</h6>
                        <small class="text-muted">Configured academic branches and linked curriculum</small>
                    </div>
                    <span class="badge bg-light text-secondary border"><?= count($departments) ?> Active</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-portal mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Department Name</th>
                                    <th>Classes</th>
                                    <th>Subjects</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($dept['department_code']) ?></span></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($dept['department_name']) ?></td>
                                            <td><?= (int)$dept['class_count'] ?> Sections</td>
                                            <td><?= (int)$dept['subject_count'] ?> Courses</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No departments configured.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Administration Shortcuts -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Administration Workflows</h6>
                    <small class="text-muted">Direct management links</small>
                </div>
                <div class="card-body d-flex flex-column justify-content-around gap-2">
                    
                    <a href="departments.php" class="p-3 bg-light rounded d-flex align-items-center justify-content-between text-decoration-none text-dark border hover-shadow transition">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-blue" style="width: 40px; height: 40px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Departments Master</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Manage branches (CSE, IT, ECE)</div>
                            </div>
                        </div>
                        <span class="btn btn-sm btn-outline-primary">Manage &rarr;</span>
                    </a>

                    <a href="classes.php" class="p-3 bg-light rounded d-flex align-items-center justify-content-between text-decoration-none text-dark border hover-shadow transition">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-cyan" style="width: 40px; height: 40px;">
                                <i class="bi bi-diagram-2-fill"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Classes & Batches</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Manage sections and semesters</div>
                            </div>
                        </div>
                        <span class="btn btn-sm btn-outline-primary">Manage &rarr;</span>
                    </a>

                    <a href="subjects.php" class="p-3 bg-light rounded d-flex align-items-center justify-content-between text-decoration-none text-dark border hover-shadow transition">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-amber" style="width: 40px; height: 40px;">
                                <i class="bi bi-book-half"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Subjects Catalog</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Syllabus courses & allocations</div>
                            </div>
                        </div>
                        <span class="btn btn-sm btn-outline-primary">Manage &rarr;</span>
                    </a>

                    <a href="teachers.php" class="p-3 bg-light rounded d-flex align-items-center justify-content-between text-decoration-none text-dark border hover-shadow transition">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-green" style="width: 40px; height: 40px;">
                                <i class="bi bi-person-video3"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Faculty Management</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Register professors & instructors</div>
                            </div>
                        </div>
                        <span class="btn btn-sm btn-outline-success">Manage &rarr;</span>
                    </a>

                    <a href="students.php" class="p-3 bg-light rounded d-flex align-items-center justify-content-between text-decoration-none text-dark border hover-shadow transition">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-rose" style="width: 40px; height: 40px;">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Student Enrollments</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Assign roll numbers & profiles</div>
                            </div>
                        </div>
                        <span class="btn btn-sm btn-outline-danger">Manage &rarr;</span>
                    </a>

                </div>
            </div>
        </div>
    </div>

    <!-- System Users Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill text-dark me-2"></i>Registered System Users</h6>
                <small class="text-muted">Master credentials across Admin, Teacher, and Student roles</small>
            </div>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($recentUsers) ?> Records Loaded</span>
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
                            <th>Email Address</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentUsers)): ?>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><code>#<?= $user['id'] ?></code></td>
                                    <td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge badge-role-admin">Admin</span>
                                        <?php elseif ($user['role'] === 'teacher'): ?>
                                            <span class="badge badge-role-teacher">Teacher</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-student">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                                    <td class="text-muted"><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success"><?= ucfirst($user['status']) ?></span>
                                    </td>
                                    <td class="text-muted small"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
