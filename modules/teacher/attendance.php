<?php
/**
 * Smart College Management Portal - Teacher Attendance Module
 * Enables faculty to conduct lecture sessions, mark student attendance records,
 * and review session analytics and turnouts.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce teacher server-side authorization
requireRole('teacher');

$page_title = 'Take & Manage Attendance';
$active_nav = 'attendance';
$currentUser = getCurrentUser();

$teacherProfile = null;
$assignedCourses = [];
$studentsInClass = [];
$selectedCourse = null;
$recentSessions = [];

if ($pdo instanceof PDO) {
    try {
        // 1. Fetch Teacher Profile
        $stmtProf = $pdo->prepare("
            SELECT tp.*, d.department_name, d.department_code 
            FROM `teacher_profiles` tp
            LEFT JOIN `departments` d ON tp.department_id = d.id
            WHERE tp.user_id = :uid
            LIMIT 1
        ");
        $stmtProf->execute([':uid' => $currentUser['id']]);
        $teacherProfile = $stmtProf->fetch();
        $teacherId = $teacherProfile['id'] ?? 0;

        // 2. Fetch Assigned Courses (Subject + Class)
        if ($teacherId > 0) {
            $stmtAssign = $pdo->prepare("
                SELECT ta.id as assignment_id, ta.class_id, ta.subject_id, ta.academic_year,
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

        // 3. Handle Selected Course for Attendance Sheet
        $selectedAssignmentId = (int)($_GET['course'] ?? 0);
        if ($selectedAssignmentId > 0) {
            foreach ($assignedCourses as $course) {
                if ((int)$course['assignment_id'] === $selectedAssignmentId) {
                    $selectedCourse = $course;
                    break;
                }
            }
        } elseif (!empty($assignedCourses)) {
            // Default to first assigned course if none selected in query
            $selectedCourse = $assignedCourses[0];
            $selectedAssignmentId = (int)$selectedCourse['assignment_id'];
        }

        // 4. Fetch Students Enrolled in Selected Class
        if ($selectedCourse) {
            $stmtStudents = $pdo->prepare("
                SELECT sp.id as student_id, sp.roll_number, sp.enrollment_number, sp.gender,
                       u.full_name, u.email
                FROM `student_profiles` sp
                JOIN `users` u ON sp.user_id = u.id
                WHERE sp.class_id = :cid AND u.status = 'active'
                ORDER BY sp.roll_number ASC
            ");
            $stmtStudents->execute([':cid' => $selectedCourse['class_id']]);
            $studentsInClass = $stmtStudents->fetchAll();
        }

        // 5. Handle POST Submission for Saving Attendance
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: attendance.php');
                exit;
            }

            if ($action === 'save_attendance') {
                $classId     = (int)($_POST['class_id'] ?? 0);
                $subjectId   = (int)($_POST['subject_id'] ?? 0);
                $sessionDate = $_POST['session_date'] ?? date('Y-m-d');
                $sessionTime = $_POST['session_time'] ?? date('H:i:s');
                $topic       = trim($_POST['topic'] ?? 'Regular Lecture Session');
                $attendance  = $_POST['status'] ?? []; // student_id => 'Present'|'Absent'|'Late'|'Excused'
                $remarks     = $_POST['remarks'] ?? []; // student_id => text

                if ($classId <= 0 || $subjectId <= 0 || empty($sessionDate) || empty($attendance)) {
                    setFlash('danger', 'Please provide lecture topic, date, and mark attendance for at least one student.');
                } else {
                    $pdo->beginTransaction();

                    // 1. Insert session record
                    $stmtSess = $pdo->prepare("
                        INSERT INTO `attendance_sessions` (`class_id`, `subject_id`, `teacher_id`, `session_date`, `session_time`, `topic`) 
                        VALUES (:cid, :sid, :tid, :sdate, :stime, :topic)
                    ");
                    $stmtSess->execute([
                        ':cid'   => $classId,
                        ':sid'   => $subjectId,
                        ':tid'   => $teacherId,
                        ':sdate' => $sessionDate,
                        ':stime' => $sessionTime,
                        ':topic' => $topic
                    ]);
                    $sessionId = (int)$pdo->lastInsertId();

                    // 2. Insert records for all students
                    $stmtRecord = $pdo->prepare("
                        INSERT INTO `attendance_records` (`session_id`, `student_id`, `status`, `remarks`) 
                        VALUES (:sess_id, :stud_id, :status, :rem)
                    ");

                    $presentCount = 0;
                    $absentCount  = 0;
                    $lateCount    = 0;
                    $excusedCount = 0;

                    foreach ($attendance as $studentId => $status) {
                        $statusVal = in_array($status, ['Present', 'Absent', 'Late', 'Excused'], true) ? $status : 'Present';
                        $remVal    = trim($remarks[$studentId] ?? '');

                        if ($statusVal === 'Present') $presentCount++;
                        elseif ($statusVal === 'Absent') $absentCount++;
                        elseif ($statusVal === 'Late') $lateCount++;
                        elseif ($statusVal === 'Excused') $excusedCount++;

                        $stmtRecord->execute([
                            ':sess_id' => $sessionId,
                            ':stud_id' => (int)$studentId,
                            ':status'  => $statusVal,
                            ':rem'     => $remVal ?: null
                        ]);
                    }

                    $pdo->commit();
                    $totalMarked = count($attendance);
                    $turnoutPct  = ($totalMarked > 0) ? round(($presentCount / $totalMarked) * 100) : 0;

                    setFlash('success', "Attendance saved for $totalMarked students ($presentCount Present, $absentCount Absent, $lateCount Late) &bull; Turnout: $turnoutPct%");
                    header("Location: attendance.php?course=" . (int)($_POST['assignment_id'] ?? 0));
                    exit;
                }
            }

            if ($action === 'delete_session') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                if ($sessionId > 0) {
                    $stmtDel = $pdo->prepare("DELETE FROM `attendance_sessions` WHERE `id` = :sid AND `teacher_id` = :tid");
                    $stmtDel->execute([':sid' => $sessionId, ':tid' => $teacherId]);
                    setFlash('success', 'Attendance session and associated student records were deleted successfully.');
                    header('Location: attendance.php');
                    exit;
                }
            }
        }

        // 6. Fetch Recent Sessions History for this Teacher
        if ($teacherId > 0) {
            $stmtHist = $pdo->prepare("
                SELECT att.id, att.session_date, att.session_time, att.topic, att.created_at,
                       c.class_name, s.subject_name, s.subject_code,
                       (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.session_id = att.id AND ar.status = 'Present') as present_count,
                       (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.session_id = att.id AND ar.status = 'Absent') as absent_count,
                       (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.session_id = att.id AND ar.status = 'Late') as late_count,
                       (SELECT COUNT(*) FROM `attendance_records` ar WHERE ar.session_id = att.id) as total_students
                FROM `attendance_sessions` att
                JOIN `classes` c ON att.class_id = c.id
                JOIN `subjects` s ON att.subject_id = s.id
                WHERE att.teacher_id = :tid
                ORDER BY att.session_date DESC, att.session_time DESC
                LIMIT 15
            ");
            $stmtHist->execute([':tid' => $teacherId]);
            $recentSessions = $stmtHist->fetchAll();
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Teacher Attendance Error: " . $e->getMessage());
    }
}

// Prepare chart labels and data from recent sessions
$chartLabels  = [];
$chartTurnout = [];
if (!empty($recentSessions)) {
    // Reverse order for chronological left-to-right display
    $reversed = array_reverse(array_slice($recentSessions, 0, 7));
    foreach ($reversed as $sess) {
        $chartLabels[]  = date('M d', strtotime($sess['session_date'])) . ' (' . $sess['subject_code'] . ')';
        $tot            = (int)$sess['total_students'];
        $pres           = (int)$sess['present_count'];
        $chartTurnout[] = ($tot > 0) ? round(($pres / $tot) * 100) : 0;
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
                    <li class="breadcrumb-item active" aria-current="page">Attendance Register</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">Student Attendance Module</h2>
            <p class="text-secondary small mb-0">
                <?= htmlspecialchars($currentUser['full_name']) ?> &bull; 
                <?= htmlspecialchars($teacherProfile['designation'] ?? 'Instructor') ?> &bull; 
                Emp ID: <code><?= htmlspecialchars($teacherProfile['employee_id'] ?? 'N/A') ?></code>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="#history-section" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock-history me-1"></i>Attendance History
            </a>
            <a href="dashboard.php" class="btn btn-primary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Teacher Dashboard
            </a>
        </div>
    </div>

    <!-- Course Selector Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="attendance.php" class="row g-3 align-items-center">
                <div class="col-md-auto">
                    <label for="courseSelect" class="col-form-label small fw-bold text-secondary">
                        <i class="bi bi-book-half text-primary me-1"></i>Select Teaching Allocation:
                    </label>
                </div>
                <div class="col-md-6">
                    <select class="form-select form-select-sm" id="courseSelect" name="course" onchange="this.form.submit()">
                        <?php if (!empty($assignedCourses)): ?>
                            <?php foreach ($assignedCourses as $c): ?>
                                <option value="<?= $c['assignment_id'] ?>" <?= ($selectedCourse && (int)$selectedCourse['assignment_id'] === (int)$c['assignment_id']) ? 'selected' : '' ?>>
                                    [<?= htmlspecialchars($c['class_name']) ?>] <?= htmlspecialchars($c['subject_name']) ?> (<?= htmlspecialchars($c['subject_code']) ?>) &bull; Sem <?= (int)$c['semester'] ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No courses assigned to your account</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-auto ms-auto text-muted small">
                    <?php if ($selectedCourse): ?>
                        <span class="badge bg-light text-primary border px-2 py-1">
                            <i class="bi bi-people me-1"></i><?= count($studentsInClass) ?> Enrolled Students
                        </span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedCourse): ?>
        <!-- Attendance Taking Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-calendar2-check-fill text-success me-2"></i>
                        New Session Register: <?= htmlspecialchars($selectedCourse['subject_name']) ?> (<?= htmlspecialchars($selectedCourse['subject_code']) ?>)
                    </h6>
                    <small class="text-muted">Batch: <strong><?= htmlspecialchars($selectedCourse['class_name']) ?></strong> &bull; Academic Year: <?= htmlspecialchars($selectedCourse['academic_year']) ?></small>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" id="btnMarkAllPresent">
                        <i class="bi bi-check-all me-1"></i>Mark All Present
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnMarkAllAbsent">
                        <i class="bi bi-x-circle me-1"></i>Mark All Absent
                    </button>
                </div>
            </div>

            <form method="POST" action="attendance.php" id="attendanceForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="assignment_id" value="<?= (int)$selectedCourse['assignment_id'] ?>">
                <input type="hidden" name="class_id" value="<?= (int)$selectedCourse['class_id'] ?>">
                <input type="hidden" name="subject_id" value="<?= (int)$selectedCourse['subject_id'] ?>">

                <div class="card-body p-4 bg-light border-bottom">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="session_date" class="form-label small fw-semibold text-secondary">Session Date</label>
                            <input type="date" class="form-control form-control-sm" id="session_date" name="session_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="session_time" class="form-label small fw-semibold text-secondary">Lecture Time</label>
                            <input type="time" class="form-control form-control-sm" id="session_time" name="session_time" value="<?= date('H:i') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="topic" class="form-label small fw-semibold text-secondary">Lecture Topic / Syllabus Unit</label>
                            <input type="text" class="form-control form-control-sm" id="topic" name="topic" placeholder="e.g. Relational Calculus & Tuple Queries" required>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-portal mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Roll Number</th>
                                    <th>Student Full Name</th>
                                    <th>Enrollment ID</th>
                                    <th class="text-center" style="width: 320px;">Attendance Status</th>
                                    <th>Notes / Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($studentsInClass)): ?>
                                    <?php foreach ($studentsInClass as $idx => $s): ?>
                                        <tr>
                                            <td><small class="text-muted"><?= $idx + 1 ?></small></td>
                                            <td>
                                                <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                                    <?= htmlspecialchars($s['roll_number']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($s['full_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($s['email']) ?></small>
                                            </td>
                                            <td><code class="small"><?= htmlspecialchars($s['enrollment_number']) ?></code></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Status">
                                                    
                                                    <!-- Present -->
                                                    <input type="radio" 
                                                           class="btn-check att-status-radio" 
                                                           name="status[<?= $s['student_id'] ?>]" 
                                                           id="pres_<?= $s['student_id'] ?>" 
                                                           value="Present" 
                                                           checked>
                                                    <label class="btn btn-outline-success" for="pres_<?= $s['student_id'] ?>">
                                                        <i class="bi bi-check-lg me-1"></i>Present
                                                    </label>

                                                    <!-- Late -->
                                                    <input type="radio" 
                                                           class="btn-check att-status-radio" 
                                                           name="status[<?= $s['student_id'] ?>]" 
                                                           id="late_<?= $s['student_id'] ?>" 
                                                           value="Late">
                                                    <label class="btn btn-outline-warning text-dark" for="late_<?= $s['student_id'] ?>">
                                                        Late
                                                    </label>

                                                    <!-- Absent -->
                                                    <input type="radio" 
                                                           class="btn-check att-status-radio" 
                                                           name="status[<?= $s['student_id'] ?>]" 
                                                           id="abs_<?= $s['student_id'] ?>" 
                                                           value="Absent">
                                                    <label class="btn btn-outline-danger" for="abs_<?= $s['student_id'] ?>">
                                                        Absent
                                                    </label>

                                                    <!-- Excused -->
                                                    <input type="radio" 
                                                           class="btn-check att-status-radio" 
                                                           name="status[<?= $s['student_id'] ?>]" 
                                                           id="exc_<?= $s['student_id'] ?>" 
                                                           value="Excused">
                                                    <label class="btn btn-outline-secondary" for="exc_<?= $s['student_id'] ?>">
                                                        Leave
                                                    </label>

                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       class="form-control form-control-sm" 
                                                       name="remarks[<?= $s['student_id'] ?>]" 
                                                       placeholder="Optional note...">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-people fs-3 d-block mb-2"></i>
                                            No students found enrolled in <strong><?= htmlspecialchars($selectedCourse['class_name']) ?></strong>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($studentsInClass)): ?>
                    <div class="card-footer bg-white p-3 border-top d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Saving creates an immutable attendance log with timestamp.
                        </div>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-semibold shadow-sm">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i>Submit Attendance Register
                        </button>
                    </div>
                <?php endif; ?>

            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
            <div>You have not been assigned to any subjects or classes yet. Please contact the College Administrator.</div>
        </div>
    <?php endif; ?>

    <!-- Attendance Trends Chart & Recent History Section -->
    <div class="row g-4 mb-4" id="history-section">
        
        <!-- Attendance Turnout Chart -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Lecture Attendance Turnout %</h6>
                        <small class="text-muted">Turnout percentages of recent sessions</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Chart.js</span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 230px;">
                        <canvas id="teacherAttendanceBarChart"></canvas>
                    </div>
                    <div class="text-center mt-3 text-muted small">
                        Class average: <strong>75% target threshold</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Sessions Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-success me-2"></i>Logged Sessions History</h6>
                        <small class="text-muted">Recorded lectures by you</small>
                    </div>
                    <span class="badge bg-light text-secondary border"><?= count($recentSessions) ?> Logged</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-portal mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject & Batch</th>
                                    <th>Lecture Topic</th>
                                    <th>Turnout</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentSessions)): ?>
                                    <?php foreach ($recentSessions as $sess): ?>
                                        <?php 
                                            $tot  = (int)$sess['total_students'];
                                            $pres = (int)$sess['present_count'];
                                            $pct  = ($tot > 0) ? round(($pres / $tot) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= date('M d, Y', strtotime($sess['session_date'])) ?></div>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($sess['session_time'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold small"><?= htmlspecialchars($sess['subject_code']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($sess['class_name']) ?></div>
                                            </td>
                                            <td class="small text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($sess['topic']) ?>">
                                                <?= htmlspecialchars($sess['topic'] ?: 'Lecture Session') ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= ($pct >= 75) ? 'badge-present' : 'badge-absent' ?>">
                                                    <?= $pres ?> / <?= $tot ?> (<?= $pct ?>%)
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" action="attendance.php" onsubmit="return confirm('Are you sure you want to delete this session? Student attendance logs will be removed.');" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_session">
                                                    <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Session">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No attendance sessions logged yet.
                                        </td>
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

<!-- UI Scripts & Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Quick Mark All Present
    const btnPresent = document.getElementById('btnMarkAllPresent');
    if (btnPresent) {
        btnPresent.addEventListener('click', function () {
            document.querySelectorAll('input.att-status-radio[value="Present"]').forEach(radio => {
                radio.checked = true;
            });
        });
    }

    // Quick Mark All Absent
    const btnAbsent = document.getElementById('btnMarkAllAbsent');
    if (btnAbsent) {
        btnAbsent.addEventListener('click', function () {
            document.querySelectorAll('input.att-status-radio[value="Absent"]').forEach(radio => {
                radio.checked = true;
            });
        });
    }

    // Render Attendance Turnout Bar Chart with Chart.js
    const ctx = document.getElementById('teacherAttendanceBarChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Attendance Turnout %',
                    data: <?= json_encode($chartTurnout) ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.85)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(val) { return val + '%'; },
                            font: { size: 11 }
                        }
                    },
                    x: {
                        ticks: { font: { size: 11 } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ' Turnout: ' + ctx.raw + '%'; }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
