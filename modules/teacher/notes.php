<?php
/**
 * Smart College Management Portal - Teacher Study Notes Management
 * Allows faculty to upload lecture notes, slides, syllabus PDFs, and reference materials.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce teacher server-side authorization
requireRole('teacher');

$page_title = 'Upload & Manage Study Notes';
$active_nav = 'notes';
$currentUser = getCurrentUser();

$teacherProfile  = null;
$assignedCourses = [];
$notesList       = [];

if ($pdo instanceof PDO) {
    try {
        // Fetch teacher profile
        $stmtProf = $pdo->prepare("SELECT id, department_id, employee_id FROM `teacher_profiles` WHERE `user_id` = :uid LIMIT 1");
        $stmtProf->execute([':uid' => $currentUser['id']]);
        $teacherProfile = $stmtProf->fetch();
        $teacherId = $teacherProfile['id'] ?? 0;

        // Fetch assigned courses (Subject + Class)
        if ($teacherId > 0) {
            $stmtAssign = $pdo->prepare("
                SELECT ta.id as assignment_id, ta.class_id, ta.subject_id,
                       s.subject_name, s.subject_code, c.class_name, c.semester
                FROM `teacher_assignments` ta
                JOIN `subjects` s ON ta.subject_id = s.id
                JOIN `classes` c ON ta.class_id = c.id
                WHERE ta.teacher_id = :tid
                ORDER BY c.class_name ASC, s.subject_name ASC
            ");
            $stmtAssign->execute([':tid' => $teacherId]);
            $assignedCourses = $stmtAssign->fetchAll();
        }

        // Handle POST Actions (Upload & Delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: notes.php');
                exit;
            }

            if ($action === 'upload_note') {
                $assignmentId = (int)($_POST['assignment_id'] ?? 0);
                $title        = trim($_POST['title'] ?? '');
                $description  = trim($_POST['description'] ?? '');

                // Find corresponding subject_id and class_id
                $classId   = 0;
                $subjectId = 0;
                foreach ($assignedCourses as $ac) {
                    if ((int)$ac['assignment_id'] === $assignmentId) {
                        $classId   = (int)$ac['class_id'];
                        $subjectId = (int)$ac['subject_id'];
                        break;
                    }
                }

                if (empty($title) || $classId <= 0 || $subjectId <= 0) {
                    setFlash('danger', 'Title and valid assigned course selection are required.');
                } elseif (empty($_FILES['note_file']) || $_FILES['note_file']['error'] !== UPLOAD_ERR_OK) {
                    setFlash('danger', 'Please select a valid file to upload.');
                } else {
                    $file     = $_FILES['note_file'];
                    $fileName = basename($file['name']);
                    $fileSize = $file['size'];
                    $fileTmp  = $file['tmp_name'];
                    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    // Allowed file extensions
                    $allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt'];

                    if (!in_array($fileExt, $allowedExts, true)) {
                        setFlash('danger', 'Invalid file type. Allowed formats: PDF, DOC, DOCX, PPT, PPTX, ZIP, TXT.');
                    } elseif ($fileSize > 25 * 1024 * 1024) { // 25 MB max
                        setFlash('danger', 'File is too large. Maximum file upload size is 25 MB.');
                    } else {
                        // Store file in uploads/notes/ with sanitized unique name
                        $uploadDir = __DIR__ . '/../../uploads/notes/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $safeName = 'note_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExt;
                        $destPath = $uploadDir . $safeName;
                        $relPath  = 'uploads/notes/' . $safeName;

                        if (move_uploaded_file($fileTmp, $destPath)) {
                            $stmtInsert = $pdo->prepare("
                                INSERT INTO `notes` (`subject_id`, `class_id`, `teacher_id`, `title`, `description`, `file_path`, `file_type`) 
                                VALUES (:sid, :cid, :tid, :title, :desc, :fpath, :ftype)
                            ");
                            $stmtInsert->execute([
                                ':sid'   => $subjectId,
                                ':cid'   => $classId,
                                ':tid'   => $teacherId,
                                ':title' => $title,
                                ':desc'  => $description ?: null,
                                ':fpath' => $relPath,
                                ':ftype' => $fileExt
                            ]);
                            setFlash('success', "Study note '$title' uploaded successfully.");
                        } else {
                            setFlash('danger', 'Failed to save uploaded file to server storage.');
                        }
                    }
                }
                header('Location: notes.php');
                exit;
            }

            if ($action === 'delete_note') {
                $noteId = (int)($_POST['note_id'] ?? 0);
                if ($noteId > 0) {
                    $stmtFind = $pdo->prepare("SELECT file_path FROM `notes` WHERE `id` = :id AND `teacher_id` = :tid");
                    $stmtFind->execute([':id' => $noteId, ':tid' => $teacherId]);
                    $note = $stmtFind->fetch();

                    if ($note) {
                        $filePath = __DIR__ . '/../../' . $note['file_path'];
                        if (file_exists($filePath) && is_file($filePath)) {
                            @unlink($filePath);
                        }
                        $stmtDel = $pdo->prepare("DELETE FROM `notes` WHERE `id` = :id");
                        $stmtDel->execute([':id' => $noteId]);
                        setFlash('success', 'Study note and uploaded file removed successfully.');
                    }
                }
                header('Location: notes.php');
                exit;
            }
        }

        // Fetch notes uploaded by this teacher
        if ($teacherId > 0) {
            $stmtNotes = $pdo->prepare("
                SELECT n.*, s.subject_name, s.subject_code, c.class_name, c.semester
                FROM `notes` n
                JOIN `subjects` s ON n.subject_id = s.id
                JOIN `classes` c ON n.class_id = c.id
                WHERE n.teacher_id = :tid
                ORDER BY n.uploaded_at DESC
            ");
            $stmtNotes->execute([':tid' => $teacherId]);
            $notesList = $stmtNotes->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Teacher Notes Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Teacher Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Study Notes</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Lecture Notes & Study Materials</h2>
            <p class="text-secondary small mb-0">Publish PDF presentations, reference guides, and syllabus units for your classes.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadNoteModal">
            <i class="bi bi-cloud-arrow-up-fill"></i>
            <span>Upload New Material</span>
        </button>
    </div>

    <!-- Notes Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>My Uploaded Documents</h6>
                <small class="text-muted">Total <?= count($notesList) ?> files published</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($notesList) ?> Files</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Title & Description</th>
                            <th>Course & Section</th>
                            <th>Uploaded On</th>
                            <th>File Resource</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notesList)): ?>
                            <?php foreach ($notesList as $note): ?>
                                <tr>
                                    <td>
                                        <div class="stat-icon-wrapper stat-icon-blue" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                            <i class="bi bi-file-earmark-<?= ($note['file_type'] === 'pdf') ? 'pdf' : 'text' ?>-fill"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($note['title']) ?></div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 320px;">
                                            <?= htmlspecialchars($note['description'] ?: 'No description provided.') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace me-1"><?= htmlspecialchars($note['subject_code']) ?></span>
                                        <span class="fw-semibold small text-secondary"><?= htmlspecialchars($note['class_name']) ?></span>
                                    </td>
                                    <td class="small text-muted font-monospace">
                                        <?= date('M d, Y', strtotime($note['uploaded_at'])) ?>
                                    </td>
                                    <td>
                                        <a href="../../<?= htmlspecialchars($note['file_path']) ?>" 
                                           class="btn btn-outline-primary btn-sm rounded-pill px-3" 
                                           target="_blank" 
                                           download>
                                            <i class="bi bi-download me-1"></i><?= strtoupper($note['file_type']) ?>
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="notes.php" onsubmit="return confirm('Are you sure you want to delete this study note and remove the file?');" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_note">
                                            <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Note">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                                    You have not uploaded any study notes yet. Click "Upload New Material" to share PDFs.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Upload Note -->
<div class="modal fade" id="uploadNoteModal" tabindex="-1" aria-labelledby="uploadNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="notes.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="upload_note">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="uploadNoteModalLabel">
                        <i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Upload Course Study Material
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="assignment_id" class="form-label small fw-semibold text-secondary">Course Allocation</label>
                        <select class="form-select" id="assignment_id" name="assignment_id" required>
                            <option value="">Select Target Class & Subject</option>
                            <?php foreach ($assignedCourses as $ac): ?>
                                <option value="<?= $ac['assignment_id'] ?>">
                                    [<?= htmlspecialchars($ac['class_name']) ?>] <?= htmlspecialchars($ac['subject_name']) ?> (<?= htmlspecialchars($ac['subject_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label small fw-semibold text-secondary">Document / Unit Title</label>
                        <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Unit 2: SQL Subqueries & Join Optimization" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold text-secondary">Summary / Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief topics covered in this document..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="note_file" class="form-label small fw-semibold text-secondary">Select File</label>
                        <input type="file" class="form-control" id="note_file" name="note_file" required>
                        <div class="form-text small">Supported extensions: PDF, DOC, DOCX, PPT, PPTX, ZIP, TXT (Max: 25 MB).</div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-cloud-upload me-1"></i>Publish Material
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
