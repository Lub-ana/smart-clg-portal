<?php
/**
 * Smart College Management Portal - Teacher Assignments & Grading
 * Allows faculty to create homework assignments, set deadlines, and grade student submissions.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce teacher server-side authorization
requireRole('teacher');

$page_title = 'Assignments & Grading Center';
$active_nav = 'assignments';
$currentUser = getCurrentUser();

$teacherProfile  = null;
$assignedCourses = [];
$assignmentsList = [];
$selectedAssign  = null;
$submissionsList = [];

if ($pdo instanceof PDO) {
    try {
        // Fetch teacher profile
        $stmtProf = $pdo->prepare("SELECT id, department_id, employee_id FROM `teacher_profiles` WHERE `user_id` = :uid LIMIT 1");
        $stmtProf->execute([':uid' => $currentUser['id']]);
        $teacherProfile = $stmtProf->fetch();
        $teacherId = $teacherProfile['id'] ?? 0;

        // Fetch assigned courses
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

        // Handle POST Actions (Create Assignment, Grade Submission, Delete Assignment)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: assignments.php');
                exit;
            }

            // Action: Create Assignment
            if ($action === 'create_assignment') {
                $courseAllocId = (int)($_POST['allocation_id'] ?? 0);
                $title         = trim($_POST['title'] ?? '');
                $description   = trim($_POST['description'] ?? '');
                $dueDate       = $_POST['due_date'] ?? '';
                $maxMarks      = (int)($_POST['max_marks'] ?? 100);

                $classId   = 0;
                $subjectId = 0;
                foreach ($assignedCourses as $ac) {
                    if ((int)$ac['assignment_id'] === $courseAllocId) {
                        $classId   = (int)$ac['class_id'];
                        $subjectId = (int)$ac['subject_id'];
                        break;
                    }
                }

                if (empty($title) || empty($dueDate) || $classId <= 0 || $subjectId <= 0) {
                    setFlash('danger', 'Please provide assignment title, course selection, and due date.');
                } else {
                    $attachmentPath = null;
                    if (!empty($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
                        $f     = $_FILES['attachment_file'];
                        $fExt  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                        $uDir  = __DIR__ . '/../../uploads/assignments/';
                        if (!is_dir($uDir)) {
                            mkdir($uDir, 0755, true);
                        }
                        $fName = 'asg_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $fExt;
                        if (move_uploaded_file($f['tmp_name'], $uDir . $fName)) {
                            $attachmentPath = 'uploads/assignments/' . $fName;
                        }
                    }

                    $stmtIns = $pdo->prepare("
                        INSERT INTO `assignments` (`subject_id`, `class_id`, `teacher_id`, `title`, `description`, `due_date`, `max_marks`, `attachment_path`) 
                        VALUES (:sid, :cid, :tid, :title, :desc, :due, :marks, :attach)
                    ");
                    $stmtIns->execute([
                        ':sid'    => $subjectId,
                        ':cid'    => $classId,
                        ':tid'    => $teacherId,
                        ':title'  => $title,
                        ':desc'   => $description ?: null,
                        ':due'    => $dueDate,
                        ':marks'  => $maxMarks,
                        ':attach' => $attachmentPath
                    ]);
                    setFlash('success', "Assignment '$title' created successfully.");
                }
                header('Location: assignments.php');
                exit;
            }

            // Action: Grade Student Submission
            if ($action === 'grade_submission') {
                $subId       = (int)($_POST['submission_id'] ?? 0);
                $asgId       = (int)($_POST['assignment_id'] ?? 0);
                $marks       = (float)($_POST['marks_obtained'] ?? 0);
                $feedback    = trim($_POST['feedback'] ?? '');

                if ($subId > 0) {
                    $stmtGrade = $pdo->prepare("
                        UPDATE `assignment_submissions` 
                        SET `marks_obtained` = :marks, `feedback` = :fb, `status` = 'graded' 
                        WHERE `id` = :id
                    ");
                    $stmtGrade->execute([
                        ':marks' => $marks,
                        ':fb'    => $feedback ?: null,
                        ':id'    => $subId
                    ]);
                    setFlash('success', "Submission graded successfully ($marks marks recorded).");
                }
                header("Location: assignments.php?view=$asgId");
                exit;
            }

            // Action: Delete Assignment
            if ($action === 'delete_assignment') {
                $asgId = (int)($_POST['assignment_id'] ?? 0);
                if ($asgId > 0) {
                    $stmtDel = $pdo->prepare("DELETE FROM `assignments` WHERE `id` = :id AND `teacher_id` = :tid");
                    $stmtDel->execute([':id' => $asgId, ':tid' => $teacherId]);
                    setFlash('success', 'Assignment and all student submissions were deleted successfully.');
                }
                header('Location: assignments.php');
                exit;
            }
        }

        // Fetch all assignments created by this teacher
        if ($teacherId > 0) {
            $stmtAsg = $pdo->prepare("
                SELECT a.*, s.subject_name, s.subject_code, c.class_name,
                       (SELECT COUNT(*) FROM `student_profiles` sp WHERE sp.class_id = a.class_id) as total_students,
                       (SELECT COUNT(*) FROM `assignment_submissions` sub WHERE sub.assignment_id = a.id) as submission_count,
                       (SELECT COUNT(*) FROM `assignment_submissions` sub WHERE sub.assignment_id = a.id AND sub.status = 'graded') as graded_count
                FROM `assignments` a
                JOIN `subjects` s ON a.subject_id = s.id
                JOIN `classes` c ON a.class_id = c.id
                WHERE a.teacher_id = :tid
                ORDER BY a.created_at DESC
            ");
            $stmtAsg->execute([':tid' => $teacherId]);
            $assignmentsList = $stmtAsg->fetchAll();
        }

        // Handle viewing submissions for a specific assignment
        $viewAsgId = (int)($_GET['view'] ?? 0);
        if ($viewAsgId > 0) {
            foreach ($assignmentsList as $asg) {
                if ((int)$asg['id'] === $viewAsgId) {
                    $selectedAssign = $asg;
                    break;
                }
            }

            if ($selectedAssign) {
                // Fetch all students in that class along with their submission (LEFT JOIN)
                $stmtSubs = $pdo->prepare("
                    SELECT sp.id as student_id, sp.roll_number, u.full_name, u.email,
                           sub.id as submission_id, sub.submission_text, sub.file_path,
                           sub.submitted_at, sub.marks_obtained, sub.feedback, sub.status as submission_status
                    FROM `student_profiles` sp
                    JOIN `users` u ON sp.user_id = u.id
                    LEFT JOIN `assignment_submissions` sub 
                           ON sub.assignment_id = :asg_id AND sub.student_id = sp.id
                    WHERE sp.class_id = :cid
                    ORDER BY sp.roll_number ASC
                ");
                $stmtSubs->execute([
                    ':asg_id' => $viewAsgId,
                    ':cid'    => $selectedAssign['class_id']
                ]);
                $submissionsList = $stmtSubs->fetchAll();
            }
        }

    } catch (PDOException $e) {
        error_log("Teacher Assignments Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Assignments & Grading</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Coursework Assignments & Grading</h2>
            <p class="text-secondary small mb-0">Publish tasks, download submitted student files, and record grading scores.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($selectedAssign): ?>
                <a href="assignments.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>All Assignments
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                <i class="bi bi-pencil-square"></i>
                <span>Create New Assignment</span>
            </button>
        </div>
    </div>

    <?php if ($selectedAssign): ?>
        <!-- Submissions & Grading Review View -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <span class="badge bg-light text-primary border font-monospace me-1"><?= htmlspecialchars($selectedAssign['subject_code']) ?></span>
                    <span class="fw-bold text-dark"><?= htmlspecialchars($selectedAssign['title']) ?></span>
                    <div class="small text-muted">
                        Class: <strong><?= htmlspecialchars($selectedAssign['class_name']) ?></strong> &bull; 
                        Deadline: <?= date('M d, Y h:i A', strtotime($selectedAssign['due_date'])) ?> &bull; 
                        Max Marks: <?= (int)$selectedAssign['max_marks'] ?> pts
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success">
                        <?= count(array_filter($submissionsList, fn($s) => !empty($s['submission_id']))) ?> / <?= count($submissionsList) ?> Submitted
                    </span>
                    <span class="badge bg-info-subtle text-info">
                        <?= count(array_filter($submissionsList, fn($s) => $s['submission_status'] === 'graded')) ?> Graded
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-portal mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Roll Number</th>
                                <th>Student Name</th>
                                <th>Submission Status</th>
                                <th>Turned In Date</th>
                                <th>Attached Solution</th>
                                <th>Score & Feedback</th>
                                <th class="text-end">Evaluation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($submissionsList)): ?>
                                <?php foreach ($submissionsList as $sub): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                                <?= htmlspecialchars($sub['roll_number']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($sub['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($sub['email']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($sub['submission_status'] === 'graded'): ?>
                                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Graded</span>
                                            <?php elseif (!empty($sub['submission_id'])): ?>
                                                <span class="badge bg-info-subtle text-info"><i class="bi bi-clock me-1"></i>Submitted</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= $sub['submitted_at'] ? date('M d, Y h:i A', strtotime($sub['submitted_at'])) : '&mdash;' ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['file_path']): ?>
                                                <a href="../../<?= htmlspecialchars($sub['file_path']) ?>" class="btn btn-outline-primary btn-sm" download>
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Download
                                                </a>
                                            <?php elseif (!empty($sub['submission_text'])): ?>
                                                <span class="text-muted small" title="<?= htmlspecialchars($sub['submission_text']) ?>">
                                                    <i class="bi bi-card-text me-1"></i>Text Only
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub['marks_obtained'] !== null): ?>
                                                <strong class="text-success"><?= $sub['marks_obtained'] ?> / <?= $selectedAssign['max_marks'] ?></strong>
                                                <?php if ($sub['feedback']): ?>
                                                    <div class="text-muted text-truncate" style="font-size: 0.75rem; max-width: 160px;" title="<?= htmlspecialchars($sub['feedback']) ?>">
                                                        "<?= htmlspecialchars($sub['feedback']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!empty($sub['submission_id'])): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success btn-grade-sub" 
                                                        data-id="<?= $sub['submission_id'] ?>"
                                                        data-name="<?= htmlspecialchars($sub['full_name']) ?>"
                                                        data-marks="<?= $sub['marks_obtained'] ?? '' ?>"
                                                        data-fb="<?= htmlspecialchars($sub['feedback'] ?? '') ?>"
                                                        data-text="<?= htmlspecialchars($sub['submission_text'] ?? '') ?>">
                                                    <i class="bi bi-award me-1"></i>Grade
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">Awaiting</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No students enrolled in this section.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- All Assignments Overview Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-journal-check text-primary me-2"></i>Active Course Assignments</h6>
                    <small class="text-muted">Total <?= count($assignmentsList) ?> assignments published</small>
                </div>
                <span class="badge bg-light text-secondary border"><?= count($assignmentsList) ?> Assignments</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-portal mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Assignment Title</th>
                                <th>Subject & Section</th>
                                <th>Due Date</th>
                                <th>Max Points</th>
                                <th>Submission Turnout</th>
                                <th>Graded Count</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignmentsList)): ?>
                                <?php foreach ($assignmentsList as $asg): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($asg['title']) ?></div>
                                            <small class="text-muted text-truncate d-block" style="max-width: 280px;">
                                                <?= htmlspecialchars($asg['description'] ?: 'No instructions provided.') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-primary border font-monospace me-1"><?= htmlspecialchars($asg['subject_code']) ?></span>
                                            <span class="fw-semibold small text-secondary"><?= htmlspecialchars($asg['class_name']) ?></span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold <?= (strtotime($asg['due_date']) < time()) ? 'text-danger' : 'text-dark' ?>">
                                                <?= date('M d, Y', strtotime($asg['due_date'])) ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($asg['due_date'])) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border font-monospace"><?= (int)$asg['max_marks'] ?> pts</span></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary">
                                                <?= (int)$asg['submission_count'] ?> / <?= (int)$asg['total_students'] ?> Submitted
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success">
                                                <?= (int)$asg['graded_count'] ?> Graded
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="assignments.php?view=<?= $asg['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye me-1"></i>Review & Grade
                                            </a>
                                            <form method="POST" action="assignments.php" onsubmit="return confirm('Delete this assignment and all student submissions?');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_assignment">
                                                <input type="hidden" name="assignment_id" value="<?= $asg['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Assignment">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-pencil-square fs-2 d-block mb-2"></i>
                                        No assignments created yet. Click "Create New Assignment" to post coursework.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- Modal: Create Assignment -->
<div class="modal fade" id="createAssignmentModal" tabindex="-1" aria-labelledby="createAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="assignments.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create_assignment">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="createAssignmentModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Create Coursework Assignment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="allocation_id" class="form-label small fw-semibold text-secondary">Course & Target Class</label>
                            <select class="form-select" id="allocation_id" name="allocation_id" required>
                                <option value="">Select Target Course</option>
                                <?php foreach ($assignedCourses as $ac): ?>
                                    <option value="<?= $ac['assignment_id'] ?>">
                                        [<?= htmlspecialchars($ac['class_name']) ?>] <?= htmlspecialchars($ac['subject_name']) ?> (<?= htmlspecialchars($ac['subject_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="max_marks" class="form-label small fw-semibold text-secondary">Maximum Marks / Points</label>
                            <input type="number" class="form-control" id="max_marks" name="max_marks" value="50" min="1" max="1000" required>
                        </div>

                        <div class="col-12">
                            <label for="title" class="form-label small fw-semibold text-secondary">Assignment Title</label>
                            <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Lab Exercise 2: Normalization and BCNF Conversion" required>
                        </div>

                        <div class="col-md-6">
                            <label for="due_date" class="form-label small fw-semibold text-secondary">Submission Deadline</label>
                            <input type="datetime-local" class="form-control" id="due_date" name="due_date" required>
                        </div>

                        <div class="col-md-6">
                            <label for="attachment_file" class="form-label small fw-semibold text-secondary">Problem Statement Attachment (Optional)</label>
                            <input type="file" class="form-control" id="attachment_file" name="attachment_file">
                            <div class="form-text small">PDF, DOCX, ZIP problem sheet.</div>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold text-secondary">Instructions / Question Details</label>
                            <textarea class="form-control" id="description" name="description" rows="4" placeholder="Detail the questions, submission guidelines, and evaluation rubrics..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i>Publish Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Grade Submission -->
<div class="modal fade" id="gradeSubmissionModal" tabindex="-1" aria-labelledby="gradeSubmissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="assignments.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="grade_submission">
                <input type="hidden" name="submission_id" id="grade_sub_id">
                <input type="hidden" name="assignment_id" value="<?= $selectedAssign['id'] ?? 0 ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="gradeSubmissionModalLabel">
                        <i class="bi bi-award text-success me-2"></i>Evaluate Submission
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="p-2 mb-3 bg-light rounded small">
                        <strong>Student:</strong> <span id="grade_student_name"></span>
                    </div>

                    <div id="grade_text_container" class="mb-3 d-none">
                        <label class="form-label small fw-semibold text-secondary">Student Answer Note:</label>
                        <div class="p-2 bg-light border rounded small" id="grade_student_text"></div>
                    </div>

                    <div class="mb-3">
                        <label for="grade_marks" class="form-label small fw-semibold text-secondary">Marks Awarded (Out of <?= $selectedAssign['max_marks'] ?? 100 ?>)</label>
                        <input type="number" step="0.5" min="0" max="<?= $selectedAssign['max_marks'] ?? 100 ?>" class="form-control" id="grade_marks" name="marks_obtained" required>
                    </div>

                    <div class="mb-3">
                        <label for="grade_feedback" class="form-label small fw-semibold text-secondary">Teacher Feedback</label>
                        <textarea class="form-control" id="grade_feedback" name="feedback" rows="3" placeholder="Praise, corrections, or constructive suggestions..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Save Grade & Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Grade Handler Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const gradeModalEl = document.getElementById('gradeSubmissionModal');
    if (gradeModalEl) {
        const gradeModal = new bootstrap.Modal(gradeModalEl);
        document.querySelectorAll('.btn-grade-sub').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('grade_sub_id').value = this.dataset.id;
                document.getElementById('grade_student_name').textContent = this.dataset.name;
                document.getElementById('grade_marks').value = this.dataset.marks;
                document.getElementById('grade_feedback').value = this.dataset.fb;

                const txt = this.dataset.text;
                const txtContainer = document.getElementById('grade_text_container');
                if (txt && txt.trim().length > 0) {
                    document.getElementById('grade_student_text').textContent = txt;
                    txtContainer.classList.remove('d-none');
                } else {
                    txtContainer.classList.add('d-none');
                }

                gradeModal.show();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
