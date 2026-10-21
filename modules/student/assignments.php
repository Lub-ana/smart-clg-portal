<?php
/**
 * Smart College Management Portal - Student Assignments Portal
 * Allows students to view coursework tasks, download prompt sheets,
 * turn in homework files, and review teacher feedback and scores.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce student server-side authorization
requireRole('student');

$page_title = 'My Coursework Assignments';
$active_nav = 'assignments';
$currentUser = getCurrentUser();

$studentProfile  = null;
$assignmentsList = [];
$stats = [
    'total'     => 0,
    'submitted' => 0,
    'graded'    => 0,
    'pending'   => 0
];

if ($pdo instanceof PDO) {
    try {
        // Fetch student profile details
        $stmtProf = $pdo->prepare("
            SELECT sp.*, c.class_name, c.semester, d.department_name, d.department_code
            FROM `student_profiles` sp
            JOIN `classes` c ON sp.class_id = c.id
            JOIN `departments` d ON sp.department_id = d.id
            WHERE sp.user_id = :uid
            LIMIT 1
        ");
        $stmtProf->execute([':uid' => $currentUser['id']]);
        $studentProfile = $stmtProf->fetch();
        $studentId = $studentProfile['id'] ?? 0;
        $classId   = $studentProfile['class_id'] ?? 0;

        // Handle POST Submission (Turn in Homework)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: assignments.php');
                exit;
            }

            if ($action === 'submit_homework') {
                $asgId       = (int)($_POST['assignment_id'] ?? 0);
                $answerText  = trim($_POST['submission_text'] ?? '');

                // Verify assignment exists and belongs to this student's class
                $stmtCheck = $pdo->prepare("SELECT id, due_date FROM `assignments` WHERE `id` = :id AND `class_id` = :cid LIMIT 1");
                $stmtCheck->execute([':id' => $asgId, ':cid' => $classId]);
                $asg = $stmtCheck->fetch();

                if (!$asg) {
                    setFlash('danger', 'Invalid assignment selection for your class section.');
                } else {
                    $filePath = null;

                    if (!empty($_FILES['solution_file']) && $_FILES['solution_file']['error'] === UPLOAD_ERR_OK) {
                        $f     = $_FILES['solution_file'];
                        $fExt  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                        $allowedExts = ['pdf', 'doc', 'docx', 'zip', 'txt', 'sql'];

                        if (!in_array($fExt, $allowedExts, true)) {
                            setFlash('danger', 'Invalid file type. Allowed formats: PDF, DOC, DOCX, ZIP, TXT, SQL.');
                            header('Location: assignments.php');
                            exit;
                        }

                        $uDir = __DIR__ . '/../../uploads/submissions/';
                        if (!is_dir($uDir)) {
                            mkdir($uDir, 0755, true);
                        }

                        $fName = 'sub_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $fExt;
                        if (move_uploaded_file($f['tmp_name'], $uDir . $fName)) {
                            $filePath = 'uploads/submissions/' . $fName;
                        }
                    }

                    if (empty($filePath) && empty($answerText)) {
                        setFlash('danger', 'Please provide either a solution file or submission comments.');
                    } else {
                        // Check if past due date
                        $status = (strtotime($asg['due_date']) < time()) ? 'late' : 'submitted';

                        // Upsert into assignment_submissions
                        $stmtUpsert = $pdo->prepare("
                            INSERT INTO `assignment_submissions` 
                            (`assignment_id`, `student_id`, `submission_text`, `file_path`, `status`) 
                            VALUES (:aid, :sid, :stext, :fpath, :status)
                            ON DUPLICATE KEY UPDATE 
                                `submission_text` = VALUES(`submission_text`),
                                `file_path` = COALESCE(VALUES(`file_path`), `file_path`),
                                `submitted_at` = CURRENT_TIMESTAMP,
                                `status` = VALUES(`status`)
                        ");
                        $stmtUpsert->execute([
                            ':aid'   => $asgId,
                            ':sid'   => $studentId,
                            ':stext' => $answerText ?: null,
                            ':fpath' => $filePath,
                            ':status'=> $status
                        ]);

                        setFlash('success', 'Your assignment solution has been turned in successfully.');
                    }
                }
                header('Location: assignments.php');
                exit;
            }
        }

        // Fetch coursework assignments for this student's class
        if ($classId > 0 && $studentId > 0) {
            $stmtAsg = $pdo->prepare("
                SELECT a.*, s.subject_name, s.subject_code,
                       u.full_name as teacher_name,
                       sub.id as submission_id, sub.submission_text, sub.file_path,
                       sub.submitted_at, sub.marks_obtained, sub.feedback, sub.status as submission_status
                FROM `assignments` a
                JOIN `subjects` s ON a.subject_id = s.id
                JOIN `teacher_profiles` tp ON a.teacher_id = tp.id
                JOIN `users` u ON tp.user_id = u.id
                LEFT JOIN `assignment_submissions` sub 
                       ON a.id = sub.assignment_id AND sub.student_id = :sid
                WHERE a.class_id = :cid
                ORDER BY a.due_date ASC
            ");
            $stmtAsg->execute([':sid' => $studentId, ':cid' => $classId]);
            $assignmentsList = $stmtAsg->fetchAll();

            $stats['total'] = count($assignmentsList);
            foreach ($assignmentsList as $asg) {
                if ($asg['submission_status'] === 'graded') {
                    $stats['graded']++;
                    $stats['submitted']++;
                } elseif (!empty($asg['submission_id'])) {
                    $stats['submitted']++;
                } else {
                    $stats['pending']++;
                }
            }
        }

    } catch (PDOException $e) {
        error_log("Student Assignments Query Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Student Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Assignments</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">My Coursework Assignments</h2>
            <p class="text-secondary small mb-0">
                Turn in homework, track evaluation scores, and review faculty feedback for 
                <strong><?= htmlspecialchars($studentProfile['class_name'] ?? 'Class') ?></strong>.
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
        </a>
    </div>

    <!-- Stat Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Tasks</span>
                    <div class="stat-icon-wrapper stat-icon-blue">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['total'] ?></div>
                <small class="text-muted">Assigned This Term</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Submitted</span>
                    <div class="stat-icon-wrapper stat-icon-cyan">
                        <i class="bi bi-send-check-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['submitted'] ?></div>
                <small class="text-muted">Turned In Work</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Graded</span>
                    <div class="stat-icon-wrapper stat-icon-green">
                        <i class="bi bi-award-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['graded'] ?></div>
                <small class="text-muted">Scores Recorded</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending</span>
                    <div class="stat-icon-wrapper <?= ($stats['pending'] > 0) ? 'stat-icon-amber' : 'stat-icon-green' ?>">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['pending'] ?></div>
                <small class="text-muted">Requires Your Action</small>
            </div>
        </div>
    </div>

    <!-- Assignments List Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Class Assignment Tasks</h6>
                <small class="text-muted">Active and completed assignments for your section</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($assignmentsList) ?> Total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Assignment Title</th>
                            <th>Deadline</th>
                            <th>Max Marks</th>
                            <th>Problem Sheet</th>
                            <th>Submission Status</th>
                            <th>Score Awarded</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assignmentsList)): ?>
                            <?php foreach ($assignmentsList as $asg): ?>
                                <?php 
                                    $isOverdue = (strtotime($asg['due_date']) < time());
                                    $hasSubmitted = !empty($asg['submission_id']);
                                    $isGraded = ($asg['submission_status'] === 'graded');
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace">
                                            <?= htmlspecialchars($asg['subject_code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($asg['title']) ?></div>
                                        <small class="text-muted">Instructor: <?= htmlspecialchars($asg['teacher_name']) ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold <?= ($isOverdue && !$hasSubmitted) ? 'text-danger' : 'text-dark' ?>">
                                            <?= date('M d, Y', strtotime($asg['due_date'])) ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($asg['due_date'])) ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border font-monospace"><?= (int)$asg['max_marks'] ?> pts</span></td>
                                    <td>
                                        <?php if ($asg['attachment_path']): ?>
                                            <a href="../../<?= htmlspecialchars($asg['attachment_path']) ?>" class="btn btn-outline-secondary btn-sm" download>
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Prompt PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isGraded): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Graded</span>
                                        <?php elseif ($hasSubmitted): ?>
                                            <span class="badge bg-info-subtle text-info"><i class="bi bi-send-check me-1"></i>Turned In</span>
                                        <?php elseif ($isOverdue): ?>
                                            <span class="badge bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning"><i class="bi bi-hourglass me-1"></i>Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($asg['marks_obtained'] !== null): ?>
                                            <strong class="text-success"><?= $asg['marks_obtained'] ?> / <?= $asg['max_marks'] ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isGraded): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-success btn-view-feedback" 
                                                    data-title="<?= htmlspecialchars($asg['title']) ?>"
                                                    data-score="<?= $asg['marks_obtained'] ?> / <?= $asg['max_marks'] ?>"
                                                    data-fb="<?= htmlspecialchars($asg['feedback'] ?: 'No written remarks provided.') ?>">
                                                <i class="bi bi-chat-left-text me-1"></i>Feedback
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary btn-submit-hw" 
                                                    data-id="<?= $asg['id'] ?>"
                                                    data-title="<?= htmlspecialchars($asg['title']) ?>"
                                                    data-max="<?= $asg['max_marks'] ?>"
                                                    data-desc="<?= htmlspecialchars($asg['description'] ?? '') ?>">
                                                <i class="bi bi-cloud-arrow-up me-1"></i><?= $hasSubmitted ? 'Resubmit' : 'Submit' ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                                    No assignments have been assigned to your class yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal: Submit Homework Solution -->
<div class="modal fade" id="submitHomeworkModal" tabindex="-1" aria-labelledby="submitHomeworkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="assignments.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="submit_homework">
                <input type="hidden" name="assignment_id" id="modal_asg_id">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="submitHomeworkModalLabel">
                        <i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Turn In Assignment Solution
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="p-2 mb-3 bg-light rounded small">
                        <strong>Task:</strong> <span id="modal_asg_title"></span>
                        <div class="text-muted mt-1" id="modal_asg_desc"></div>
                    </div>

                    <div class="mb-3">
                        <label for="solution_file" class="form-label small fw-semibold text-secondary">Upload Solution Document</label>
                        <input type="file" class="form-control" id="solution_file" name="solution_file">
                        <div class="form-text small">Accepted formats: PDF, DOC, DOCX, ZIP, TXT, SQL.</div>
                    </div>

                    <div class="mb-3">
                        <label for="submission_text" class="form-label small fw-semibold text-secondary">Answer Text / Student Comments (Optional)</label>
                        <textarea class="form-control" id="submission_text" name="submission_text" rows="3" placeholder="Enter answers or submission note for the instructor..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-lg me-1"></i>Confirm & Turn In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View Feedback -->
<div class="modal fade" id="viewFeedbackModal" tabindex="-1" aria-labelledby="viewFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="viewFeedbackModalLabel">
                    <i class="bi bi-award-fill text-success me-2"></i>Evaluation Result & Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="stat-icon-wrapper stat-icon-green mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1" id="feedback_title"></h5>
                <div class="h3 fw-bold text-success mb-3" id="feedback_score"></div>
                <div class="p-3 bg-light border rounded text-start">
                    <label class="small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.7rem;">Instructor Feedback:</label>
                    <p class="mb-0 small text-dark fst-italic" id="feedback_text"></p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary btn-sm px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- UI Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Submit Homework Modal
    const subModal = new bootstrap.Modal(document.getElementById('submitHomeworkModal'));
    document.querySelectorAll('.btn-submit-hw').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modal_asg_id').value = this.dataset.id;
            document.getElementById('modal_asg_title').textContent = this.dataset.title;
            document.getElementById('modal_asg_desc').textContent = this.dataset.desc || '';
            subModal.show();
        });
    });

    // View Feedback Modal
    const fbModal = new bootstrap.Modal(document.getElementById('viewFeedbackModal'));
    document.querySelectorAll('.btn-view-feedback').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('feedback_title').textContent = this.dataset.title;
            document.getElementById('feedback_score').textContent = this.dataset.score;
            document.getElementById('feedback_text').textContent = this.dataset.fb;
            fbModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
