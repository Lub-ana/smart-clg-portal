<?php
/**
 * Smart College Management Portal - Student Directory & Enrollment Management
 * Allows Administrator to view, enroll, manage, and remove student profiles.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce admin server-side authorization
requireRole('admin');

$page_title = 'Student Enrollment Management';
$active_nav = 'students';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: students.php');
        exit;
    }

    if ($action === 'add') {
        $fullName       = trim($_POST['full_name'] ?? '');
        $username       = trim($_POST['username'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $password       = $_POST['password'] ?? '';
        $rollNumber     = strtoupper(trim($_POST['roll_number'] ?? ''));
        $enrollmentNo   = strtoupper(trim($_POST['enrollment_number'] ?? ''));
        $departmentId   = (int)($_POST['department_id'] ?? 0);
        $classId        = (int)($_POST['class_id'] ?? 0);
        $phone          = trim($_POST['phone'] ?? '');
        $gender         = $_POST['gender'] ?? 'Male';
        $dob            = $_POST['dob'] ?: null;
        $address        = trim($_POST['address'] ?? '');
        $guardianName   = trim($_POST['guardian_name'] ?? '');
        $guardianPhone  = trim($_POST['guardian_phone'] ?? '');

        if (empty($fullName) || empty($username) || empty($email) || empty($password) || empty($rollNumber) || empty($enrollmentNo) || $departmentId <= 0 || $classId <= 0) {
            setFlash('danger', 'Full name, credentials, roll number, enrollment ID, department, and class are required.');
        } else {
            try {
                // Check uniqueness for username, email, roll_number, enrollment_number
                $stmtCheck = $pdo->prepare("SELECT id FROM `users` WHERE `username` = :u OR `email` = :e LIMIT 1");
                $stmtCheck->execute([':u' => $username, ':e' => $email]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', 'A user with that username or email already exists.');
                } else {
                    $stmtRoll = $pdo->prepare("SELECT id FROM `student_profiles` WHERE `roll_number` = :r OR `enrollment_number` = :enr LIMIT 1");
                    $stmtRoll->execute([':r' => $rollNumber, ':enr' => $enrollmentNo]);
                    if ($stmtRoll->fetch()) {
                        setFlash('warning', "Roll number '$rollNumber' or enrollment number '$enrollmentNo' is already registered.");
                    } else {
                        // Atomic Transaction
                        $pdo->beginTransaction();

                        $pwHash = password_hash($password, PASSWORD_BCRYPT);
                        $stmtUser = $pdo->prepare("
                            INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `full_name`, `status`) 
                            VALUES (:u, :e, :pw, 'student', :name, 'active')
                        ");
                        $stmtUser->execute([
                            ':u'    => $username,
                            ':e'    => $email,
                            ':pw'   => $pwHash,
                            ':name' => $fullName
                        ]);
                        $newUserId = (int)$pdo->lastInsertId();

                        $stmtProf = $pdo->prepare("
                            INSERT INTO `student_profiles` 
                            (`user_id`, `roll_number`, `enrollment_number`, `department_id`, `class_id`, `phone`, `gender`, `dob`, `address`, `guardian_name`, `guardian_phone`) 
                            VALUES (:uid, :roll, :enr, :dept, :cls, :phone, :gender, :dob, :addr, :gname, :gphone)
                        ");
                        $stmtProf->execute([
                            ':uid'    => $newUserId,
                            ':roll'   => $rollNumber,
                            ':enr'    => $enrollmentNo,
                            ':dept'   => $departmentId,
                            ':cls'    => $classId,
                            ':phone'  => $phone,
                            ':gender' => $gender,
                            ':dob'    => $dob,
                            ':addr'   => $address,
                            ':gname'  => $guardianName,
                            ':gphone' => $guardianPhone
                        ]);

                        $pdo->commit();
                        setFlash('success', "Student '$fullName' ($rollNumber) has been enrolled successfully.");
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Enroll Student Error: " . $e->getMessage());
                setFlash('danger', 'Database error while enrolling student: ' . $e->getMessage());
            }
        }
        header('Location: students.php');
        exit;
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                // Deleting user cascades to student_profiles, attendance_records, etc.
                $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = :uid AND `role` = 'student'");
                $stmt->execute([':uid' => $userId]);
                setFlash('success', 'Student account and academic profile removed successfully.');
            } catch (PDOException $e) {
                error_log("Delete Student Error: " . $e->getMessage());
                setFlash('danger', 'Unable to delete student account. System constraint triggered.');
            }
        }
        header('Location: students.php');
        exit;
    }
}

// Filter parameters
$filterDept  = (int)($_GET['dept'] ?? 0);
$filterClass = (int)($_GET['class'] ?? 0);

$departments  = [];
$classes      = [];
$studentsList = [];

if ($pdo instanceof PDO) {
    try {
        $departments = $pdo->query("SELECT id, department_name, department_code FROM `departments` ORDER BY department_name ASC")->fetchAll();
        $classes     = $pdo->query("SELECT id, class_name, semester, department_id FROM `classes` ORDER BY class_name ASC")->fetchAll();

        $query = "
            SELECT sp.*, u.username, u.email, u.full_name, u.status,
                   d.department_name, d.department_code,
                   c.class_name, c.semester,
                   (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.student_id = sp.id AND ar.status = 'Present') as present_count,
                   (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.student_id = sp.id) as total_attendance
            FROM `student_profiles` sp
            JOIN `users` u ON sp.user_id = u.id
            JOIN `departments` d ON sp.department_id = d.id
            JOIN `classes` c ON sp.class_id = c.id
        ";
        $whereClauses = [];
        $params = [];

        if ($filterDept > 0) {
            $whereClauses[] = "sp.department_id = :dept";
            $params[':dept'] = $filterDept;
        }
        if ($filterClass > 0) {
            $whereClauses[] = "sp.class_id = :cls";
            $params[':cls'] = $filterClass;
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY c.class_name ASC, sp.roll_number ASC";

        $stmtStudents = $pdo->prepare($query);
        $stmtStudents->execute($params);
        $studentsList = $stmtStudents->fetchAll();

    } catch (PDOException $e) {
        error_log("Students Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Student Directory</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Enrolled Students Directory</h2>
            <p class="text-secondary small mb-0">Register student admissions, manage roll numbers, and assign class sections.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="bi bi-person-plus-fill"></i>
            <span>Enroll New Student</span>
        </button>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="students.php" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label small fw-semibold text-secondary">
                        <i class="bi bi-funnel me-1"></i>Filter Students:
                    </label>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="dept" onchange="this.form.submit()">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($filterDept === (int)$d['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="class" onchange="this.form.submit()">
                        <option value="0">All Class Sections</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($filterClass === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['class_name']) ?> (Sem <?= (int)$c['semester'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filterDept > 0 || $filterClass > 0): ?>
                    <div class="col-auto">
                        <a href="students.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Students Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-people text-primary me-2"></i>Registered Students List</h6>
                <small class="text-muted">Showing <?= count($studentsList) ?> enrolled students</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($studentsList) ?> Active Students</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Department & Class</th>
                            <th>Enrollment ID</th>
                            <th>Contact Phone</th>
                            <th>Attendance Record</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($studentsList)): ?>
                            <?php foreach ($studentsList as $s): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                            <?= htmlspecialchars($s['roll_number']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($s['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace me-1"><?= htmlspecialchars($s['department_code']) ?></span>
                                        <span class="fw-semibold small text-primary"><?= htmlspecialchars($s['class_name']) ?></span>
                                    </td>
                                    <td><code class="small"><?= htmlspecialchars($s['enrollment_number']) ?></code></td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($s['phone'] ?: 'N/A') ?>
                                    </td>
                                    <td>
                                        <?php if ($s['total_attendance'] > 0): ?>
                                            <?php $pct = round(($s['present_count'] / $s['total_attendance']) * 100); ?>
                                            <span class="badge <?= ($pct >= 75) ? 'badge-present' : 'badge-absent' ?>">
                                                <?= (int)$s['present_count'] ?> / <?= (int)$s['total_attendance'] ?> (<?= $pct ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">No logs</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success"><?= ucfirst($s['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm btn-delete-student" 
                                                data-uid="<?= $s['user_id'] ?>"
                                                data-name="<?= htmlspecialchars($s['full_name']) ?>"
                                                title="Delete Student">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    No students found matching current filters. Click "Enroll New Student" to register.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Enroll New Student -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="students.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addStudentModalLabel">
                        <i class="bi bi-backpack-fill text-success me-2"></i>Enroll New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label small fw-semibold text-secondary">Student Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" placeholder="e.g. Yash Singhal" required>
                        </div>
                        <div class="col-md-6">
                            <label for="roll_number" class="form-label small fw-semibold text-secondary">Roll Number (Unique)</label>
                            <input type="text" class="form-control text-uppercase" id="roll_number" name="roll_number" placeholder="e.g. CSE-25-010" required>
                        </div>

                        <div class="col-md-6">
                            <label for="enrollment_number" class="form-label small fw-semibold text-secondary">Enrollment ID (Unique)</label>
                            <input type="text" class="form-control text-uppercase" id="enrollment_number" name="enrollment_number" placeholder="e.g. ENR2023010" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label small fw-semibold text-secondary">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="username" class="form-label small fw-semibold text-secondary">Username (Sign In ID)</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="e.g. student_yash" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold text-secondary">Student Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="e.g. yash@student.smartcollege.edu" required>
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold text-secondary">Initial Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="e.g. Student@123" required>
                        </div>
                        <div class="col-md-6">
                            <label for="dob" class="form-label small fw-semibold text-secondary">Date of Birth</label>
                            <input type="date" class="form-control" id="dob" name="dob">
                        </div>

                        <div class="col-md-6">
                            <label for="department_id" class="form-label small fw-semibold text-secondary">Department</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Academic Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="class_id" class="form-label small fw-semibold text-secondary">Class Section</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class Section</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?> (Sem <?= (int)$c['semester'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold text-secondary">Student Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="e.g. 9811002233">
                        </div>
                        <div class="col-md-6">
                            <label for="guardian_name" class="form-label small fw-semibold text-secondary">Guardian / Parent Name</label>
                            <input type="text" class="form-control" id="guardian_name" name="guardian_name" placeholder="e.g. Rajesh Singhal">
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label small fw-semibold text-secondary">Residential Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Full residential address..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Enroll Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="students.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_student_uid">

                <div class="modal-body text-center p-4">
                    <div class="stat-icon-wrapper stat-icon-rose mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.75rem;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Student?</h5>
                    <p class="text-secondary small mb-3">
                        Are you sure you want to delete <strong id="delete_student_name"></strong>?
                        This will terminate their enrollment and academic logs.
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
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));
    document.querySelectorAll('.btn-delete-student').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete_student_uid').value = this.dataset.uid;
            document.getElementById('delete_student_name').textContent = this.dataset.name;
            deleteModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
