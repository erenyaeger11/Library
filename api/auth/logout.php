<?php
/**
 * Signs out the administrator.
 *
 * @endpoint POST /api/auth/logout.php
 * @auth Session-based
 */
require_once __DIR__ . '/../config.php';

startSession();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
// End: admin logout
