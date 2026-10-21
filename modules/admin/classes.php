<?php
/**
 * Smart College Management Portal - Classes & Batches Management
 * Allows Administrator to view, create, edit, and delete class sections.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce admin server-side authorization
requireRole('admin');

$page_title = 'Classes & Sections Management';
$active_nav = 'classes';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: classes.php');
        exit;
    }

    if ($action === 'add') {
        $className = trim($_POST['class_name'] ?? '');
        $deptId    = (int)($_POST['department_id'] ?? 0);
        $semester  = (int)($_POST['semester'] ?? 1);
        $year      = trim($_POST['academic_year'] ?? '2025-2026');

        if (empty($className) || $deptId <= 0 || $semester <= 0) {
            setFlash('danger', 'Class name, department, and valid semester (1-8) are required.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO `classes` (`department_id`, `class_name`, `semester`, `academic_year`) 
                    VALUES (:dept, :name, :sem, :year)
                ");
                $stmt->execute([
                    ':dept' => $deptId,
                    ':name' => $className,
                    ':sem'  => $semester,
                    ':year' => $year
                ]);
                setFlash('success', "Class section '$className' has been added successfully.");
            } catch (PDOException $e) {
                error_log("Add Class Error: " . $e->getMessage());
                setFlash('danger', 'Database error while creating class section.');
            }
        }
        header('Location: classes.php');
        exit;
    }

    if ($action === 'edit') {
        $id        = (int)($_POST['class_id'] ?? 0);
        $className = trim($_POST['class_name'] ?? '');
        $deptId    = (int)($_POST['department_id'] ?? 0);
        $semester  = (int)($_POST['semester'] ?? 1);
        $year      = trim($_POST['academic_year'] ?? '2025-2026');

        if ($id <= 0 || empty($className) || $deptId <= 0) {
            setFlash('danger', 'Invalid class parameters for update.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE `classes` 
                    SET `department_id` = :dept, `class_name` = :name, `semester` = :sem, `academic_year` = :year 
                    WHERE `id` = :id
                ");
                $stmt->execute([
                    ':dept' => $deptId,
                    ':name' => $className,
                    ':sem'  => $semester,
                    ':year' => $year,
                    ':id'   => $id
                ]);
                setFlash('success', "Class section '$className' updated successfully.");
            } catch (PDOException $e) {
                error_log("Edit Class Error: " . $e->getMessage());
                setFlash('danger', 'Database error while updating class.');
            }
        }
        header('Location: classes.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['class_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `classes` WHERE `id` = :id");
                $stmt->execute([':id' => $id]);
                setFlash('success', 'Class section deleted successfully.');
            } catch (PDOException $e) {
                error_log("Delete Class Error: " . $e->getMessage());
                setFlash('danger', 'Unable to delete class. Students or attendance records are currently associated with it.');
            }
        }
        header('Location: classes.php');
        exit;
    }
}

// Fetch filter department
$filterDept = (int)($_GET['dept'] ?? 0);

// Fetch departments for dropdowns & filter
$departments = [];
$classesList = [];

if ($pdo instanceof PDO) {
    try {
        $departments = $pdo->query("SELECT id, department_name, department_code FROM `departments` ORDER BY department_name ASC")->fetchAll();

        $query = "
            SELECT c.*, d.department_name, d.department_code,
                   (SELECT COUNT(*) FROM `student_profiles` sp WHERE sp.class_id = c.id) as student_count,
                   (SELECT COUNT(*) FROM `attendance_sessions` att WHERE att.class_id = c.id) as session_count
            FROM `classes` c
            JOIN `departments` d ON c.department_id = d.id
        ";
        if ($filterDept > 0) {
            $query .= " WHERE c.department_id = :filterDept";
        }
        $query .= " ORDER BY d.department_name ASC, c.semester ASC, c.class_name ASC";

        $stmtClasses = $pdo->prepare($query);
        if ($filterDept > 0) {
            $stmtClasses->execute([':filterDept' => $filterDept]);
        } else {
            $stmtClasses->execute();
        }
        $classesList = $stmtClasses->fetchAll();

    } catch (PDOException $e) {
        error_log("Classes Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Classes & Batches</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Classes & Sections</h2>
            <p class="text-secondary small mb-0">Manage student batches, section assignments, and semester curriculums.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Add New Class</span>
        </button>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="classes.php" class="row g-2 align-items-center">
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
                        <a href="classes.php" class="btn btn-sm btn-outline-secondary">Clear Filter</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Classes Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-2 text-primary me-2"></i>Class Sections Directory</h6>
                <small class="text-muted">Showing <?= count($classesList) ?> class batches</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($classesList) ?> Active Classes</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Class Section</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th>Enrolled Students</th>
                            <th>Attendance Sessions</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($classesList)): ?>
                            <?php foreach ($classesList as $cls): ?>
                                <tr>
                                    <td><code>#<?= $cls['id'] ?></code></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($cls['class_name']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace me-1"><?= htmlspecialchars($cls['department_code']) ?></span>
                                        <span class="small text-secondary"><?= htmlspecialchars($cls['department_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info">Semester <?= (int)$cls['semester'] ?></span>
                                    </td>
                                    <td class="small text-muted font-monospace"><?= htmlspecialchars($cls['academic_year']) ?></td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-people me-1"></i><?= (int)$cls['student_count'] ?> Students
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-calendar-check me-1"></i><?= (int)$cls['session_count'] ?> Sessions
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-edit-class" 
                                                    data-id="<?= $cls['id'] ?>"
                                                    data-name="<?= htmlspecialchars($cls['class_name']) ?>"
                                                    data-dept="<?= $cls['department_id'] ?>"
                                                    data-sem="<?= $cls['semester'] ?>"
                                                    data-year="<?= htmlspecialchars($cls['academic_year']) ?>"
                                                    title="Edit Class">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-delete-class" 
                                                    data-id="<?= $cls['id'] ?>"
                                                    data-name="<?= htmlspecialchars($cls['class_name']) ?>"
                                                    title="Delete Class">
                                                <i class="bi bi-trash text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    No classes found matching criteria. Click "Add New Class" to create one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Add Class -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="classes.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addClassModalLabel">
                        <i class="bi bi-diagram-2 text-primary me-2"></i>Add Class Section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_name" class="form-label small fw-semibold text-secondary">Class Name / Section</label>
                        <input type="text" class="form-control" id="class_name" name="class_name" placeholder="e.g. CSE-6A or IT-4B" required>
                    </div>

                    <div class="mb-3">
                        <label for="department_id" class="form-label small fw-semibold text-secondary">Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Academic Department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="semester" class="form-label small fw-semibold text-secondary">Semester (1 - 8)</label>
                            <input type="number" class="form-control" id="semester" name="semester" min="1" max="8" value="5" required>
                        </div>
                        <div class="col-6">
                            <label for="academic_year" class="form-label small fw-semibold text-secondary">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" value="2025-2026" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Create Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Class -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="classes.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="class_id" id="edit_class_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editClassModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Class Section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_class_name" class="form-label small fw-semibold text-secondary">Class Name / Section</label>
                        <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label small fw-semibold text-secondary">Department</label>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="edit_semester" class="form-label small fw-semibold text-secondary">Semester (1 - 8)</label>
                            <input type="number" class="form-control" id="edit_semester" name="semester" min="1" max="8" required>
                        </div>
                        <div class="col-6">
                            <label for="edit_academic_year" class="form-label small fw-semibold text-secondary">Academic Year</label>
                            <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="classes.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="class_id" id="delete_class_id">

                <div class="modal-body text-center p-4">
                    <div class="stat-icon-wrapper stat-icon-rose mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.75rem;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Class?</h5>
                    <p class="text-secondary small mb-3">
                        Are you sure you want to delete <strong id="delete_class_name"></strong>?
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

<!-- UI Scripts for Modals -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit Modal Trigger
    const editModal = new bootstrap.Modal(document.getElementById('editClassModal'));
    document.querySelectorAll('.btn-edit-class').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_class_id').value = this.dataset.id;
            document.getElementById('edit_class_name').value = this.dataset.name;
            document.getElementById('edit_department_id').value = this.dataset.dept;
            document.getElementById('edit_semester').value = this.dataset.sem;
            document.getElementById('edit_academic_year').value = this.dataset.year;
            editModal.show();
        });
    });

    // Delete Modal Trigger
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteClassModal'));
    document.querySelectorAll('.btn-delete-class').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete_class_id').value = this.dataset.id;
            document.getElementById('delete_class_name').textContent = this.dataset.name;
            deleteModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
