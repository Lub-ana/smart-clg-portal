<?php
/**
 * Smart College Management Portal - Notifications & Announcements Engine
 * Provides role broadcasts, administrative alerts, and user notifications.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce authentication
requireLogin();

$page_title = 'Notifications & Announcements';
$active_nav = 'notifications';
$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? '';
$userId = (int)($currentUser['id'] ?? 0);

$notificationsList = [];

if ($pdo instanceof PDO) {
    try {
        // Handle POST Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token  = $_POST['csrf_token'] ?? '';
            $action = $_POST['action'] ?? '';

            if (!verifyCsrfToken($token)) {
                setFlash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
                header('Location: notifications.php');
                exit;
            }

            // Action: Mark Single Notification as Read
            if ($action === 'mark_read') {
                $notifId = (int)($_POST['notification_id'] ?? 0);
                if ($notifId > 0) {
                    $stmtRead = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `id` = :id");
                    $stmtRead->execute([':id' => $notifId]);
                    setFlash('success', 'Notification marked as read.');
                }
                header('Location: notifications.php');
                exit;
            }

            // Action: Mark All as Read
            if ($action === 'mark_all_read') {
                $stmtAllRead = $pdo->prepare("
                    UPDATE `notifications` 
                    SET `is_read` = 1 
                    WHERE (`target_role` = 'all' OR `target_role` = :role OR `user_id` = :uid)
                ");
                $stmtAllRead->execute([':role' => $role, ':uid' => $userId]);
                setFlash('success', 'All notifications marked as read.');
                header('Location: notifications.php');
                exit;
            }

            // Action: Admin Broadcast Notice
            if ($action === 'broadcast' && hasRole('admin')) {
                $targetRole = $_POST['target_role'] ?? 'all';
                $title      = trim($_POST['title'] ?? '');
                $message    = trim($_POST['message'] ?? '');

                if (empty($title) || empty($message)) {
                    setFlash('danger', 'Notice title and message content are required.');
                } else {
                    $stmtBroad = $pdo->prepare("
                        INSERT INTO `notifications` (`user_id`, `target_role`, `title`, `message`, `is_read`) 
                        VALUES (NULL, :target, :title, :msg, 0)
                    ");
                    $stmtBroad->execute([
                        ':target' => in_array($targetRole, ['all', 'admin', 'teacher', 'student'], true) ? $targetRole : 'all',
                        ':title'  => $title,
                        ':msg'    => $message
                    ]);
                    setFlash('success', "Campus notification broadcasted to '$targetRole' members successfully.");
                }
                header('Location: notifications.php');
                exit;
            }
        }

        // Fetch notifications relevant to current user
        $stmtNotifs = $pdo->prepare("
            SELECT * 
            FROM `notifications` 
            WHERE (`target_role` = 'all' OR `target_role` = :role OR `user_id` = :uid)
            ORDER BY `is_read` ASC, `created_at` DESC
        ");
        $stmtNotifs->execute([':role' => $role, ':uid' => $userId]);
        $notificationsList = $stmtNotifs->fetchAll();

    } catch (PDOException $e) {
        error_log("Notifications Query Error: " . $e->getMessage());
    }
}

$csrfToken = generateCsrfToken();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
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
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Overview</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Notifications</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">System Notifications & Announcements</h2>
            <p class="text-secondary small mb-0">Campus administrative updates, academic deadlines, and activity alerts.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasRole('admin')): ?>
                <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                    <i class="bi bi-megaphone-fill"></i>
                    <span>Broadcast New Notice</span>
                </button>
            <?php endif; ?>

            <?php if (!empty($notificationsList)): ?>
                <form method="POST" action="notifications.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="d-flex flex-column gap-3 mb-4">
        <?php if (!empty($notificationsList)): ?>
            <?php foreach ($notificationsList as $notif): ?>
                <?php $isUnread = ((int)$notif['is_read'] === 0); ?>
                <div class="card border-0 shadow-sm <?= $isUnread ? 'border-start border-4 border-primary bg-primary-subtle bg-opacity-25' : '' ?>">
                    <div class="card-body p-4 d-flex justify-content-between align-items-start gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="stat-icon-wrapper <?= $isUnread ? 'stat-icon-blue' : 'stat-icon-cyan' ?> flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.25rem;">
                                <i class="bi <?= $isUnread ? 'bi-bell-fill' : 'bi-bell' ?>"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <h5 class="fw-bold text-dark mb-0 fs-6"><?= htmlspecialchars($notif['title']) ?></h5>
                                    
                                    <!-- Target Scope Badge -->
                                    <span class="badge bg-light text-secondary border font-monospace text-uppercase" style="font-size: 0.7rem;">
                                        <i class="bi bi-globe me-1"></i><?= htmlspecialchars($notif['target_role']) ?>
                                    </span>

                                    <?php if ($isUnread): ?>
                                        <span class="badge bg-primary text-white" style="font-size: 0.68rem;">New Unread</span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-secondary small mb-2"><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
                                
                                <span class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                    <i class="bi bi-clock me-1"></i><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($isUnread): ?>
                            <form method="POST" action="notifications.php" class="flex-shrink-0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Mark as Read">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-bell-slash fs-1 d-block mb-3 text-secondary"></i>
                <h5>No Notifications</h5>
                <p class="small mb-0">You are all caught up! Academic notices and campus announcements will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- Modal: Admin Broadcast Notice -->
<?php if (hasRole('admin')): ?>
<div class="modal fade" id="broadcastModal" tabindex="-1" aria-labelledby="broadcastModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="notifications.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="broadcast">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="broadcastModalLabel">
                        <i class="bi bi-megaphone-fill text-primary me-2"></i>Dispatch Campus Broadcast
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_target" class="form-label small fw-semibold text-secondary">Target Audience</label>
                        <select class="form-select" id="modal_target" name="target_role" required>
                            <option value="all">Entire College (All Students, Teachers, Staff)</option>
                            <option value="student">Students Only</option>
                            <option value="teacher">Faculty & Teachers Only</option>
                            <option value="admin">Administrators Only</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_notif_title" class="form-label small fw-semibold text-secondary">Notice Title</label>
                        <input type="text" class="form-control" id="modal_notif_title" name="title" placeholder="e.g. Campus Holiday Notice / Tech Fest Registration" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal_notif_msg" class="form-label small fw-semibold text-secondary">Message Content</label>
                        <textarea class="form-control" id="modal_notif_msg" name="message" rows="4" placeholder="Detail the circular, dates, requirements, or guidelines..." required></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-broadcast me-1"></i>Broadcast Notice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
