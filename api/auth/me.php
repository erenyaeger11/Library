<?php
/**
 * Returns the authenticated administrator's session profile.
 *
 * @endpoint GET /api/auth/me.php
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
jsonResponse(['success' => true, 'data' => $admin]);
