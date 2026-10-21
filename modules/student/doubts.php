<?php
/**
 * Smart College Management Portal - Student Doubts & Discussion Forum
 * Allows students to ask academic questions, review teacher clarifications,
 * and engage in subject discussion threads.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce student server-side authorization
requireRole('student');

$page_title = 'Doubts & Q&A Discussions';
$active_nav = 'doubts';
$currentUser = getCurrentUser();

$studentProfile = null;
$subjectsList   = [];
$doubtsList     = [];
$filterSubject  = (int)($_GET['subject'] ?? 0);
$filterStatus   = trim($_GET['status'] ?? '');

if ($pdo instanceof PDO) {
    try {
        // Fetch student profile
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
        $deptId    = $studentProfile['department_id'] ?? 0;

        // Fetch subjects for this student's department/semester
        if ($deptId > 0) {
            $stmtSubjs = $pdo->prepare("SELECT id, subject_name, subject_code FROM `subjects` WHERE `department_id` = :dept ORDER BY `subject_name` ASC");
            $stmtSubjs->execute([':dept' => $deptId]);
            $subjectsList = $stmtSubjs->fetchAll();
        }

        // Handle POST Actions (Create Doubt & Reply to Thread)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: doubts.php');
                exit;
            }

            if ($action === 'create_doubt') {
                $subjectId = (int)($_POST['subject_id'] ?? 0);
                $title     = trim($_POST['title'] ?? '');
                $desc      = trim($_POST['description'] ?? '');

                if ($subjectId <= 0 || empty($title) || empty($desc)) {
                    setFlash('danger', 'Subject selection, question title, and detailed description are required.');
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO `doubts` (`student_id`, `subject_id`, `title`, `description`, `status`) 
                        VALUES (:sid, :sub_id, :title, :desc, 'open')
                    ");
                    $stmtIns->execute([
                        ':sid'    => $studentId,
                        ':sub_id' => $subjectId,
                        ':title'  => $title,
                        ':desc'   => $desc
                    ]);
                    setFlash('success', "Your question '$title' has been posted to course instructors.");
                }
                header('Location: doubts.php');
                exit;
            }

            if ($action === 'reply_doubt') {
                $doubtId = (int)($_POST['doubt_id'] ?? 0);
                $reply   = trim($_POST['response_text'] ?? '');

                if ($doubtId <= 0 || empty($reply)) {
                    setFlash('danger', 'Reply text cannot be empty.');
                } else {
                    $stmtReply = $pdo->prepare("
                        INSERT INTO `doubt_responses` (`doubt_id`, `user_id`, `response_text`) 
                        VALUES (:did, :uid, :reply)
                    ");
                    $stmtReply->execute([
                        ':did'   => $doubtId,
                        ':uid'   => $currentUser['id'],
                        ':reply' => $reply
                    ]);
                    setFlash('success', 'Your follow-up reply was posted to the discussion thread.');
                }
                header("Location: doubts.php#doubt-$doubtId");
                exit;
            }
        }

        // Fetch doubts for this student
        if ($studentId > 0) {
            $query = "
                SELECT d.*, s.subject_name, s.subject_code,
                       (SELECT COUNT(*) FROM `doubt_responses` dr WHERE dr.doubt_id = d.id) as response_count
                FROM `doubts` d
                JOIN `subjects` s ON d.subject_id = s.id
                WHERE d.student_id = :sid
            ";
            $params = [':sid' => $studentId];

            if ($filterSubject > 0) {
                $query .= " AND d.subject_id = :subj";
                $params[':subj'] = $filterSubject;
            }
            if (!empty($filterStatus)) {
                $query .= " AND d.status = :status";
                $params[':status'] = $filterStatus;
            }

            $query .= " ORDER BY d.created_at DESC";

            $stmtDoubts = $pdo->prepare($query);
            $stmtDoubts->execute($params);
            $doubtsList = $stmtDoubts->fetchAll();

            // Fetch responses for all loaded doubts
            $doubtIds = array_column($doubtsList, 'id');
            $responsesByDoubt = [];

            if (!empty($doubtIds)) {
                $inClause = implode(',', array_map('intval', $doubtIds));
                $stmtResps = $pdo->query("
                    SELECT dr.*, u.full_name, u.role, u.username
                    FROM `doubt_responses` dr
                    JOIN `users` u ON dr.user_id = u.id
                    WHERE dr.doubt_id IN ($inClause)
                    ORDER BY dr.created_at ASC
                ");
                while ($r = $stmtResps->fetch()) {
                    $responsesByDoubt[$r['doubt_id']][] = $r;
                }
            }
        }

    } catch (PDOException $e) {
        error_log("Student Doubts Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Doubts & Discussions</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Academic Doubts & Q&A Forum</h2>
            <p class="text-secondary small mb-0">Ask conceptual questions directly to subject instructors and review official solutions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
            </a>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#askDoubtModal">
                <i class="bi bi-question-circle-fill"></i>
                <span>Ask a New Question</span>
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="doubts.php" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="subject" onchange="this.form.submit()">
                        <option value="0">All Subjects</option>
                        <?php foreach ($subjectsList as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($filterSubject === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['subject_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="">All Statuses (Open & Answered)</option>
                        <option value="open" <?= ($filterStatus === 'open') ? 'selected' : '' ?>>Open Inquiries</option>
                        <option value="answered" <?= ($filterStatus === 'answered') ? 'selected' : '' ?>>Answered by Teacher</option>
                        <option value="closed" <?= ($filterStatus === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <?php if ($filterSubject > 0 || !empty($filterStatus)): ?>
                    <div class="col-auto">
                        <a href="doubts.php" class="btn btn-sm btn-outline-secondary">Reset Filters</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Doubts List -->
    <div class="d-flex flex-column gap-4 mb-4">
        <?php if (!empty($doubtsList)): ?>
            <?php foreach ($doubtsList as $doubt): ?>
                <?php 
                    $resps = $responsesByDoubt[$doubt['id']] ?? [];
                    $isAnswered = ($doubt['status'] === 'answered');
                ?>
                <div class="card border-0 shadow-sm" id="doubt-<?= $doubt['id'] ?>">
                    
                    <!-- Doubt Card Header -->
                    <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-primary border font-monospace">
                                <?= htmlspecialchars($doubt['subject_code']) ?>
                            </span>
                            <span class="fw-semibold text-secondary small"><?= htmlspecialchars($doubt['subject_name']) ?></span>
                            <span class="text-muted">&bull;</span>
                            <span class="small text-muted font-monospace"><i class="bi bi-clock me-1"></i><?= date('M d, Y h:i A', strtotime($doubt['created_at'])) ?></span>
                        </div>
                        <div>
                            <?php if ($doubt['status'] === 'answered'): ?>
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Answered</span>
                            <?php elseif ($doubt['status'] === 'open'): ?>
                                <span class="badge bg-warning-subtle text-warning"><i class="bi bi-hourglass me-1"></i>Awaiting Reply</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">Closed</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Doubt Body -->
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($doubt['title']) ?></h5>
                        <p class="text-secondary small mb-3"><?= nl2br(htmlspecialchars($doubt['description'])) ?></p>

                        <!-- Discussion Replies Thread -->
                        <?php if (!empty($resps)): ?>
                            <div class="p-3 bg-light rounded mt-3 border">
                                <h6 class="small fw-bold text-secondary text-uppercase mb-3" style="letter-spacing: 0.05em;">
                                    <i class="bi bi-chat-left-dots-fill text-primary me-2"></i>Instructor Clarifications (<?= count($resps) ?>)
                                </h6>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($resps as $r): ?>
                                        <div class="p-3 bg-white rounded border shadow-sm <?= ($r['role'] === 'teacher') ? 'border-primary-subtle' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold small text-dark"><?= htmlspecialchars($r['full_name']) ?></span>
                                                    <?php if ($r['role'] === 'teacher'): ?>
                                                        <span class="badge badge-role-teacher small"><i class="bi bi-person-check-fill me-1"></i>Course Faculty</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-role-student small">Student</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="small text-muted font-monospace"><?= date('M d, h:i A', strtotime($r['created_at'])) ?></span>
                                            </div>
                                            <div class="small text-secondary"><?= nl2br(htmlspecialchars($r['response_text'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Follow-up Form -->
                        <div class="mt-3 pt-3 border-top">
                            <form method="POST" action="doubts.php" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="reply_doubt">
                                <input type="hidden" name="doubt_id" value="<?= $doubt['id'] ?>">
                                <input type="text" 
                                       class="form-control form-control-sm" 
                                       name="response_text" 
                                       placeholder="Post a follow-up clarification..." 
                                       required>
                                <button type="submit" class="btn btn-outline-primary btn-sm px-3 flex-shrink-0">
                                    <i class="bi bi-send me-1"></i>Reply
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-chat-quote fs-1 d-block mb-3 text-secondary"></i>
                <h5>No Questions Found</h5>
                <p class="small mb-0">You have not submitted any doubts for this subject. Click "Ask a New Question" to consult your instructors.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- Modal: Ask Doubt -->
<div class="modal fade" id="askDoubtModal" tabindex="-1" aria-labelledby="askDoubtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="doubts.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create_doubt">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="askDoubtModalLabel">
                        <i class="bi bi-question-circle-fill text-primary me-2"></i>Ask an Academic Doubt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_subject" class="form-label small fw-semibold text-secondary">Course Subject</label>
                        <select class="form-select" id="modal_subject" name="subject_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($subjectsList as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['subject_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_title" class="form-label small fw-semibold text-secondary">Question Topic / Subject Line</label>
                        <input type="text" class="form-control" id="modal_title" name="title" placeholder="e.g. When is 3NF preferred over BCNF in production systems?" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal_desc" class="form-label small fw-semibold text-secondary">Detailed Problem Description</label>
                        <textarea class="form-control" id="modal_desc" name="description" rows="4" placeholder="Explain the specific concept, homework query, or error you need assistance with..." required></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-send-fill me-1"></i>Post Question
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
