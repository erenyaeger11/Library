<?php
/**
 * Creates a student and their QR code.
 *
 * @endpoint POST /api/students/create.php
 * @auth Required
 * @body JSON student_number, full_name, email, program, year_level
 */
require_once __DIR__ . '/../config.php';

requireAuth();

$data = readJsonBody();
$required = ['student_number', 'full_name', 'email', 'program', 'year_level'];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
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
    $existing = db()->prepare('SELECT id FROM students WHERE student_number = :student_number OR email = :email LIMIT 1');
    $existing->execute([':student_number' => $studentNumber, ':email' => $email]);
    if ($existing->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Student ID or email is already registered'], 409);
    }

    db()->beginTransaction();
    $stmt = db()->prepare('INSERT INTO students (student_number, full_name, email, program, year_level, account_status) VALUES (:student_number, :full_name, :email, :program, :year_level, :account_status)');
    $stmt->execute([
        ':student_number' => $studentNumber,
        ':full_name' => $fullName,
        ':email' => $email,
        ':program' => $program,
        ':year_level' => $yearLevel,
        ':account_status' => 'active'
    ]);

    $studentId = (int) db()->lastInsertId();
    $qrIdentifier = generateQrIdentifier();
    $qrHash = hashQrIdentifier($qrIdentifier);

    $qrStmt = db()->prepare('INSERT INTO student_qr_codes (student_id, qr_identifier_hash, status) VALUES (:student_id, :qr_identifier_hash, :status)');
    $qrStmt->execute([
        ':student_id' => $studentId,
        ':qr_identifier_hash' => $qrHash,
        ':status' => 'active'
    ]);

    db()->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Student account created successfully',
        'data' => [
            'id' => $studentId,
            'student_number' => $studentNumber,
            'full_name' => $fullName,
            'email' => $email,
            'program' => $program,
            'year_level' => $yearLevel,
            'qr_identifier' => $qrIdentifier
        ]
    ], 201);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    jsonResponse(['success' => false, 'message' => 'Student creation failed', 'error' => APP_ENV === 'development' ? $e->getMessage() : null], 500);
} // End: create student
