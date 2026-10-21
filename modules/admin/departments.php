<?php
/**
 * Smart College Management Portal - Departments Management
 * Allows Administrator to view, create, update, and remove academic departments.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce admin server-side authorization
requireRole('admin');

$page_title = 'Department Management';
$active_nav = 'departments';

// Handle POST actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: departments.php');
        exit;
    }

    if ($action === 'add') {
        $name = trim($_POST['department_name'] ?? '');
        $code = strtoupper(trim($_POST['department_code'] ?? ''));
        $desc = trim($_POST['description'] ?? '');

        if (empty($name) || empty($code)) {
            setFlash('danger', 'Department name and code are required fields.');
        } else {
            try {
                // Check code uniqueness
                $stmtCheck = $pdo->prepare("SELECT id FROM `departments` WHERE `department_code` = :code LIMIT 1");
                $stmtCheck->execute([':code' => $code]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', "Department code '$code' already exists.");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `departments` (`department_name`, `department_code`, `description`) VALUES (:name, :code, :desc)");
                    $stmt->execute([
                        ':name' => $name,
                        ':code' => $code,
                        ':desc' => $desc
                    ]);
                    setFlash('success', "Department '$name' ($code) has been created successfully.");
                }
            } catch (PDOException $e) {
                error_log("Add Department Error: " . $e->getMessage());
                setFlash('danger', 'Database error while creating department: ' . $e->getMessage());
            }
        }
        header('Location: departments.php');
        exit;
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['department_name'] ?? '');
        $code = strtoupper(trim($_POST['department_code'] ?? ''));
        $desc = trim($_POST['description'] ?? '');

        if ($id <= 0 || empty($name) || empty($code)) {
            setFlash('danger', 'Valid department ID, name, and code are required.');
        } else {
            try {
                // Check code uniqueness excluding current department
                $stmtCheck = $pdo->prepare("SELECT id FROM `departments` WHERE `department_code` = :code AND id != :id LIMIT 1");
                $stmtCheck->execute([':code' => $code, ':id' => $id]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', "Department code '$code' is already in use by another department.");
                } else {
                    $stmt = $pdo->prepare("UPDATE `departments` SET `department_name` = :name, `department_code` = :code, `description` = :desc WHERE `id` = :id");
                    $stmt->execute([
                        ':name' => $name,
                        ':code' => $code,
                        ':desc' => $desc,
                        ':id'   => $id
                    ]);
                    setFlash('success', "Department '$name' ($code) updated successfully.");
                }
            } catch (PDOException $e) {
                error_log("Edit Department Error: " . $e->getMessage());
                setFlash('danger', 'Database error while updating department.');
            }
        }
        header('Location: departments.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['department_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `departments` WHERE `id` = :id");
                $stmt->execute([':id' => $id]);
                setFlash('success', 'Department and all associated records have been removed successfully.');
            } catch (PDOException $e) {
                error_log("Delete Department Error: " . $e->getMessage());
                setFlash('danger', 'Unable to delete department. Sub-records exist or foreign key constraint triggered.');
            }
        }
        header('Location: departments.php');
        exit;
    }
}

// Fetch all departments with associated metrics
$departments = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("
            SELECT d.*,
                   (SELECT COUNT(*) FROM `classes` c WHERE c.department_id = d.id) as class_count,
                   (SELECT COUNT(*) FROM `subjects` s WHERE s.department_id = d.id) as subject_count,
                   (SELECT COUNT(*) FROM `student_profiles` sp WHERE sp.department_id = d.id) as student_count,
                   (SELECT COUNT(*) FROM `teacher_profiles` tp WHERE tp.department_id = d.id) as teacher_count
            FROM `departments` d
            ORDER BY d.id ASC
        ");
        $departments = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Departments List Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Departments</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Academic Departments</h2>
            <p class="text-secondary small mb-0">Create and oversee organizational engineering & science departments.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Add New Department</span>
        </button>
    </div>

    <!-- Departments Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-building text-primary me-2"></i>Departments Directory</h6>
                <small class="text-muted">Total <?= count($departments) ?> departments configured</small>
            </div>
            <span class="badge bg-primary-subtle text-primary"><?= count($departments) ?> Active Branches</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Description</th>
                            <th>Classes</th>
                            <th>Subjects</th>
                            <th>Faculty / Students</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><code>#<?= $dept['id'] ?></code></td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                            <?= htmlspecialchars($dept['department_code']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($dept['department_name']) ?></td>
                                    <td class="text-muted small" style="max-width: 250px;">
                                        <?= htmlspecialchars($dept['description'] ?: 'No description provided.') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-diagram-2 me-1"></i><?= (int)$dept['class_count'] ?> Classes
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-book me-1"></i><?= (int)$dept['subject_count'] ?> Subjects
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success small me-1">
                                            <i class="bi bi-person-video3 me-1"></i><?= (int)$dept['teacher_count'] ?> Faculty
                                        </span>
                                        <span class="badge bg-info-subtle text-info small">
                                            <i class="bi bi-people me-1"></i><?= (int)$dept['student_count'] ?> Students
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-edit-dept" 
                                                    data-id="<?= $dept['id'] ?>"
                                                    data-name="<?= htmlspecialchars($dept['department_name']) ?>"
                                                    data-code="<?= htmlspecialchars($dept['department_code']) ?>"
                                                    data-desc="<?= htmlspecialchars($dept['description'] ?? '') ?>"
                                                    title="Edit Department">
                                                <i class="bi bi-pencil-square text-primary"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-delete-dept" 
                                                    data-id="<?= $dept['id'] ?>"
                                                    data-name="<?= htmlspecialchars($dept['department_name']) ?>"
                                                    title="Delete Department">
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
                                    No departments found. Click "Add New Department" to create one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addDepartmentModalLabel">
                        <i class="bi bi-building-add text-primary me-2"></i>Add Academic Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_name" class="form-label small fw-semibold text-secondary">Department Name</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" placeholder="e.g. Mechanical Engineering" required>
                    </div>

                    <div class="mb-3">
                        <label for="department_code" class="form-label small fw-semibold text-secondary">Department Code (Unique)</label>
                        <input type="text" class="form-control text-uppercase" id="department_code" name="department_code" placeholder="e.g. MECH" maxlength="20" required>
                        <div class="form-text small">Short uppercase identifier (e.g. CSE, IT, ECE, MECH).</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold text-secondary">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief overview of department..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Create Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="department_id" id="edit_department_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editDepartmentModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_department_name" class="form-label small fw-semibold text-secondary">Department Name</label>
                        <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department_code" class="form-label small fw-semibold text-secondary">Department Code</label>
                        <input type="text" class="form-control text-uppercase" id="edit_department_code" name="department_code" maxlength="20" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label small fw-semibold text-secondary">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="departments.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="department_id" id="delete_department_id">

                <div class="modal-body text-center p-4">
                    <div class="stat-icon-wrapper stat-icon-rose mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.75rem;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete Department?</h5>
                    <p class="text-secondary small mb-3">
                        Are you sure you want to delete <strong id="delete_department_name"></strong>?
                        This will cascade remove linked classes and subjects.
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
    const editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
    document.querySelectorAll('.btn-edit-dept').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_department_id').value = this.dataset.id;
            document.getElementById('edit_department_name').value = this.dataset.name;
            document.getElementById('edit_department_code').value = this.dataset.code;
            document.getElementById('edit_description').value = this.dataset.desc;
            editModal.show();
        });
    });

    // Delete Modal Trigger
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
    document.querySelectorAll('.btn-delete-dept').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('delete_department_id').value = this.dataset.id;
            document.getElementById('delete_department_name').textContent = this.dataset.name;
            deleteModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
