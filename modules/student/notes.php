<?php
/**
 * Smart College Management Portal - Student Notes Download Center
 * Allows students to browse, search, and download course notes published for their section.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce student server-side authorization
requireRole('student');

$page_title = 'Class Notes & Study Materials';
$active_nav = 'notes';
$currentUser = getCurrentUser();

$studentProfile = null;
$notesList      = [];
$subjectsList   = [];
$filterSubject  = (int)($_GET['subject'] ?? 0);
$searchTerm     = trim($_GET['search'] ?? '');

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
        $classId = $studentProfile['class_id'] ?? 0;

        if ($classId > 0) {
            // Fetch distinct subjects with notes in this class for the filter dropdown
            $stmtSubjs = $pdo->prepare("
                SELECT DISTINCT s.id, s.subject_name, s.subject_code 
                FROM `notes` n 
                JOIN `subjects` s ON n.subject_id = s.id 
                WHERE n.class_id = :cid
                ORDER BY s.subject_name ASC
            ");
            $stmtSubjs->execute([':cid' => $classId]);
            $subjectsList = $stmtSubjs->fetchAll();

            // Build query for class notes with optional search & subject filter
            $query = "
                SELECT n.*, s.subject_name, s.subject_code,
                       u.full_name as teacher_name, tp.designation
                FROM `notes` n
                JOIN `subjects` s ON n.subject_id = s.id
                JOIN `teacher_profiles` tp ON n.teacher_id = tp.id
                JOIN `users` u ON tp.user_id = u.id
                WHERE n.class_id = :cid
            ";
            $params = [':cid' => $classId];

            if ($filterSubject > 0) {
                $query .= " AND n.subject_id = :sid";
                $params[':sid'] = $filterSubject;
            }

            if (!empty($searchTerm)) {
                $query .= " AND (n.title LIKE :search1 OR n.description LIKE :search2)";
                $params[':search1'] = '%' . $searchTerm . '%';
                $params[':search2'] = '%' . $searchTerm . '%';
            }

            $query .= " ORDER BY n.uploaded_at DESC";

            $stmtNotes = $pdo->prepare($query);
            $stmtNotes->execute($params);
            $notesList = $stmtNotes->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Student Notes Query Error: " . $e->getMessage());
    }
}

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
                    <li class="breadcrumb-item active" aria-current="page">Study Notes</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Class Notes & Study Materials</h2>
            <p class="text-secondary small mb-0">
                Official lecture slides, PDF documents, and syllabus materials for 
                <strong><?= htmlspecialchars($studentProfile['class_name'] ?? 'Class') ?></strong>.
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
        </a>
    </div>

    <!-- Filter & Search Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="notes.php" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search by title or topic..." value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="subject" onchange="this.form.submit()">
                        <option value="0">All Subjects in Section</option>
                        <?php foreach ($subjectsList as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($filterSubject === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['subject_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">Filter</button>
                    <?php if ($filterSubject > 0 || !empty($searchTerm)): ?>
                        <a href="notes.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Notes List Cards Grid -->
    <div class="row g-4 mb-4">
        <?php if (!empty($notesList)): ?>
            <?php foreach ($notesList as $note): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="stat-icon-wrapper stat-icon-blue">
                                    <i class="bi bi-file-earmark-<?= ($note['file_type'] === 'pdf') ? 'pdf' : 'text' ?>-fill"></i>
                                </div>
                                <span class="badge bg-light text-primary border font-monospace">
                                    <?= htmlspecialchars($note['subject_code']) ?>
                                </span>
                            </div>

                            <h5 class="fw-bold text-dark mb-1 fs-6"><?= htmlspecialchars($note['title']) ?></h5>
                            <div class="small text-muted mb-2"><?= htmlspecialchars($note['subject_name']) ?></div>
                            <p class="text-secondary small mb-3 flex-grow-1" style="min-height: 48px;">
                                <?= htmlspecialchars($note['description'] ?: 'Official course materials uploaded by instructor.') ?>
                            </p>

                            <div class="pt-3 border-top mt-auto">
                                <div class="d-flex align-items-center justify-content-between mb-3 small text-muted">
                                    <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($note['teacher_name']) ?></span>
                                    <span><i class="bi bi-calendar me-1"></i><?= date('M d, Y', strtotime($note['uploaded_at'])) ?></span>
                                </div>

                                <a href="../../<?= htmlspecialchars($note['file_path']) ?>" 
                                   class="btn btn-primary btn-sm w-100 shadow-sm" 
                                   target="_blank" 
                                   download>
                                    <i class="bi bi-download me-1"></i>Download Material (<?= strtoupper($note['file_type']) ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm p-5 text-center text-muted">
                    <i class="bi bi-journal-x fs-1 d-block mb-3 text-secondary"></i>
                    <h5>No Study Notes Found</h5>
                    <p class="small mb-0">No lecture notes match your criteria. When teachers publish PDFs, they will appear here.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
