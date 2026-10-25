<?php
/**
 * Gets one student's details and visit history.
 *
 * @endpoint GET /api/students/show.php?id={id}
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'Student ID is required'], 400);
}

$stmt = db()->prepare(
    'SELECT s.id, s.student_number, s.full_name, s.email, s.program, s.year_level,
            s.account_status, s.created_at, s.updated_at,
            (SELECT q.status FROM student_qr_codes q WHERE q.student_id = s.id ORDER BY q.created_at DESC, q.id DESC LIMIT 1) AS qr_status
     FROM students s
     WHERE s.id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    jsonResponse(['success' => false, 'message' => 'Student not found'], 404);
}

$historyStmt = db()->prepare(
    'SELECT visit_date, time_in, purpose
     FROM visitor_logs
     WHERE student_id = :student_id
     ORDER BY visit_date DESC, time_in DESC
     LIMIT 50'
);
$historyStmt->execute([':student_id' => $id]);
$student['visit_history'] = $historyStmt->fetchAll();

jsonResponse(['success' => true, 'data' => $student]);
// End: student profile
