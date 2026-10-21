<?php
/**
 * Smart College Management Portal - Teacher Dashboard
 * Faculty control center for taking attendance, uploading course notes,
 * creating assignments, and addressing student doubts.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce teacher server-side authorization
requireRole('teacher');

$page_title = 'Teacher Dashboard';
$active_nav = 'teacher_dashboard';
$currentUser = getCurrentUser();

$teacherProfile = null;
$assignedCourses = [];
$recentSessions = [];
$stats = [
    'classes'     => 0,
    'subjects'    => 0,
    'sessions'    => 0,
    'notes'       => 0,
    'assignments' => 0,
    'open_doubts' => 0,
];

if ($pdo instanceof PDO) {
    try {
        // Fetch teacher profile info
        $stmtProfile = $pdo->prepare("
            SELECT tp.*, d.department_name, d.department_code 
            FROM `teacher_profiles` tp
            LEFT JOIN `departments` d ON tp.department_id = d.id
            WHERE tp.user_id = :uid
            LIMIT 1
        ");
        $stmtProfile->execute([':uid' => $currentUser['id']]);
        $teacherProfile = $stmtProfile->fetch();

        $teacherId = $teacherProfile['id'] ?? 0;

        if ($teacherId) {
            // Fetch assigned subjects & classes
            $stmtAssign = $pdo->prepare("
                SELECT ta.id, ta.academic_year, s.subject_name, s.subject_code, c.class_name, c.semester
                FROM `teacher_assignments` ta
                JOIN `subjects` s ON ta.subject_id = s.id
                JOIN `classes` c ON ta.class_id = c.id
                WHERE ta.teacher_id = :tid
                ORDER BY c.class_name ASC, s.subject_name ASC
            ");
            $stmtAssign->execute([':tid' => $teacherId]);
            $assignedCourses = $stmtAssign->fetchAll();

            $stats['classes'] = count(array_unique(array_column($assignedCourses, 'class_name')));
            $stats['subjects'] = count(array_unique(array_column($assignedCourses, 'subject_name')));

            // Count sessions conducted
            $stmtSessCount = $pdo->prepare("SELECT COUNT(*) FROM `attendance_sessions` WHERE teacher_id = :tid");
            $stmtSessCount->execute([':tid' => $teacherId]);
            $stats['sessions'] = (int)$stmtSessCount->fetchColumn();

            // Count notes uploaded
            $stmtNotesCount = $pdo->prepare("SELECT COUNT(*) FROM `notes` WHERE teacher_id = :tid");
            $stmtNotesCount->execute([':tid' => $teacherId]);
            $stats['notes'] = (int)$stmtNotesCount->fetchColumn();

            // Count assignments created
            $stmtAssignCount = $pdo->prepare("SELECT COUNT(*) FROM `assignments` WHERE teacher_id = :tid");
            $stmtAssignCount->execute([':tid' => $teacherId]);
            $stats['assignments'] = (int)$stmtAssignCount->fetchColumn();

            // Count open doubts for teacher's subjects
            $stmtDoubts = $pdo->prepare("
                SELECT COUNT(*) 
                FROM `doubts` d 
                WHERE d.subject_id IN (SELECT subject_id FROM `teacher_assignments` WHERE teacher_id = :tid)
                  AND d.status = 'open'
            ");
            $stmtDoubts->execute([':tid' => $teacherId]);
            $stats['open_doubts'] = (int)$stmtDoubts->fetchColumn();

            // Fetch recent attendance sessions
            $stmtRecent = $pdo->prepare("
                SELECT att.id, att.session_date, att.session_time, att.topic,
                       c.class_name, s.subject_name, s.subject_code,
                       (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.session_id = att.id AND ar.status = 'Present') as present_count,
                       (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.session_id = att.id) as total_records
                FROM `attendance_sessions` att
                JOIN `classes` c ON att.class_id = c.id
                JOIN `subjects` s ON att.subject_id = s.id
                WHERE att.teacher_id = :tid
                ORDER BY att.session_date DESC, att.session_time DESC
                LIMIT 5
            ");
            $stmtRecent->execute([':tid' => $teacherId]);
            $recentSessions = $stmtRecent->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Teacher Dashboard Query Error: " . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Main Content Area -->
<main class="portal-main-content">

    <!-- Flash Messages -->
    <?= renderFlashes() ?>

    <!-- Header Greeting -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-role-teacher">Faculty Member</span>
                <span class="text-muted small"><?= htmlspecialchars($teacherProfile['designation'] ?? 'Instructor') ?> &bull; <?= htmlspecialchars($teacherProfile['department_name'] ?? 'Academic Dept') ?></span>
            </div>
            <h2 class="h3 fw-bold text-dark mt-1 mb-0"><?= htmlspecialchars($currentUser['full_name']) ?></h2>
            <p class="text-secondary small mb-0">Employee ID: <code><?= htmlspecialchars($teacherProfile['employee_id'] ?? 'N/A') ?></code> &bull; Academic Year: 2025-2026</p>
        </div>
        <div class="d-flex gap-2">
            <a href="attendance.php" class="btn btn-success btn-sm shadow-sm">
                <i class="bi bi-calendar2-check-fill me-1"></i>Take Attendance
            </a>
            <a href="../../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid me-1"></i>System Overview
            </a>
            <a href="../../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Sign Out
            </a>
        </div>
    </div>

    <!-- Teacher Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Assigned</span>
                    <div class="stat-icon-wrapper stat-icon-blue">
                        <i class="bi bi-book-half"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['subjects'] ?> Subjects</div>
                <small class="text-muted"><?= $stats['classes'] ?> Class Sections</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Attendance</span>
                    <div class="stat-icon-wrapper stat-icon-green">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['sessions'] ?> Sessions</div>
                <small class="text-muted">Lectures Logged</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Study Notes</span>
                    <div class="stat-icon-wrapper stat-icon-cyan">
                        <i class="bi bi-journal-arrow-up"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['notes'] ?> Files</div>
                <small class="text-muted">Uploaded PDFs / Slides</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Student Doubts</span>
                    <div class="stat-icon-wrapper stat-icon-amber">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                </div>
                <div class="h4 fw-bold mb-0"><?= $stats['open_doubts'] ?> Open</div>
                <small class="text-muted">Awaiting Your Reply</small>
            </div>
        </div>
    </div>

    <!-- Assigned Courses & Recent Sessions -->
    <div class="row g-4 mb-4">
        
        <!-- Assigned Courses Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill text-primary me-2"></i>My Teaching Allocations</h6>
                        <small class="text-muted">Assigned subjects & batches</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary"><?= count($assignedCourses) ?> Courses</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($assignedCourses)): ?>
                            <?php foreach ($assignedCourses as $course): ?>
                                <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($course['subject_name']) ?></span>
                                            <span class="badge bg-light text-primary border"><?= htmlspecialchars($course['subject_code']) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 small text-muted">
                                            <span><i class="bi bi-people me-1"></i>Class: <strong><?= htmlspecialchars($course['class_name']) ?></strong></span>
                                            <span>&bull;</span>
                                            <span>Semester <?= (int)$course['semester'] ?></span>
                                        </div>
                                    </div>
                                    <a href="attendance.php?course=<?= (int)$course['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check2-square me-1"></i>Mark Attendance
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted small">No courses allocated yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance Sessions Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-success me-2"></i>Recent Attendance Sessions</h6>
                        <small class="text-muted">Attendance registers logged by you</small>
                    </div>
                    <span class="badge bg-success-subtle text-success">Live Register</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-portal mb-0">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Subject & Class</th>
                                    <th>Topic Covered</th>
                                    <th>Turnout</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentSessions)): ?>
                                    <?php foreach ($recentSessions as $sess): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= date('M d, Y', strtotime($sess['session_date'])) ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($sess['session_time'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold"><?= htmlspecialchars($sess['subject_code']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($sess['class_name']) ?></div>
                                            </td>
                                            <td class="small text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($sess['topic']) ?>">
                                                <?= htmlspecialchars($sess['topic'] ?: 'Standard Lecture Session') ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-status badge-present">
                                                    <?= (int)$sess['present_count'] ?> / <?= (int)$sess['total_records'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No attendance sessions recorded yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
