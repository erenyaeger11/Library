<?php
/**
 * Authenticates an administrator and creates a secure PHP session.
 *
 * @endpoint POST /api/auth/login.php
 * @auth Public
 * @body JSON { "email": "admin@example.com", "password": "..." }
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST is allowed'], 405);
}

$data = readJsonBody();
$email = sanitizeString($data['email'] ?? '');
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
}

try {
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
    }

    startSession();
    session_regenerate_id(true);

    $_SESSION['admin'] = [
        'id' => (int) $admin['id'],
        'full_name' => $admin['full_name'],
        'email' => $admin['email'],
        'role' => $admin['role']
    ];

    jsonResponse([
        'success' => true,
        'message' => 'Admin login successful',
        'data' => $_SESSION['admin']
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Login failed', 'error' => APP_ENV === 'development' ? $e->getMessage() : null], 500);
}
