<?php
/**
 * Updates a student's details.
 *
 * @endpoint POST /api/students/update.php
 * @auth Required
 * @body JSON { "id": 123, ...student fields to update }
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST is allowed'], 405);
}

$data = readJsonBody();
$id = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'Student ID is required'], 400);
}

$required = ['student_number', 'full_name', 'email', 'program', 'year_level'];
foreach ($required as $field) {
    if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
        jsonResponse(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'], 400);
    }
}

$studentNumber = sanitizeString($data['student_number']);
$fullName = sanitizeString($data['full_name']);
$email = sanitizeString(strtolower($data['email']));
$program = sanitizeString($data['program']);
$yearLevel = sanitizeString($data['year_level']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
}

try {
    $pdo = db();
    $studentStmt = $pdo->prepare('SELECT id FROM students WHERE id = :id LIMIT 1');
    $studentStmt->execute([':id' => $id]);
    if (!$studentStmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Student not found'], 404);
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT id FROM students WHERE id <> :id AND (student_number = :student_number OR email = :email) LIMIT 1'
    );
    $duplicateStmt->execute([
        ':id' => $id,
        ':student_number' => $studentNumber,
        ':email' => $email
    ]);
    if ($duplicateStmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Student ID or email is already registered'], 409);
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'UPDATE students
         SET student_number = :student_number, full_name = :full_name, email = :email, program = :program, year_level = :year_level
         WHERE id = :id'
    );
    $stmt->execute([
        ':student_number' => $studentNumber,
        ':full_name' => $fullName,
        ':email' => $email,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':id' => $id
    ]);

    $auditStmt = $pdo->prepare(
        'INSERT INTO audit_logs (admin_id, student_id, action, details)
         VALUES (:admin_id, :student_id, :action, :details)'
    );
    $auditStmt->execute([
        ':admin_id' => $admin['id'],
        ':student_id' => $id,
        ':action' => 'student_updated',
        ':details' => json_encode(['student_number' => $studentNumber])
    ]);
    $pdo->commit();

    jsonResponse(['success' => true, 'message' => 'Student updated successfully']);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    jsonResponse(['success' => false, 'message' => 'Student update failed', 'error' => APP_ENV === 'development' ? $e->getMessage() : null], 500);
} // End: update student
