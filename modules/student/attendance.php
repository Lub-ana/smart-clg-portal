<?php
/**
 * Smart College Management Portal - Student Attendance Detailed Report
 * Displays personal attendance percentage, subject-wise analytics,
 * session-by-session history logs, and exam eligibility status.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce student server-side authorization
requireRole('student');

$page_title = 'My Attendance Analytics';
$active_nav = 'attendance';
$currentUser = getCurrentUser();

$studentProfile   = null;
$overallStats     = ['Present' => 0, 'Late' => 0, 'Absent' => 0, 'Excused' => 0];
$subjectWiseStats = [];
$sessionLogs      = [];
$overallPercentage = 0;

if ($pdo instanceof PDO) {
    try {
        // Fetch student profile details
        $stmtProf = $pdo->prepare("
            SELECT sp.*, d.department_name, d.department_code, c.class_name, c.semester, c.academic_year
            FROM `student_profiles` sp
            JOIN `departments` d ON sp.department_id = d.id
            JOIN `classes` c ON sp.class_id = c.id
            WHERE sp.user_id = :uid
            LIMIT 1
        ");
        $stmtProf->execute([':uid' => $currentUser['id']]);
        $studentProfile = $stmtProf->fetch();
        $studentId = $studentProfile['id'] ?? 0;
        $classId   = $studentProfile['class_id'] ?? 0;

        if ($studentId > 0) {
            // 1. Overall stats
            $stmtOverall = $pdo->prepare("
                SELECT ar.status, COUNT(*) as count
                FROM `attendance_records` ar
                WHERE ar.student_id = :sid
                GROUP BY ar.status
            ");
            $stmtOverall->execute([':sid' => $studentId]);
            while ($row = $stmtOverall->fetch()) {
                if (isset($overallStats[$row['status']])) {
                    $overallStats[$row['status']] = (int)$row['count'];
                }
            }

            $totalAttended = $overallStats['Present'] + ($overallStats['Late'] * 0.75);
            $totalConducted = array_sum($overallStats);
            if ($totalConducted > 0) {
                $overallPercentage = round(($totalAttended / $totalConducted) * 100, 1);
            }

            // 2. Subject-wise breakdown
            $stmtSubj = $pdo->prepare("
                SELECT s.id as subject_id, s.subject_name, s.subject_code,
                       COUNT(ar.id) as total_sessions,
                       SUM(CASE WHEN ar.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                       SUM(CASE WHEN ar.status = 'Late' THEN 1 ELSE 0 END) as late_count,
                       SUM(CASE WHEN ar.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                FROM `subjects` s
                JOIN `attendance_sessions` att ON s.id = att.subject_id AND att.class_id = :cid
                JOIN `attendance_records` ar ON att.id = ar.session_id AND ar.student_id = :sid
                GROUP BY s.id, s.subject_name, s.subject_code
                ORDER BY s.subject_name ASC
            ");
            $stmtSubj->execute([':cid' => $classId, ':sid' => $studentId]);
            $subjectWiseStats = $stmtSubj->fetchAll();

            // 3. Complete Session History Logs
            $stmtLogs = $pdo->prepare("
                SELECT att.session_date, att.session_time, att.topic,
                       s.subject_name, s.subject_code,
                       u.full_name as teacher_name,
                       ar.status, ar.remarks
                FROM `attendance_records` ar
                JOIN `attendance_sessions` att ON ar.session_id = att.id
                JOIN `subjects` s ON att.subject_id = s.id
                JOIN `teacher_profiles` tp ON att.teacher_id = tp.id
                JOIN `users` u ON tp.user_id = u.id
                WHERE ar.student_id = :sid
                ORDER BY att.session_date DESC, att.session_time DESC
            ");
            $stmtLogs->execute([':sid' => $studentId]);
            $sessionLogs = $stmtLogs->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Student Attendance Page Error: " . $e->getMessage());
    }
}

// Chart.js data for subject breakdown
$subjLabels = [];
$subjScores = [];
foreach ($subjectWiseStats as $sw) {
    $tot = (int)$sw['total_sessions'];
    $prs = (int)$sw['present_count'] + ((int)$sw['late_count'] * 0.75);
    $pct = ($tot > 0) ? round(($prs / $tot) * 100, 1) : 0;
    $subjLabels[] = $sw['subject_code'];
    $subjScores[] = $pct;
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
                    <li class="breadcrumb-item active" aria-current="page">My Attendance</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">My Attendance Analytics</h2>
            <p class="text-secondary small mb-0">
                <?= htmlspecialchars($currentUser['full_name']) ?> &bull; 
                Roll: <code><?= htmlspecialchars($studentProfile['roll_number'] ?? 'N/A') ?></code> &bull; 
                Class: <strong><?= htmlspecialchars($studentProfile['class_name'] ?? 'Class') ?></strong>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Attendance Percentage Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-stat">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Cumulative %</span>
                    <div class="stat-icon-wrapper <?= ($overallPercentage >= 75) ? 'stat-icon-green' : 'stat-icon-rose' ?>">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
                <div class="h2 fw-bold mb-0"><?= $overallPercentage ?>%</div>
                <small class="<?= ($overallPercentage >= 75) ? 'text-success' : 'text-danger' ?> fw-semibold">
                    <?= ($overallPercentage >= 75) ? '✓ Exam Eligible (>=75%)' : '⚠ Attendance Shortage Alert' ?>
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
                <div class="h2 fw-bold mb-0"><?= $overallStats['Present'] ?></div>
                <small class="text-muted">On-time Lectures</small>
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
                <div class="h2 fw-bold mb-0"><?= $overallStats['Late'] ?></div>
                <small class="text-muted">Partial Credit</small>
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
                <div class="h2 fw-bold mb-0"><?= $overallStats['Absent'] ?></div>
                <small class="text-muted">Missed Classes</small>
            </div>
        </div>
    </div>

    <!-- Subject Breakdown & Visual Graph -->
    <div class="row g-4 mb-4">
        
        <!-- Subject Progress List -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-book-half text-primary me-2"></i>Subject-Wise Attendance Scorecard</h6>
                        <small class="text-muted">Course by course breakdown</small>
                    </div>
                    <span class="badge bg-light text-secondary border"><?= count($subjectWiseStats) ?> Subjects</span>
                </div>
                <div class="card-body p-3">
                    <?php if (!empty($subjectWiseStats)): ?>
                        <?php foreach ($subjectWiseStats as $sw): ?>
                            <?php 
                                $tot = (int)$sw['total_sessions'];
                                $prs = (int)$sw['present_count'] + ((int)$sw['late_count'] * 0.75);
                                $pct = ($tot > 0) ? round(($prs / $tot) * 100, 1) : 0;
                            ?>
                            <div class="mb-3 p-3 bg-light rounded border">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold text-dark">
                                        <?= htmlspecialchars($sw['subject_name']) ?>
                                        <span class="badge bg-light text-primary border font-monospace ms-1"><?= htmlspecialchars($sw['subject_code']) ?></span>
                                    </div>
                                    <div class="fw-bold <?= ($pct >= 75) ? 'text-success' : 'text-danger' ?>">
                                        <?= $pct ?>%
                                    </div>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar <?= ($pct >= 75) ? 'bg-success' : 'bg-danger' ?>" 
                                         role="progressbar" 
                                         style="width: <?= min(100, $pct) ?>%" 
                                         aria-valuenow="<?= $pct ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Present: <strong><?= (int)$sw['present_count'] ?></strong> &bull; Late: <strong><?= (int)$sw['late_count'] ?></strong> &bull; Absent: <strong><?= (int)$sw['absent_count'] ?></strong></span>
                                    <span>Total: <?= $tot ?> Lectures</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">No subject attendance logged yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Subject Bar Chart -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Subject Comparison %</h6>
                        <small class="text-muted">Visual percentage chart</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Chart.js</span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 260px;">
                        <canvas id="studentSubjectBarChart"></canvas>
                    </div>
                    <div class="text-center mt-3 small text-muted">
                        University Minimum: <strong>75%</strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Complete Session History Logs Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar2-range-fill text-success me-2"></i>Lecture Session Logs</h6>
                <small class="text-muted">Chronological attendance record for your roll number</small>
            </div>
            <span class="badge bg-light text-secondary border"><?= count($sessionLogs) ?> Sessions Logged</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table-portal mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Subject</th>
                            <th>Lecture Topic</th>
                            <th>Instructor</th>
                            <th>Your Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sessionLogs)): ?>
                            <?php foreach ($sessionLogs as $log): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold small"><?= date('M d, Y', strtotime($log['session_date'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($log['session_time'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($log['subject_code']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($log['subject_name']) ?></div>
                                    </td>
                                    <td class="small text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($log['topic']) ?>">
                                        <?= htmlspecialchars($log['topic'] ?: 'Standard Lecture') ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($log['teacher_name']) ?></td>
                                    <td>
                                        <?php if ($log['status'] === 'Present'): ?>
                                            <span class="badge badge-status badge-present">Present</span>
                                        <?php elseif ($log['status'] === 'Late'): ?>
                                            <span class="badge badge-status badge-late">Late</span>
                                        <?php elseif ($log['status'] === 'Excused'): ?>
                                            <span class="badge bg-secondary-subtle text-secondary">Leave</span>
                                        <?php else: ?>
                                            <span class="badge badge-status badge-absent">Absent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($log['remarks'] ?: '&mdash;') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No attendance logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Chart.js Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('studentSubjectBarChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($subjLabels) ?>,
                datasets: [{
                    label: 'Subject Attendance %',
                    data: <?= json_encode($subjScores) ?>,
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(6, 182, 212, 0.85)'
                    ],
                    borderColor: '#ffffff',
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
                            callback: function(val) { return val + '%'; }
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
