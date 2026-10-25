<?php
/**
 * Gets the signed-in admin's details.
 *
 * @endpoint GET /api/auth/me.php
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
jsonResponse(['success' => true, 'data' => $admin]);
// End: current admin
