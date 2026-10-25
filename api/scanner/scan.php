<?php
/**
 * Checks a QR code and records the student's visit.
 *
 * @endpoint POST /api/scanner/scan.php
 * @auth Required
 * @body JSON { "qr_identifier": "...", "purpose": "STUDY|BORROW_BOOK" }
 */
require_once __DIR__ . '/../config.php';

requireAuth();

$data = readJsonBody();
$identifier = $data['qr_identifier'] ?? '';
$purpose = strtoupper((string) ($data['purpose'] ?? 'STUDY'));

if ($identifier === '') {
    jsonResponse(['success' => false, 'message' => 'QR code identifier is required'], 400);
}

$hash = hashQrIdentifier((string) $identifier);

try {
    // Finds the QR token and its student.
    $stmt = db()->prepare(
        'SELECT s.*, q.qr_identifier_hash, q.status AS qr_status FROM students s INNER JOIN student_qr_codes q ON q.student_id = s.id WHERE q.qr_identifier_hash = :hash LIMIT 1'
    );
    $stmt->execute([':hash' => $hash]);
    $student = $stmt->fetch();

    if (!$student) {
        jsonResponse(['success' => false, 'message' => 'Invalid QR code. Please contact library staff.'], 404);
    }

    if ($student['account_status'] !== 'active') {
        jsonResponse(['success' => false, 'message' => 'This student account is inactive.'], 403);
    }

    if ($student['qr_status'] !== 'active') {
        jsonResponse(['success' => false, 'message' => 'This QR code has been revoked or disabled.'], 403);
    }

    $check = db()->prepare('SELECT id FROM visitor_logs WHERE student_id = :student_id AND visit_date = CURDATE() LIMIT 1');
    $check->execute([':student_id' => $student['id']]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'This QR code has already been used for this check-in.'], 409);
    }

    if (!in_array($purpose, ['STUDY', 'BORROW_BOOK'], true)) {
        jsonResponse(['success' => false, 'message' => 'Select a valid visit purpose.'], 400);
    }

    $insert = db()->prepare('INSERT INTO visitor_logs (student_id, student_number_snapshot, visit_date, time_in, purpose) VALUES (:student_id, :student_number_snapshot, CURDATE(), CURRENT_TIME(), :purpose)');
    $insert->execute([
        ':student_id' => $student['id'],
        ':student_number_snapshot' => $student['student_number'],
        ':purpose' => $purpose
    ]);
    $visitId = db()->lastInsertId();
    $visitStmt = db()->prepare('SELECT visit_date, time_in FROM visitor_logs WHERE id = :id');
    $visitStmt->execute([':id' => $visitId]);
    $visit = $visitStmt->fetch();

    jsonResponse([
        'success' => true,
        'message' => 'ACCESS RECORDED SUCCESSFULLY',
        'data' => [
            'student' => [
                'id' => $student['id'],
                'student_number' => $student['student_number'],
                'full_name' => $student['full_name'],
                'program' => $student['program'],
                'year_level' => $student['year_level']
            ],
            'purpose' => $purpose,
            'visit_date' => $visit['visit_date'],
            'time_in' => $visit['time_in']
        ]
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Scanner processing failed', 'error' => APP_ENV === 'development' ? $e->getMessage() : null], 500);
} // End: scan and log visit
