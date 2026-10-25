<?php
/**
 * Gets recent library visits.
 *
 * @endpoint GET /api/reports/visitor-logs.php
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

requireAuth();

try {
    $stmt = db()->query(
        'SELECT v.student_number_snapshot AS student_number, s.full_name, s.program, s.year_level, v.visit_date, v.time_in, v.purpose
         FROM visitor_logs v
         INNER JOIN students s ON s.id = v.student_id
         ORDER BY v.visit_date DESC, v.time_in DESC, v.id DESC
         LIMIT 100'
    );

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Visitor logs unavailable',
        'error' => APP_ENV === 'development' ? $e->getMessage() : null
    ], 500);
} // End: visit report
