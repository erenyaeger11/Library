<?php
/**
 * Gets the student list.
 *
 * @endpoint GET /api/students/list.php
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

requireAuth();

$sql = 'SELECT s.id, s.student_number, s.full_name, s.email, s.program, s.year_level, s.account_status, s.created_at,
               (SELECT q.status FROM student_qr_codes q WHERE q.student_id = s.id ORDER BY q.created_at DESC, q.id DESC LIMIT 1) AS qr_status
        FROM students s ORDER BY s.created_at DESC';
$stmt = db()->query($sql);
$students = $stmt->fetchAll();

jsonResponse(['success' => true, 'data' => $students]);
// End: student list
