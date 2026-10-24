<?php
/**
 * Permanently deletes a student, their QR code, and their visit history.
 *
 * @endpoint POST /api/students/delete.php
 * @auth Required
 * @body JSON { "id": 123 }
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST is allowed'], 405);
}

$data = readJsonBody();
$studentId = isset($data['id']) ? (int) $data['id'] : 0;
if ($studentId <= 0) {
    jsonResponse(['success' => false, 'message' => 'A valid student ID is required'], 400);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $studentStmt = $pdo->prepare('SELECT id FROM students WHERE id = :id LIMIT 1 FOR UPDATE');
    $studentStmt->execute([':id' => $studentId]);
    if (!$studentStmt->fetch()) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Student not found'], 404);
    }

    $historyStmt = $pdo->prepare('DELETE FROM visitor_logs WHERE student_id = :student_id');
    $historyStmt->execute([':student_id' => $studentId]);

    $deleteStmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $deleteStmt->execute([':id' => $studentId]);

    $auditStmt = $pdo->prepare(
        'INSERT INTO audit_logs (admin_id, student_id, action, details)
         VALUES (:admin_id, NULL, :action, :details)'
    );
    $auditStmt->execute([
        ':admin_id' => $admin['id'],
        ':action' => 'student_deleted',
        ':details' => json_encode([
            'student_id' => $studentId,
            'visitor_logs_deleted' => $historyStmt->rowCount()
        ])
    ]);

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Student account, QR code, and visit history permanently deleted.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse([
        'success' => false,
        'message' => 'Unable to delete student',
        'error' => APP_ENV === 'development' ? $e->getMessage() : null
    ], 500);
}
