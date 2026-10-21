<?php
/**
 * Smart College Management Portal - Authentication & Authorization Helper
 * Handles secure session initialization, role-based authorization,
 * CSRF token management, and flash notifications.
 */

// Initialize secure session if not already active
if (session_status() === PHP_SESSION_NONE) {
    // Session security configuration
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

/**
 * Returns the base URL for the portal.
 * Defaults to '/smart-clg-portal/' under standard XAMPP htdocs.
 *
 * @return string
 */
function getBaseUrl(): string {
    return '/smart-clg-portal/';
}

/**
 * Checks whether a user is currently authenticated in the session.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Retrieves the currently logged-in user array, or null if unauthenticated.
 *
 * @return array|null
 */
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Checks whether the current user has one of the specified roles.
 *
 * @param string|array $roles A single role string or array of roles (e.g. 'admin' or ['admin', 'teacher'])
 * @return bool
 */
function hasRole($roles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    $userRole = $_SESSION['user']['role'] ?? '';
    if (is_array($roles)) {
        return in_array($userRole, $roles, true);
    }
    return $userRole === $roles;
}

/**
 * Enforces that a user must be logged in.
 * If not authenticated, redirects to login.php with an informative flash message.
 *
 * @return void
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access that page.');
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
}

/**
 * Enforces server-side authorization for specific user roles.
 * If unauthorized, redirects to the user's appropriate dashboard or login.
 *
 * @param string|array $roles Role(s) permitted to access the resource.
 * @return void
 */
function requireRole($roles): void {
    requireLogin();

    if (!hasRole($roles)) {
        $roleStr = is_array($roles) ? implode(', ', $roles) : $roles;
        setFlash('danger', 'Access Denied: You do not have permission to view this resource (Required role: ' . htmlspecialchars($roleStr) . ').');
        
        // Redirect to the user's own appropriate dashboard
        redirectUserToDashboard();
        exit;
    }
}

/**
 * Redirects the currently authenticated user to their role-specific dashboard.
 *
 * @return void
 */
function redirectUserToDashboard(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }

    $role = $_SESSION['user']['role'] ?? '';
    switch ($role) {
        case 'admin':
            header('Location: ' . getBaseUrl() . 'modules/admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: ' . getBaseUrl() . 'modules/teacher/dashboard.php');
            break;
        case 'student':
            header('Location: ' . getBaseUrl() . 'modules/student/dashboard.php');
            break;
        default:
            header('Location: ' . getBaseUrl() . 'index.php');
            break;
    }
    exit;
}

/**
 * Authenticates a user into the session and regenerates the session ID to prevent session fixation.
 *
 * @param array $user User database record
 * @return void
 */
function loginUser(array $user): void {
    // Regenerate session ID on privilege level change if headers not yet sent
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'status'    => $user['status'],
    ];

    // Refresh CSRF token for the new authenticated session
    generateCsrfToken();
}

/**
 * Destroys the current user session and clears session cookies securely.
 *
 * @return void
 */
function logoutUser(): void {
    $_SESSION = [];

    if (!headers_sent() && ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Generates and returns a cryptographically secure CSRF token.
 *
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token using timing-safe comparison.
 *
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sets a flash message to be displayed on the next request.
 *
 * @param string $type Bootstrap alert type: 'success', 'danger', 'warning', 'info'
 * @param string $message
 * @return void
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieves and clears all stored flash messages.
 *
 * @return array
 */
function getFlashes(): array {
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Renders HTML alerts for any active flash messages.
 *
 * @return string
 */
function renderFlashes(): string {
    $flashes = getFlashes();
    if (empty($flashes)) {
        return '';
    }

    $html = '<div class="flash-messages-container mb-3">';
    foreach ($flashes as $flash) {
        $type = htmlspecialchars($flash['type']);
        $icon = match($type) {
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            default   => 'bi-info-circle-fill'
        };
        $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        $html .= '<i class="bi ' . $icon . ' me-2 fs-5"></i>';
        $html .= '<div>' . htmlspecialchars($flash['message']) . '</div>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Returns the unread notification count for the currently authenticated user.
 *
 * @return int
 */
function getUnreadNotificationCount(): int {
    global $pdo;
    if (!isLoggedIn() || !($pdo instanceof PDO)) {
        return 0;
    }
    $user = getCurrentUser();
    $role = $user['role'] ?? '';
    $uid  = (int)($user['id'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM `notifications` 
            WHERE (`target_role` = 'all' OR `target_role` = :role OR `user_id` = :uid)
              AND `is_read` = 0
        ");
        $stmt->execute([':role' => $role, ':uid' => $uid]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
