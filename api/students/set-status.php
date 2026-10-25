<?php
/**
 * Activates or deactivates a student.
 *
 * @endpoint POST /api/students/set-status.php
 * @auth Required
 * @body JSON { "id": 123, "account_status": "active|inactive" }
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST is allowed'], 405);
}

$data = readJsonBody();
$studentId = isset($data['id']) ? (int) $data['id'] : 0;
$status = $data['account_status'] ?? '';

if ($studentId <= 0 || !in_array($status, ['active', 'inactive'], true)) {
    jsonResponse(['success' => false, 'message' => 'A valid student ID and account status are required'], 400);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $studentStmt = $pdo->prepare('SELECT id, account_status FROM students WHERE id = :id LIMIT 1 FOR UPDATE');
    $studentStmt->execute([':id' => $studentId]);
    $student = $studentStmt->fetch();
    if (!$student) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Student not found'], 404);
    }

    $updateStmt = $pdo->prepare('UPDATE students SET account_status = :status WHERE id = :id');
    $updateStmt->execute([':status' => $status, ':id' => $studentId]);

    if ($status === 'inactive') {
        $revokeStmt = $pdo->prepare(
            "UPDATE student_qr_codes SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP
             WHERE student_id = :student_id AND status = 'active'"
        );
        $revokeStmt->execute([':student_id' => $studentId]);
    }

    $auditStmt = $pdo->prepare(
        'INSERT INTO audit_logs (admin_id, student_id, action, details)
         VALUES (:admin_id, :student_id, :action, :details)'
    );
    $auditStmt->execute([
        ':admin_id' => $admin['id'],
        ':student_id' => $studentId,
        ':action' => $status === 'inactive' ? 'student_deactivated' : 'student_activated',
        ':details' => json_encode(['previous_status' => $student['account_status'], 'account_status' => $status])
    ]);

    $pdo->commit();
    jsonResponse([
        'success' => true,
        'message' => $status === 'inactive'
            ? 'Student deactivated. Their visit history has been preserved.'
            : 'Student activated. Generate a new QR code before their next visit.'
    ]);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    jsonResponse([
        'success' => false,
        'message' => 'Unable to change student status',
        'error' => APP_ENV === 'development' ? $e->getMessage() : null
    ], 500);
} // End: student status
