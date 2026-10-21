<?php
/**
 * Smart College Management Portal - Faculty & Teachers Management
 * Allows Administrator to view, register, edit, and manage faculty member accounts.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce admin server-side authorization
requireRole('admin');

$page_title = 'Faculty Directory Management';
$active_nav = 'teachers';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: teachers.php');
        exit;
    }

    if ($action === 'add') {
        $fullName      = trim($_POST['full_name'] ?? '');
        $username      = trim($_POST['username'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $employeeId    = strtoupper(trim($_POST['employee_id'] ?? ''));
        $departmentId  = (int)($_POST['department_id'] ?? 0);
        $designation   = trim($_POST['designation'] ?? 'Assistant Professor');
        $qualification = trim($_POST['qualification'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $joiningDate   = $_POST['joining_date'] ?: date('Y-m-d');

        if (empty($fullName) || empty($username) || empty($email) || empty($password) || empty($employeeId) || $departmentId <= 0) {
            setFlash('danger', 'All required faculty registration fields must be completed.');
        } else {
            try {
                // Check username, email, employee_id uniqueness
                $stmtCheck = $pdo->prepare("SELECT id FROM `users` WHERE `username` = :u OR `email` = :e LIMIT 1");
                $stmtCheck->execute([':u' => $username, ':e' => $email]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', 'A user with that username or email already exists.');
                } else {
                    $stmtEmp = $pdo->prepare("SELECT id FROM `teacher_profiles` WHERE `employee_id` = :emp LIMIT 1");
                    $stmtEmp->execute([':emp' => $employeeId]);
                    if ($stmtEmp->fetch()) {
                        setFlash('warning', "Employee ID '$employeeId' is already registered.");
                    } else {
                        // Atomic Transaction
                        $pdo->beginTransaction();

                        $pwHash = password_hash($password, PASSWORD_BCRYPT);
                        $stmtUser = $pdo->prepare("
                            INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `full_name`, `status`) 
                            VALUES (:u, :e, :pw, 'teacher', :name, 'active')
                        ");
                        $stmtUser->execute([
                            ':u'    => $username,
                            ':e'    => $email,
                            ':pw'   => $pwHash,
                            ':name' => $fullName
                        ]);
                        $newUserId = (int)$pdo->lastInsertId();

                        $stmtProf = $pdo->prepare("
                            INSERT INTO `teacher_profiles` 
                            (`user_id`, `employee_id`, `department_id`, `designation`, `qualification`, `phone`, `joining_date`) 
                            VALUES (:uid, :emp, :dept, :desig, :qual, :phone, :jdate)
                        ");
                        $stmtProf->execute([
                            ':uid'   => $newUserId,
                            ':emp'   => $employeeId,
                            ':dept'  => $departmentId,
                            ':desig' => $designation,
                            ':qual'  => $qualification,
                            ':phone' => $phone,
                            ':jdate' => $joiningDate
                        ]);

                        $pdo->commit();
                        setFlash('success', "Faculty member '$fullName' ($employeeId) registered successfully.");
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Register Teacher Error: " . $e->getMessage());
                setFlash('danger', 'Database error while registering faculty member: ' . $e->getMessage());
            }
        }
        header('Location: teachers.php');
        exit;
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                // Deleting from users cascades to teacher_profiles & teacher_assignments
                $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = :uid AND `role` = 'teacher'");
                $stmt->execute([':uid' => $userId]);
                setFlash('success', 'Faculty account and profile removed successfully.');
            } catch (PDOException $e) {
                error_log("Delete Teacher Error: " . $e->getMessage());
                setFlash('danger', 'Unable to delete faculty account. Linked attendance or grades exist.');
            }
        }
        header('Location: teachers.php');
        exit;
    }
}

// Filter Department
$filterDept = (int)($_GET['dept'] ?? 0);

// Fetch departments & teachers
$departments = [];
$teachersList = [];

if ($pdo instanceof PDO) {
    try {
        $departments = $pdo->query("SELECT id, department_name, department_code FROM `departments` ORDER BY department_name ASC")->fetchAll();

        $query = "
            SELECT tp.*, u.username, u.email, u.full_name, u.status,
                   d.department_name, d.department_code,
                   (SELECT COUNT(*) FROM `teacher_assignments` ta WHERE ta.teacher_id = tp.id) as assignment_count,
                   (SELECT COUNT(*) FROM `attendance_sessions` att WHERE att.teacher_id = tp.id) as session_count
            FROM `teacher_profiles` tp
            JOIN `users` u ON tp.user_id = u.id
            JOIN `departments` d ON tp.department_id = d.id
        ";
        if ($filterDept > 0) {
            $query .= " WHERE tp.department_id = :filterDept";
        }
        $query .= " ORDER BY d.department_name ASC, u.full_name ASC";

        $stmtTeachers = $pdo->prepare($query);
        if ($filterDept > 0) {
            $stmtTeachers->execute([':filterDept' => $filterDept]);
        } else {
            $stmtTeachers->execute();
        }
        $teachersList = $stmtTeachers->fetchAll();

    } catch (PDOException $e) {
        error_log("Teachers Query Error: " . $e->getMessage());
    }
}

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Main Content Area -->
<main class="portal-main-content">

    <!-- Flash Messages -->
    <?= renderFlashes() ?>

    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Faculty Directory</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Faculty & Teachers Directory</h2>
            <p class="text-secondary small mb-0">Register instructors, manage faculty profiles, and monitor teaching loads.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="bi bi-person-plus-fill"></i>
            <span>Register Faculty Member</span>
        </button>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="teachers.php" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label for="deptFilter" class="col-form-label small fw-semibold text-secondary">
                        <i class="bi bi-funnel me-1"></i>Filter Department:
                    </label>
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm" id="deptFilter" name="dept" onchange="this.form.submit()">
                        <option value="0">All Academic Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($filterDept === (int)$d['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filterDept > 0): ?>
                    <div class="col-auto">
                        <a href="teachers.php" class="btn btn-sm btn-outline-secondary">Clear Filter</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Teachers Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-video3 text-primary me-2"></i>Active Faculty List</h6>
                <small class="text-muted">Total <?= count($teachersList) ?> teachers registered</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($teachersList) ?> Faculty Members</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Emp ID</th>
                            <th>Faculty Name</th>
                            <th>Department</th>
                            <th>Designation & Qualification</th>
                            <th>Email & Phone</th>
                            <th>Allocated Courses</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($teachersList)): ?>
                            <?php foreach ($teachersList as $t): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                            <?= htmlspecialchars($t['employee_id']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($t['full_name']) ?></div>
                                        <small class="text-muted font-monospace">@<?= htmlspecialchars($t['username']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace me-1"><?= htmlspecialchars($t['department_code']) ?></span>
                                        <span class="small text-secondary"><?= htmlspecialchars($t['department_name']) ?></span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark"><?= htmlspecialchars($t['designation']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($t['qualification'] ?: 'Not specified') ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($t['email']) ?></div>
                                        <?php if ($t['phone']): ?>
                                            <div class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($t['phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <i class="bi bi-book-half me-1"></i><?= (int)$t['assignment_count'] ?> Courses
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success"><?= ucfirst($t['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm btn-delete-teacher" 
                                                data-uid="<?= $t['user_id'] ?>"
                                                data-name="<?= htmlspecialchars($t['full_name']) ?>"
                                                title="Delete Faculty Account">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    No faculty members registered. Click "Register Faculty Member" to add one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Add Faculty Member -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="teachers.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addTeacherModalLabel">
                        <i class="bi bi-person-plus-fill text-primary me-2"></i>Register New Faculty Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label small fw-semibold text-secondary">Full Name (with Title)</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" placeholder="e.g. Dr. Ramesh Gupta" required>
                        </div>
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label small fw-semibold text-secondary">Employee ID (Unique)</label>
                            <input type="text" class="form-control text-uppercase" id="employee_id" name="employee_id" placeholder="e.g. EMP-T105" required>
                        </div>

                        <div class="col-md-6">
                            <label for="username" class="form-label small fw-semibold text-secondary">Username (Sign In ID)</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="e.g. teacher_gupta" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold text-secondary">Official Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="e.g. r.gupta@smartcollege.edu" required>
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold text-secondary">Initial Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="e.g. Teacher@123" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label small fw-semibold text-secondary">Academic Department</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="designation" class="form-label small fw-semibold text-secondary">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g. Assistant Professor" value="Assistant Professor" required>
                        </div>
                        <div class="col-md-6">
                            <label for="qualification" class="form-label small fw-semibold text-secondary">Highest Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" placeholder="e.g. Ph.D. in Computer Science">
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold text-secondary">Contact Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="e.g. 9876543210">
                        </div>
                        <div class="col-md-6">
                            <label for="joining_date" class="form-label small fw-semibold text-secondary">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Register Faculty
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-labelledby="deleteTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="teachers.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_teacher_uid">

                <div class="modal-body text-center p-4">
                    <div class="stat-icon-wrapper stat-icon-rose mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.75rem;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Faculty?</h5>
                    <p class="text-secondary small mb-3">
                        Are you sure you want to delete <strong id="delete_teacher_name"></strong>?
                        This will terminate their login credentials.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Confirm Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- UI Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteTeacherModal'));
    document.querySelectorAll('.btn-delete-teacher').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete_teacher_uid').value = this.dataset.uid;
            document.getElementById('delete_teacher_name').textContent = this.dataset.name;
            deleteModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
