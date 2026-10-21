<?php
/**
 * Smart College Management Portal - Teacher Doubts Resolution Center
 * Allows faculty to review student academic inquiries, post official answers,
 * and engage in subject discussion threads.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce teacher server-side authorization
requireRole('teacher');

$page_title = 'Resolve Student Doubts';
$active_nav = 'doubts';
$currentUser = getCurrentUser();

$teacherProfile  = null;
$assignedCourses = [];
$doubtsList      = [];
$stats = [
    'total'    => 0,
    'open'     => 0,
    'answered' => 0
];
$filterSubject = (int)($_GET['subject'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');

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
                SELECT DISTINCT s.id as subject_id, s.subject_name, s.subject_code 
                FROM `teacher_assignments` ta
                JOIN `subjects` s ON ta.subject_id = s.id
                WHERE ta.teacher_id = :tid
                ORDER BY s.subject_name ASC
            ");
            $stmtAssign->execute([':tid' => $teacherId]);
            $assignedCourses = $stmtAssign->fetchAll();
        }

        // Handle POST Actions (Answer & Close)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: doubts.php');
                exit;
            }

            if ($action === 'answer_doubt') {
                $doubtId = (int)($_POST['doubt_id'] ?? 0);
                $reply   = trim($_POST['response_text'] ?? '');

                if ($doubtId <= 0 || empty($reply)) {
                    setFlash('danger', 'Answer response cannot be empty.');
                } else {
                    $pdo->beginTransaction();

                    // 1. Insert response
                    $stmtResp = $pdo->prepare("
                        INSERT INTO `doubt_responses` (`doubt_id`, `user_id`, `response_text`) 
                        VALUES (:did, :uid, :reply)
                    ");
                    $stmtResp->execute([
                        ':did'   => $doubtId,
                        ':uid'   => $currentUser['id'],
                        ':reply' => $reply
                    ]);

                    // 2. Mark doubt status as answered
                    $stmtUp = $pdo->prepare("UPDATE `doubts` SET `status` = 'answered' WHERE `id` = :did");
                    $stmtUp->execute([':did' => $doubtId]);

                    $pdo->commit();
                    setFlash('success', 'Your answer has been published and the inquiry is marked as Answered.');
                }
                header("Location: doubts.php#doubt-$doubtId");
                exit;
            }

            if ($action === 'close_doubt') {
                $doubtId = (int)($_POST['doubt_id'] ?? 0);
                if ($doubtId > 0) {
                    $stmtClose = $pdo->prepare("UPDATE `doubts` SET `status` = 'closed' WHERE `id` = :did");
                    $stmtClose->execute([':did' => $doubtId]);
                    setFlash('success', 'Doubt inquiry closed.');
                }
                header('Location: doubts.php');
                exit;
            }
        }

        // Fetch doubts for this teacher's assigned subjects
        $subjectIds = array_column($assignedCourses, 'subject_id');
        if (!empty($subjectIds)) {
            $subjInClause = implode(',', array_map('intval', $subjectIds));

            $query = "
                SELECT d.*, s.subject_name, s.subject_code,
                       sp.roll_number, u.full_name as student_name, c.class_name
                FROM `doubts` d
                JOIN `subjects` s ON d.subject_id = s.id
                JOIN `student_profiles` sp ON d.student_id = sp.id
                JOIN `users` u ON sp.user_id = u.id
                JOIN `classes` c ON sp.class_id = c.id
                WHERE d.subject_id IN ($subjInClause)
            ";
            $params = [];

            if ($filterSubject > 0) {
                $query .= " AND d.subject_id = :subj";
                $params[':subj'] = $filterSubject;
            }
            if (!empty($filterStatus)) {
                $query .= " AND d.status = :status";
                $params[':status'] = $filterStatus;
            }

            $query .= " ORDER BY (d.status = 'open') DESC, d.created_at DESC";

            $stmtDoubts = $pdo->prepare($query);
            $stmtDoubts->execute($params);
            $doubtsList = $stmtDoubts->fetchAll();

            $stats['total'] = count($doubtsList);
            foreach ($doubtsList as $d) {
                if ($d['status'] === 'open') $stats['open']++;
                elseif ($d['status'] === 'answered') $stats['answered']++;
            }

            // Fetch responses for all loaded doubts
            $doubtIds = array_column($doubtsList, 'id');
            $responsesByDoubt = [];

            if (!empty($doubtIds)) {
                $inClause = implode(',', array_map('intval', $doubtIds));
                $stmtResps = $pdo->query("
                    SELECT dr.*, u.full_name, u.role
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
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Teacher Doubts Error: " . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Resolve Doubts</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Student Doubts & Inquiries Hub</h2>
            <p class="text-secondary small mb-0">Address conceptual queries, clarify lecture questions, and guide students.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
        </a>
    </div>

    <!-- Stat Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Inquiries</span>
                    <div class="stat-icon-wrapper stat-icon-blue">
                        <i class="bi bi-chat-quote-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['total'] ?></div>
                <small class="text-muted">Questions in Your Courses</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Open</span>
                    <div class="stat-icon-wrapper <?= ($stats['open'] > 0) ? 'stat-icon-rose' : 'stat-icon-green' ?>">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['open'] ?></div>
                <small class="<?= ($stats['open'] > 0) ? 'text-danger fw-semibold' : 'text-muted' ?>">
                    <?= ($stats['open'] > 0) ? 'Awaiting Your Response' : 'All Inquiries Addressed' ?>
                </small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Answered</span>
                    <div class="stat-icon-wrapper stat-icon-green">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $stats['answered'] ?></div>
                <small class="text-muted">Resolved Clarifications</small>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="doubts.php" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="subject" onchange="this.form.submit()">
                        <option value="0">All Taught Subjects</option>
                        <?php foreach ($assignedCourses as $s): ?>
                            <option value="<?= $s['subject_id'] ?>" <?= ($filterSubject === (int)$s['subject_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['subject_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="open" <?= ($filterStatus === 'open') ? 'selected' : '' ?>>Pending Open Only</option>
                        <option value="answered" <?= ($filterStatus === 'answered') ? 'selected' : '' ?>>Answered Only</option>
                    </select>
                </div>
                <?php if ($filterSubject > 0 || !empty($filterStatus)): ?>
                    <div class="col-auto">
                        <a href="doubts.php" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                    $isOpen = ($doubt['status'] === 'open');
                ?>
                <div class="card border-0 shadow-sm <?= $isOpen ? 'border-start border-4 border-warning' : '' ?>" id="doubt-<?= $doubt['id'] ?>">
                    
                    <!-- Doubt Card Header -->
                    <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-primary border font-monospace">
                                <?= htmlspecialchars($doubt['subject_code']) ?>
                            </span>
                            <span class="fw-bold text-dark small"><?= htmlspecialchars($doubt['student_name']) ?></span>
                            <span class="badge bg-light text-dark border font-monospace small"><?= htmlspecialchars($doubt['roll_number']) ?></span>
                            <span class="badge bg-light text-secondary border small"><?= htmlspecialchars($doubt['class_name']) ?></span>
                            <span class="text-muted">&bull;</span>
                            <span class="small text-muted font-monospace"><i class="bi bi-clock me-1"></i><?= date('M d, Y h:i A', strtotime($doubt['created_at'])) ?></span>
                        </div>
                        <div>
                            <?php if ($doubt['status'] === 'open'): ?>
                                <span class="badge bg-warning-subtle text-warning"><i class="bi bi-hourglass me-1"></i>Requires Answer</span>
                            <?php elseif ($doubt['status'] === 'answered'): ?>
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Answered</span>
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
                                    <i class="bi bi-chat-left-dots-fill text-primary me-2"></i>Clarification Thread (<?= count($resps) ?>)
                                </h6>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($resps as $r): ?>
                                        <div class="p-3 bg-white rounded border shadow-sm <?= ($r['role'] === 'teacher') ? 'border-primary-subtle' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold small text-dark"><?= htmlspecialchars($r['full_name']) ?></span>
                                                    <?php if ($r['role'] === 'teacher'): ?>
                                                        <span class="badge badge-role-teacher small">Instructor</span>
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

                        <!-- Instructor Answer Form -->
                        <div class="mt-3 pt-3 border-top">
                            <form method="POST" action="doubts.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="answer_doubt">
                                <input type="hidden" name="doubt_id" value="<?= $doubt['id'] ?>">

                                <label class="form-label small fw-bold text-secondary mb-2">
                                    <i class="bi bi-pencil-square text-primary me-1"></i>Post Official Answer / Clarification:
                                </label>
                                <textarea class="form-control form-control-sm mb-2" 
                                          name="response_text" 
                                          rows="3" 
                                          placeholder="Type your explanation, reference textbook section, or answer..." 
                                          required></textarea>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Posting marks this student inquiry as <strong>Answered</strong>.</small>
                                    <button type="submit" class="btn btn-primary btn-sm px-3">
                                        <i class="bi bi-send-fill me-1"></i>Publish Answer
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-chat-heart fs-1 d-block mb-3 text-secondary"></i>
                <h5>No Student Doubts Found</h5>
                <p class="small mb-0">There are currently no open queries for your assigned courses.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
