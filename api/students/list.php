<?php
/**
 * Lists student directory records and QR/account statuses.
 *
 * @endpoint GET /api/students/list.php
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

requireAuth();

$sql = 'SELECT s.id, s.student_number, s.full_name, s.email, s.program, s.year_level, s.account_status, s.created_at, q.status AS qr_status FROM students s LEFT JOIN student_qr_codes q ON q.student_id = s.id ORDER BY s.created_at DESC';
$stmt = db()->query($sql);
$students = $stmt->fetchAll();

jsonResponse(['success' => true, 'data' => $students]);
