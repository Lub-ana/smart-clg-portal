<?php
/**
 * Smart College Management Portal - Subjects Catalog Management
 * Allows Administrator to view, create, edit, and delete curriculum subjects.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce admin server-side authorization
requireRole('admin');

$page_title = 'Subjects Catalog Management';
$active_nav = 'subjects';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: subjects.php');
        exit;
    }

    if ($action === 'add') {
        $name     = trim($_POST['subject_name'] ?? '');
        $code     = strtoupper(trim($_POST['subject_code'] ?? ''));
        $deptId   = (int)($_POST['department_id'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 1);

        if (empty($name) || empty($code) || $deptId <= 0 || $semester <= 0) {
            setFlash('danger', 'Subject name, code, department, and semester are required.');
        } else {
            try {
                // Check code uniqueness
                $stmtCheck = $pdo->prepare("SELECT id FROM `subjects` WHERE `subject_code` = :code LIMIT 1");
                $stmtCheck->execute([':code' => $code]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', "Subject code '$code' already exists.");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO `subjects` (`department_id`, `subject_name`, `subject_code`, `semester`) 
                        VALUES (:dept, :name, :code, :sem)
                    ");
                    $stmt->execute([
                        ':dept' => $deptId,
                        ':name' => $name,
                        ':code' => $code,
                        ':sem'  => $semester
                    ]);
                    setFlash('success', "Subject '$name' ($code) has been created successfully.");
                }
            } catch (PDOException $e) {
                error_log("Add Subject Error: " . $e->getMessage());
                setFlash('danger', 'Database error while creating subject.');
            }
        }
        header('Location: subjects.php');
        exit;
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['subject_id'] ?? 0);
        $name     = trim($_POST['subject_name'] ?? '');
        $code     = strtoupper(trim($_POST['subject_code'] ?? ''));
        $deptId   = (int)($_POST['department_id'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 1);

        if ($id <= 0 || empty($name) || empty($code) || $deptId <= 0) {
            setFlash('danger', 'Valid subject fields are required for update.');
        } else {
            try {
                // Check code uniqueness excluding current subject
                $stmtCheck = $pdo->prepare("SELECT id FROM `subjects` WHERE `subject_code` = :code AND id != :id LIMIT 1");
                $stmtCheck->execute([':code' => $code, ':id' => $id]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', "Subject code '$code' is already in use by another subject.");
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE `subjects` 
                        SET `department_id` = :dept, `subject_name` = :name, `subject_code` = :code, `semester` = :sem 
                        WHERE `id` = :id
                    ");
                    $stmt->execute([
                        ':dept' => $deptId,
                        ':name' => $name,
                        ':code' => $code,
                        ':sem'  => $semester,
                        ':id'   => $id
                    ]);
                    setFlash('success', "Subject '$name' ($code) updated successfully.");
                }
            } catch (PDOException $e) {
                error_log("Edit Subject Error: " . $e->getMessage());
                setFlash('danger', 'Database error while updating subject.');
            }
        }
        header('Location: subjects.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['subject_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `subjects` WHERE `id` = :id");
                $stmt->execute([':id' => $id]);
                setFlash('success', 'Subject and linked assignments/notes removed successfully.');
            } catch (PDOException $e) {
                error_log("Delete Subject Error: " . $e->getMessage());
                setFlash('danger', 'Unable to delete subject. Academic records exist for this course.');
            }
        }
        header('Location: subjects.php');
        exit;
    }
}

// Fetch filter department
$filterDept = (int)($_GET['dept'] ?? 0);

// Fetch departments for dropdowns & filter
$departments = [];
$subjectsList = [];

if ($pdo instanceof PDO) {
    try {
        $departments = $pdo->query("SELECT id, department_name, department_code FROM `departments` ORDER BY department_name ASC")->fetchAll();

        $query = "
            SELECT s.*, d.department_name, d.department_code,
                   (SELECT COUNT(*) FROM `notes` n WHERE n.subject_id = s.id) as note_count,
                   (SELECT COUNT(*) FROM `assignments` a WHERE a.subject_id = s.id) as assignment_count,
                   (SELECT u.full_name 
                    FROM `teacher_assignments` ta 
                    JOIN `teacher_profiles` tp ON ta.teacher_id = tp.id 
                    JOIN `users` u ON tp.user_id = u.id 
                    WHERE ta.subject_id = s.id 
                    LIMIT 1) as assigned_teacher
            FROM `subjects` s
            JOIN `departments` d ON s.department_id = d.id
        ";
        if ($filterDept > 0) {
            $query .= " WHERE s.department_id = :filterDept";
        }
        $query .= " ORDER BY d.department_name ASC, s.semester ASC, s.subject_name ASC";

        $stmtSubj = $pdo->prepare($query);
        if ($filterDept > 0) {
            $stmtSubj->execute([':filterDept' => $filterDept]);
        } else {
            $stmtSubj->execute();
        }
        $subjectsList = $stmtSubj->fetchAll();

    } catch (PDOException $e) {
        error_log("Subjects Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Subjects Catalog</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Curriculum Subjects</h2>
            <p class="text-secondary small mb-0">Configure academic subjects, syllabus courses, and faculty allocations.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Add New Subject</span>
        </button>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="subjects.php" class="row g-2 align-items-center">
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
                        <a href="subjects.php" class="btn btn-sm btn-outline-secondary">Clear Filter</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Subjects Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-book text-primary me-2"></i>Curriculum Subjects Catalog</h6>
                <small class="text-muted">Total <?= count($subjectsList) ?> subjects listed</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($subjectsList) ?> Subjects</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Title</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Assigned Faculty</th>
                            <th>Study Materials</th>
                            <th>Assignments</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($subjectsList)): ?>
                            <?php foreach ($subjectsList as $subj): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                            <?= htmlspecialchars($subj['subject_code']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($subj['subject_name']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-secondary border font-monospace me-1"><?= htmlspecialchars($subj['department_code']) ?></span>
                                        <span class="small text-secondary"><?= htmlspecialchars($subj['department_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info">Sem <?= (int)$subj['semester'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($subj['assigned_teacher']): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bi bi-person-check-fill me-1"></i><?= htmlspecialchars($subj['assigned_teacher']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-journal-text me-1"></i><?= (int)$subj['note_count'] ?> Notes
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-pencil-square me-1"></i><?= (int)$subj['assignment_count'] ?> HW
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-edit-subj" 
                                                    data-id="<?= $subj['id'] ?>"
                                                    data-name="<?= htmlspecialchars($subj['subject_name']) ?>"
                                                    data-code="<?= htmlspecialchars($subj['subject_code']) ?>"
                                                    data-dept="<?= $subj['department_id'] ?>"
                                                    data-sem="<?= $subj['semester'] ?>"
                                                    title="Edit Subject">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-delete-subj" 
                                                    data-id="<?= $subj['id'] ?>"
                                                    data-name="<?= htmlspecialchars($subj['subject_name']) ?>"
                                                    title="Delete Subject">
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
                                    No subjects found. Click "Add New Subject" to create one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Add Subject -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="subjects.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addSubjectModalLabel">
                        <i class="bi bi-book-half text-primary me-2"></i>Add Curriculum Subject
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subject_name" class="form-label small fw-semibold text-secondary">Subject Title</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" placeholder="e.g. Artificial Intelligence" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="subject_code" class="form-label small fw-semibold text-secondary">Subject Code</label>
                            <input type="text" class="form-control text-uppercase" id="subject_code" name="subject_code" placeholder="e.g. CS601" required>
                        </div>
                        <div class="col-6">
                            <label for="semester" class="form-label small fw-semibold text-secondary">Semester (1 - 8)</label>
                            <input type="number" class="form-control" id="semester" name="semester" min="1" max="8" value="5" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="department_id" class="form-label small fw-semibold text-secondary">Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Create Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Subject -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="subjects.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="subject_id" id="edit_subject_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editSubjectModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Curriculum Subject
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_subject_name" class="form-label small fw-semibold text-secondary">Subject Title</label>
                        <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="edit_subject_code" class="form-label small fw-semibold text-secondary">Subject Code</label>
                            <input type="text" class="form-control text-uppercase" id="edit_subject_code" name="subject_code" required>
                        </div>
                        <div class="col-6">
                            <label for="edit_semester" class="form-label small fw-semibold text-secondary">Semester (1 - 8)</label>
                            <input type="number" class="form-control" id="edit_semester" name="semester" min="1" max="8" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label small fw-semibold text-secondary">Department</label>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?> (<?= htmlspecialchars($d['department_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
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
<div class="modal fade" id="deleteSubjectModal" tabindex="-1" aria-labelledby="deleteSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="subjects.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="subject_id" id="delete_subject_id">

                <div class="modal-body text-center p-4">
                    <div class="stat-icon-wrapper stat-icon-rose mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.75rem;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Subject?</h5>
                    <p class="text-secondary small mb-3">
                        Are you sure you want to delete <strong id="delete_subject_name"></strong>?
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
    const editModal = new bootstrap.Modal(document.getElementById('editSubjectModal'));
    document.querySelectorAll('.btn-edit-subj').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_subject_id').value = this.dataset.id;
            document.getElementById('edit_subject_name').value = this.dataset.name;
            document.getElementById('edit_subject_code').value = this.dataset.code;
            document.getElementById('edit_department_id').value = this.dataset.dept;
            document.getElementById('edit_semester').value = this.dataset.sem;
            editModal.show();
        });
    });

    // Delete Modal Trigger
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteSubjectModal'));
    document.querySelectorAll('.btn-delete-subj').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete_subject_id').value = this.dataset.id;
            document.getElementById('delete_subject_name').textContent = this.dataset.name;
            deleteModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
