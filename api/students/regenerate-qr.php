<?php
/**
 * Makes another QR code for a student.
 *
 * @endpoint POST /api/students/regenerate-qr.php
 * @auth Required
 * @body JSON { "student_id": 123 }
 */
require_once __DIR__ . '/../config.php';

$admin = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST is allowed'], 405);
}

$data = readJsonBody();
$studentId = isset($data['student_id']) ? (int) $data['student_id'] : 0;
if ($studentId <= 0) {
    jsonResponse(['success' => false, 'message' => 'A valid student ID is required'], 400);
}

try {
    $pdo = db();
    $studentStmt = $pdo->prepare('SELECT id, student_number, full_name FROM students WHERE id = :id AND account_status = :status LIMIT 1');
    $studentStmt->execute([':id' => $studentId, ':status' => 'active']);
    $student = $studentStmt->fetch();
    if (!$student) {
        jsonResponse(['success' => false, 'message' => 'Active student not found'], 404);
    }

    $identifier = bin2hex(random_bytes(32));
    $identifierHash = hashQrIdentifier($identifier);

    $pdo->beginTransaction();
    $qrStmt = $pdo->prepare(
        'INSERT INTO student_qr_codes (student_id, qr_identifier_hash, status)
         VALUES (:student_id, :identifier_hash, :status)'
    );
    $qrStmt->execute([
        ':student_id' => $studentId,
        ':identifier_hash' => $identifierHash,
        ':status' => 'active'
    ]);

    $auditStmt = $pdo->prepare(
        'INSERT INTO audit_logs (admin_id, student_id, action, details)
         VALUES (:admin_id, :student_id, :action, :details)'
    );
    $auditStmt->execute([
        ':admin_id' => $admin['id'],
        ':student_id' => $studentId,
        ':action' => 'student_qr_regenerated',
        ':details' => json_encode(['student_number' => $student['student_number']])
    ]);
    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'QR code generated. Save or print it now; the identifier is only shown once.',
        'data' => [
            'student_id' => $studentId,
            'student_number' => $student['student_number'],
            'full_name' => $student['full_name'],
            'qr_identifier' => $identifier
        ]
    ]);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    jsonResponse([
        'success' => false,
        'message' => 'QR code generation failed',
        'error' => APP_ENV === 'development' ? $e->getMessage() : null
    ], 500);
} // End: make QR code
