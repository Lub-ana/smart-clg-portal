<?php
/**
 * Smart College Management Portal - Student Dashboard
 * Student learning dashboard featuring personal attendance tracking with Chart.js,
 * lecture notes access, assignment status, and doubt inquiries.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce student server-side authorization
requireRole('student');

$page_title = 'Student Dashboard';
$active_nav = 'student_dashboard';
$currentUser = getCurrentUser();

$studentProfile = null;
$attendanceCounts = ['Present' => 0, 'Late' => 0, 'Absent' => 0];
$attendancePercentage = 0;
$totalSessions = 0;
$recentNotes = [];
$assignmentsList = [];

if ($pdo instanceof PDO) {
    try {
        // Fetch student profile details
        $stmtProfile = $pdo->prepare("
            SELECT sp.*, d.department_name, d.department_code, c.class_name, c.semester, c.academic_year
            FROM `student_profiles` sp
            JOIN `departments` d ON sp.department_id = d.id
            JOIN `classes` c ON sp.class_id = c.id
            WHERE sp.user_id = :uid
            LIMIT 1
        ");
        $stmtProfile->execute([':uid' => $currentUser['id']]);
        $studentProfile = $stmtProfile->fetch();

        $studentId = $studentProfile['id'] ?? 0;
        $classId   = $studentProfile['class_id'] ?? 0;

        if ($studentId) {
            // Fetch student personal attendance distribution
            $stmtAtt = $pdo->prepare("
                SELECT ar.status, COUNT(*) as count
                FROM `attendance_records` ar
                WHERE ar.student_id = :sid
                GROUP BY ar.status
            ");
            $stmtAtt->execute([':sid' => $studentId]);
            while ($row = $stmtAtt->fetch()) {
                if (isset($attendanceCounts[$row['status']])) {
                    $attendanceCounts[$row['status']] = (int)$row['count'];
                }
            }

            $totalSessions = array_sum($attendanceCounts);
            if ($totalSessions > 0) {
                // Calculate attendance percentage (Present counts as 1.0, Late counts as 0.75 or 1.0)
                $attendedWeight = $attendanceCounts['Present'] + ($attendanceCounts['Late'] * 0.75);
                $attendancePercentage = round(($attendedWeight / $totalSessions) * 100, 1);
            }

            // Fetch course notes available for this student's class
            $stmtNotes = $pdo->prepare("
                SELECT n.id, n.title, n.description, n.file_path, n.file_type, n.uploaded_at,
                       s.subject_name, s.subject_code, u.full_name as teacher_name
                FROM `notes` n
                JOIN `subjects` s ON n.subject_id = s.id
                JOIN `teacher_profiles` tp ON n.teacher_id = tp.id
                JOIN `users` u ON tp.user_id = u.id
                WHERE n.class_id = :cid
                ORDER BY n.uploaded_at DESC
                LIMIT 4
            ");
            $stmtNotes->execute([':cid' => $classId]);
            $recentNotes = $stmtNotes->fetchAll();

            // Fetch active assignments and student submission status
            $stmtAssign = $pdo->prepare("
                SELECT a.id, a.title, a.due_date, a.max_marks,
                       s.subject_name, s.subject_code,
                       sub.status as submission_status, sub.marks_obtained
                FROM `assignments` a
                JOIN `subjects` s ON a.subject_id = s.id
                LEFT JOIN `assignment_submissions` sub 
                       ON a.id = sub.assignment_id AND sub.student_id = :sid
                WHERE a.class_id = :cid
                ORDER BY a.due_date ASC
                LIMIT 4
            ");
            $stmtAssign->execute([':cid' => $classId, ':sid' => $studentId]);
            $assignmentsList = $stmtAssign->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Student Dashboard Query Error: " . $e->getMessage());
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
                <span class="badge badge-role-student">Student</span>
                <span class="text-muted small"><?= htmlspecialchars($studentProfile['class_name'] ?? 'Class') ?> &bull; <?= htmlspecialchars($studentProfile['department_name'] ?? 'Dept') ?></span>
            </div>
            <h2 class="h3 fw-bold text-dark mt-1 mb-0"><?= htmlspecialchars($currentUser['full_name']) ?></h2>
            <p class="text-secondary small mb-0">
                Roll No: <code><?= htmlspecialchars($studentProfile['roll_number'] ?? 'N/A') ?></code> &bull;
                Enrollment ID: <code><?= htmlspecialchars($studentProfile['enrollment_number'] ?? 'N/A') ?></code>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="../../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid me-1"></i>System Overview
            </a>
            <a href="../../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Sign Out
            </a>
        </div>
    </div>

    <!-- Student Attendance Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Attendance Rate</span>
                    <div class="stat-icon-wrapper <?= ($attendancePercentage >= 75) ? 'stat-icon-green' : 'stat-icon-rose' ?>">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $attendancePercentage ?>%</div>
                <small class="<?= ($attendancePercentage >= 75) ? 'text-success' : 'text-danger' ?> fw-semibold">
                    <?= ($attendancePercentage >= 75) ? 'Eligible for Exams' : 'Low Attendance Alert' ?>
                </small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Present</span>
                    <div class="stat-icon-wrapper stat-icon-green">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $attendanceCounts['Present'] ?></div>
                <small class="text-muted">Out of <?= $totalSessions ?> Lectures</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Late</span>
                    <div class="stat-icon-wrapper stat-icon-amber">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $attendanceCounts['Late'] ?></div>
                <small class="text-muted">Partial Credit Recorded</small>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Absent</span>
                    <div class="stat-icon-wrapper stat-icon-rose">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-0"><?= $attendanceCounts['Absent'] ?></div>
                <small class="text-muted">Missed Classes</small>
            </div>
        </div>
    </div>

    <!-- Attendance Chart & Class Notes -->
    <div class="row g-4 mb-4">
        
        <!-- Personal Attendance Graph with Chart.js -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-primary me-2"></i>My Attendance Breakdown</h6>
                        <small class="text-muted">Individual session analysis</small>
                    </div>
                    <span class="badge <?= ($attendancePercentage >= 75) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                        <?= $attendancePercentage ?>% Average
                    </span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 210px;">
                        <canvas id="studentAttendanceChart"></canvas>
                    </div>
                    <div class="text-center mt-3 small text-muted">
                        Target requirement: <strong>75% minimum</strong> attendance per university guidelines.
                    </div>
                </div>
            </div>
        </div>

        <!-- Class Study Materials -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark-fill text-info me-2"></i>Class Notes & Study Materials</h6>
                        <small class="text-muted">Uploaded by course teachers</small>
                    </div>
                    <span class="badge bg-light text-secondary border"><?= count($recentNotes) ?> Notes</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($recentNotes)): ?>
                            <?php foreach ($recentNotes as $note): ?>
                                <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="stat-icon-wrapper stat-icon-blue" style="width: 40px; height: 40px;">
                                            <i class="bi bi-file-earmark-pdf-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold small text-dark"><?= htmlspecialchars($note['title']) ?></div>
                                            <div class="text-muted" style="font-size: 0.78rem;">
                                                <span class="badge bg-light text-primary border"><?= htmlspecialchars($note['subject_code']) ?></span>
                                                &bull; <?= htmlspecialchars($note['teacher_name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-secondary border small">
                                        <i class="bi bi-download me-1"></i><?= strtoupper($note['file_type']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted small">No class notes uploaded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Assignments & Submissions List -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Coursework Assignments</h6>
                <small class="text-muted">Pending and graded coursework for your section</small>
            </div>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($assignmentsList) ?> Assignments</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Assignment Title</th>
                            <th>Deadline</th>
                            <th>Max Marks</th>
                            <th>My Status</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assignmentsList)): ?>
                            <?php foreach ($assignmentsList as $asg): ?>
                                <tr>
                                    <td><span class="badge bg-light text-primary border"><?= htmlspecialchars($asg['subject_code']) ?></span></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($asg['title']) ?></td>
                                    <td class="text-muted small"><?= date('M d, Y h:i A', strtotime($asg['due_date'])) ?></td>
                                    <td><?= (int)$asg['max_marks'] ?> pts</td>
                                    <td>
                                        <?php if ($asg['submission_status'] === 'graded'): ?>
                                            <span class="badge bg-success-subtle text-success">Graded</span>
                                        <?php elseif ($asg['submission_status'] === 'submitted'): ?>
                                            <span class="badge bg-info-subtle text-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($asg['marks_obtained'] !== null): ?>
                                            <strong class="text-success"><?= $asg['marks_obtained'] ?> / <?= $asg['max_marks'] ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No assignments posted for your class yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Chart.js Personal Attendance Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('studentAttendanceChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Late', 'Absent'],
                datasets: [{
                    data: [
                        <?= $attendanceCounts['Present'] ?>,
                        <?= $attendanceCounts['Late'] ?>,
                        <?= $attendanceCounts['Absent'] ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 12
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
