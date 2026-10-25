<?php
/**
 * Gets dashboard counts and recent activity.
 *
 * @endpoint GET /api/reports/dashboard.php
 * @auth Required
 */
require_once __DIR__ . '/../config.php';

requireAuth();

try {
    $metrics = db()->query(
        "SELECT
            (SELECT COUNT(*) FROM students) AS total_students,
            (SELECT COUNT(*) FROM students WHERE account_status = 'active') AS active_student_accounts,
            (SELECT COUNT(*) FROM visitor_logs WHERE visit_date = CURDATE()) AS today_visits,
            (SELECT COUNT(*) FROM visitor_logs WHERE visit_date = CURDATE() AND purpose = 'STUDY') AS study_visits_today,
            (SELECT COUNT(*) FROM visitor_logs WHERE visit_date = CURDATE() AND purpose = 'BORROW_BOOK') AS borrow_book_visits_today,
            CURDATE() AS today"
    )->fetch();

    $chartRows = db()->query(
        'SELECT visit_date, COUNT(*) AS visits
         FROM visitor_logs
         WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY visit_date
         ORDER BY visit_date ASC'
    )->fetchAll();
    $visitsByDate = [];
    foreach ($chartRows as $row) {
        $visitsByDate[$row['visit_date']] = (int) $row['visits'];
    }
    $activity = [];
    $todayDate = new DateTimeImmutable($metrics['today']);
    for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
        $date = $todayDate->modify("-{$daysAgo} days")->format('Y-m-d');
        $activity[] = [
            'date' => $date,
            'label' => $todayDate->modify("-{$daysAgo} days")->format('D'),
            'visits' => $visitsByDate[$date] ?? 0
        ];
    }

    $recentVisits = db()->query(
        'SELECT v.student_number_snapshot AS student_number, s.full_name, v.visit_date, v.time_in, v.purpose
         FROM visitor_logs v
         INNER JOIN students s ON s.id = v.student_id
         ORDER BY v.visit_date DESC, v.time_in DESC, v.id DESC
         LIMIT 5'
    )->fetchAll();
    $latestStudents = db()->query(
        'SELECT student_number, full_name, program, created_at
         FROM students
         ORDER BY created_at DESC, id DESC
         LIMIT 5'
    )->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => [
            'total_students' => (int) $metrics['total_students'],
            'today_visits' => (int) $metrics['today_visits'],
            'active_student_accounts' => (int) $metrics['active_student_accounts'],
            'study_visits_today' => (int) $metrics['study_visits_today'],
            'borrow_book_visits_today' => (int) $metrics['borrow_book_visits_today'],
            'activity' => $activity,
            'recent_visits' => $recentVisits,
            'latest_students' => $latestStudents,
            'updated_at' => date(DATE_ATOM)
        ]
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Dashboard metrics unavailable', 'error' => APP_ENV === 'development' ? $e->getMessage() : null], 500);
} // End: dashboard report
