<?php
/**
 * Smart College Management Portal - Logout Handler
 * Securely terminates the session and redirects to the login screen.
 */

require_once __DIR__ . '/includes/auth.php';

// Terminate user session
logoutUser();

// Start a fresh session specifically to store the logout flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
setFlash('info', 'You have been successfully signed out.');

// Redirect to login page
header('Location: ' . getBaseUrl() . 'login.php');
exit;
