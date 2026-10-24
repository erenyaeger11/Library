<?php
require_once __DIR__ . '/api/config.php';

define('LIBRARY_APP_ENTRY', true);
startSession();

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectToPage(string $page = 'dashboard'): void
{
    header('Location: index.php?page=' . rawurlencode($page));
    exit;
}

function setFlash(string $key, $value): void
{
    $_SESSION['flash'][$key] = $value;
}

function getFlash(string $key)
{
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function requireCsrfToken(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }
}

function auditStudentAction(array $admin, ?int $studentId, string $action, array $details = []): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (admin_id, student_id, action, details)
         VALUES (:admin_id, :student_id, :action, :details)'
    );
    $stmt->execute([
        ':admin_id' => $admin['id'],
        ':student_id' => $studentId,
        ':action' => $action,
        ':details' => json_encode($details, JSON_UNESCAPED_SLASHES)
    ]);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$pageTitles = [
    'dashboard' => 'Admin Dashboard',
    'students' => 'Student Management',
    'qr-codes' => 'QR Code Management',
    'scanner' => 'Library Entry Scanner',
    'logs' => 'Visitor Logs',
    'reports' => 'Reports and Analytics',
    'settings' => 'Settings'
];
$page = $_GET['page'] ?? ($_POST['page'] ?? 'dashboard');
if (!is_string($page) || !isset($pageTitles[$page])) {
    $page = 'dashboard';
}
$admin = $_SESSION['admin'] ?? null;
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($basePath === '.') {
    $basePath = '';
}
$flashMessage = getFlash('message');
$flashError = getFlash('error');
$createdStudent = getFlash('created_student');
$generatedQr = getFlash('generated_qr');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        requireCsrfToken();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        try {
            $stmt = db()->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $account = $stmt->fetch();
            if ($account && password_verify($password, $account['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin'] = [
                    'id' => (int) $account['id'],
                    'full_name' => $account['full_name'],
                    'email' => $account['email'],
                    'role' => $account['role']
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                redirectToPage();
            }
            setFlash('error', 'Invalid email or password.');
        } catch (Throwable $e) {
            setFlash('error', 'Sign in failed. Check the PHP database configuration.');
        }
        header('Location: index.php');
        exit;
    }

    if ($action === 'logout') {
        requireCsrfToken();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
        }
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (!$admin) {
        header('Location: index.php');
        exit;
    }
    requireCsrfToken();

    try {
        $pdo = db();
        if ($action === 'create_student' || $action === 'update_student') {
            $studentId = (int) ($_POST['id'] ?? 0);
            $studentNumber = sanitizeString($_POST['student_number'] ?? '');
            $fullName = sanitizeString($_POST['full_name'] ?? '');
            $email = strtolower((string) sanitizeString($_POST['email'] ?? ''));
            $program = sanitizeString($_POST['program'] ?? '');
            $yearLevel = sanitizeString($_POST['year_level'] ?? '');

            if (!$studentNumber || !$fullName || !$email || !$program || !$yearLevel || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter all student details and a valid email address.');
            }

            $duplicate = $pdo->prepare(
                'SELECT id FROM students WHERE (student_number = :student_number OR email = :email) AND id <> :id LIMIT 1'
            );
            $duplicate->execute([
                ':student_number' => $studentNumber,
                ':email' => $email,
                ':id' => $action === 'update_student' ? $studentId : 0
            ]);
            if ($duplicate->fetch()) {
                throw new InvalidArgumentException('Student ID or email is already registered.');
            }

            if ($action === 'create_student') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO students (student_number, full_name, email, program, year_level, account_status)
                     VALUES (:student_number, :full_name, :email, :program, :year_level, :account_status)'
                );
                $stmt->execute([
                    ':student_number' => $studentNumber,
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':program' => $program,
                    ':year_level' => $yearLevel,
                    ':account_status' => 'active'
                ]);
                $studentId = (int) $pdo->lastInsertId();
                $identifier = generateQrIdentifier();
                $qrStmt = $pdo->prepare(
                    'INSERT INTO student_qr_codes (student_id, qr_identifier_hash, status)
                     VALUES (:student_id, :qr_identifier_hash, :status)'
                );
                $qrStmt->execute([
                    ':student_id' => $studentId,
                    ':qr_identifier_hash' => hashQrIdentifier($identifier),
                    ':status' => 'active'
                ]);
                auditStudentAction($admin, $studentId, 'student_created', ['student_number' => $studentNumber]);
                $pdo->commit();
                setFlash('created_student', [
                    'student_number' => $studentNumber,
                    'full_name' => $fullName,
                    'qr_identifier' => $identifier
                ]);
                setFlash('message', 'Student account created successfully.');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE students
                     SET student_number = :student_number, full_name = :full_name, email = :email,
                         program = :program, year_level = :year_level
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':student_number' => $studentNumber,
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':program' => $program,
                    ':year_level' => $yearLevel,
                    ':id' => $studentId
                ]);
                if ($stmt->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT id FROM students WHERE id = :id');
                    $exists->execute([':id' => $studentId]);
                    if (!$exists->fetch()) {
                        throw new InvalidArgumentException('Student not found.');
                    }
                }
                auditStudentAction($admin, $studentId, 'student_updated', ['student_number' => $studentNumber]);
                setFlash('message', 'Student updated successfully.');
            }
            redirectToPage('students');
        }

        if ($action === 'set_status') {
            $studentId = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['account_status'] ?? '');
            if ($studentId <= 0 || !in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('A valid student and status are required.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT account_status FROM students WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $studentId]);
            $student = $stmt->fetch();
            if (!$student) {
                throw new InvalidArgumentException('Student not found.');
            }
            $update = $pdo->prepare('UPDATE students SET account_status = :status WHERE id = :id');
            $update->execute([':status' => $status, ':id' => $studentId]);
            if ($status === 'inactive') {
                $revoke = $pdo->prepare(
                    "UPDATE student_qr_codes SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP
                     WHERE student_id = :student_id AND status = 'active'"
                );
                $revoke->execute([':student_id' => $studentId]);
            }
            auditStudentAction($admin, $studentId, $status === 'inactive' ? 'student_deactivated' : 'student_activated', [
                'previous_status' => $student['account_status'],
                'account_status' => $status
            ]);
            $pdo->commit();
            setFlash('message', $status === 'inactive'
                ? 'Student deactivated. Visit history has been preserved.'
                : 'Student activated. Generate a new QR code before the next visit.');
            redirectToPage('students');
        }

        if ($action === 'delete_student') {
            $studentId = (int) ($_POST['id'] ?? 0);
            if ($studentId <= 0) {
                throw new InvalidArgumentException('A valid student ID is required.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM students WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $studentId]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('Student not found.');
            }
            $history = $pdo->prepare('DELETE FROM visitor_logs WHERE student_id = :student_id');
            $history->execute([':student_id' => $studentId]);
            $delete = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $delete->execute([':id' => $studentId]);
            auditStudentAction($admin, null, 'student_deleted', [
                'student_id' => $studentId,
                'visitor_logs_deleted' => $history->rowCount()
            ]);
            $pdo->commit();
            setFlash('message', 'Student account, QR code, and visit history permanently deleted.');
            redirectToPage('students');
        }

        if ($action === 'generate_qr') {
            $studentId = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'SELECT id, student_number, full_name FROM students WHERE id = :id AND account_status = :status LIMIT 1'
            );
            $stmt->execute([':id' => $studentId, ':status' => 'active']);
            $student = $stmt->fetch();
            if (!$student) {
                throw new InvalidArgumentException('Active student not found.');
            }
            $identifier = generateQrIdentifier();
            $pdo->beginTransaction();
            $qr = $pdo->prepare(
                'INSERT INTO student_qr_codes (student_id, qr_identifier_hash, status)
                 VALUES (:student_id, :identifier_hash, :status)
                 ON DUPLICATE KEY UPDATE qr_identifier_hash = VALUES(qr_identifier_hash),
                   status = VALUES(status), created_at = CURRENT_TIMESTAMP, revoked_at = NULL'
            );
            $qr->execute([
                ':student_id' => $studentId,
                ':identifier_hash' => hashQrIdentifier($identifier),
                ':status' => 'active'
            ]);
            auditStudentAction($admin, $studentId, 'student_qr_regenerated', ['student_number' => $student['student_number']]);
            $pdo->commit();
            setFlash('generated_qr', [
                'id' => $studentId,
                'student_number' => $student['student_number'],
                'full_name' => $student['full_name'],
                'qr_identifier' => $identifier
            ]);
            setFlash('message', 'QR code generated. Save or print it now; the identifier is only shown once.');
            redirectToPage('qr-codes');
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : (APP_ENV === 'development' ? $e->getMessage() : 'The request could not be completed.'));
        redirectToPage($page);
    }
}

$admin = $_SESSION['admin'] ?? null;
if (!$admin) {
    $loginError = $flashError;
    require __DIR__ . '/pages/login.php';
    exit;
}

$dbError = '';
$dashboard = null;
$students = [];
$logs = [];
$studentProfile = null;
try {
    if ($page === 'dashboard') {
        $metrics = db()->query(
            "SELECT
              (SELECT COUNT(*) FROM students) AS total_students,
              (SELECT COUNT(*) FROM students WHERE account_status = 'active') AS active_student_accounts,
              (SELECT COUNT(*) FROM visitor_logs WHERE visit_date = CURDATE()) AS today_visits,
              (SELECT COUNT(*) FROM visitor_logs WHERE visit_date = CURDATE() AND purpose = 'STUDY') AS study_visits_today,
              (SELECT COUNT(*) FROM visitor_logs WHERE visit_date = CURDATE() AND purpose = 'BORROW_BOOK') AS borrow_book_visits_today,
              CURDATE() AS today"
        )->fetch();
        $activityRows = db()->query(
            'SELECT visit_date, COUNT(*) AS visits FROM visitor_logs
             WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY visit_date'
        )->fetchAll();
        $activityByDate = [];
        foreach ($activityRows as $row) {
            $activityByDate[$row['visit_date']] = (int) $row['visits'];
        }
        $today = new DateTimeImmutable($metrics['today']);
        $activity = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day = $today->modify("-{$daysAgo} days");
            $activity[] = ['date' => $day->format('Y-m-d'), 'label' => $day->format('D'), 'visits' => $activityByDate[$day->format('Y-m-d')] ?? 0];
        }
        $dashboard = [
            'metrics' => $metrics,
            'activity' => $activity,
            'recent_visits' => db()->query(
                'SELECT v.student_number_snapshot AS student_number, s.full_name, v.visit_date, v.time_in, v.purpose
                 FROM visitor_logs v INNER JOIN students s ON s.id = v.student_id
                 ORDER BY v.visit_date DESC, v.time_in DESC, v.id DESC LIMIT 5'
            )->fetchAll(),
            'latest_students' => db()->query(
                'SELECT student_number, full_name, program, created_at FROM students
                 ORDER BY created_at DESC, id DESC LIMIT 5'
            )->fetchAll()
        ];
    } elseif ($page === 'students' || $page === 'qr-codes') {
        $students = db()->query(
            'SELECT s.id, s.student_number, s.full_name, s.email, s.program, s.year_level,
                    s.account_status, s.created_at, q.status AS qr_status
             FROM students s LEFT JOIN student_qr_codes q ON q.student_id = s.id
             ORDER BY s.created_at DESC'
        )->fetchAll();
        if (isset($_GET['view'])) {
            $profileStmt = db()->prepare(
                'SELECT s.id, s.student_number, s.full_name, s.email, s.program, s.year_level,
                        s.account_status, s.created_at, q.status AS qr_status
                 FROM students s LEFT JOIN student_qr_codes q ON q.student_id = s.id
                 WHERE s.id = :id LIMIT 1'
            );
            $profileStmt->execute([':id' => (int) $_GET['view']]);
            $studentProfile = $profileStmt->fetch();
            if ($studentProfile) {
                $historyStmt = db()->prepare(
                    'SELECT visit_date, time_in, purpose FROM visitor_logs
                     WHERE student_id = :id ORDER BY visit_date DESC, time_in DESC LIMIT 50'
                );
                $historyStmt->execute([':id' => $studentProfile['id']]);
                $studentProfile['visit_history'] = $historyStmt->fetchAll();
            }
        }
    } elseif ($page === 'logs' || $page === 'scanner') {
        $logs = db()->query(
            'SELECT v.student_number_snapshot AS student_number, s.full_name, s.program, s.year_level,
                    v.visit_date, v.time_in, v.purpose
             FROM visitor_logs v INNER JOIN students s ON s.id = v.student_id
             ORDER BY v.visit_date DESC, v.time_in DESC, v.id DESC LIMIT ' . ($page === 'scanner' ? '5' : '100')
        )->fetchAll();
    }
} catch (Throwable $e) {
    $dbError = APP_ENV === 'development' ? $e->getMessage() : 'Unable to load data. Check your PHP and database configuration.';
}

$query = trim((string) ($_GET['q'] ?? ''));
if ($query !== '' && $page === 'students') {
    $students = array_values(array_filter($students, static function ($student) use ($query): bool {
        foreach (['student_number', 'full_name', 'email', 'program'] as $field) {
            if (stripos($student[$field], $query) !== false) {
                return true;
            }
        }
        return false;
    }));
}
$maxVisits = 1;
foreach (($dashboard['activity'] ?? []) as $day) {
    $maxVisits = max($maxVisits, (int) $day['visits']);
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($page === 'dashboard'): ?>
        <meta http-equiv="refresh" content="15"><?php endif; ?>
    <title><?= escapeHtml($pageTitles[$page]) ?> | SSU Library</title>
    <link rel="stylesheet" href="<?= escapeHtml($basePath) ?>/assets/php-app.css">
    <?php if ($page === 'students' || $page === 'qr-codes'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
    <?php endif; ?>
    <?php if ($page === 'scanner'): ?>
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
    <?php endif; ?>
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="index.php?page=dashboard">
                <span class="brand-mark">SSU</span>
                <span><small>Library</small><strong>QR Entry</strong></span>
            </a>
            <nav class="main-nav" aria-label="Main navigation">
                <?php foreach ($pageTitles as $key => $label): ?>
                    <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= escapeHtml($key) ?>">
                        <span
                            aria-hidden="true"><?= ['dashboard' => '▦', 'students' => '♙', 'qr-codes' => '▦', 'scanner' => '▣', 'logs' => '◷', 'reports' => '▥', 'settings' => '⚙'][$key] ?></span><?= escapeHtml($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="post" class="logout-form">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                <button class="nav-link logout-link" type="submit"><span aria-hidden="true">↪</span>Logout</button>
            </form>
            <div class="sidebar-user">
                <strong><?= escapeHtml($admin['full_name']) ?></strong><span><?= escapeHtml($admin['email']) ?></span>
            </div>
        </aside>
        <main class="main-content">
            <header class="mobile-header">
                <a class="brand" href="index.php?page=dashboard"><span
                        class="brand-mark">SSU</span><span><small>Library</small><strong>QR Entry</strong></span></a>
                <button class="button secondary mobile-menu-button" type="button"
                    onclick="document.body.classList.toggle('nav-open')">Menu</button>
            </header>
            <nav class="mobile-nav" aria-label="Mobile navigation">
                <?php foreach ($pageTitles as $key => $label): ?><a
                        href="index.php?page=<?= escapeHtml($key) ?>"><?= escapeHtml($label) ?></a><?php endforeach; ?>
                <form method="post"><input type="hidden" name="action" value="logout"><input type="hidden"
                        name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit">Logout</button>
                </form>
            </nav>
            <?php if ($flashMessage): ?>
                <div class="alert success" role="status"><?= escapeHtml($flashMessage) ?></div><?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert error" role="alert"><?= escapeHtml($flashError) ?></div><?php endif; ?>
            <?php if ($dbError): ?>
                <div class="alert error" role="alert">Data could not be loaded: <?= escapeHtml($dbError) ?></div>
            <?php endif; ?>

            <?php require __DIR__ . '/pages/' . $page . '.php'; ?>
        </main>
    </div>
    <script>
        window.LibraryApp = <?= json_encode([
            'csrfToken' => $csrfToken,
            'scanEndpoint' => $basePath . '/api/scanner/scan.php',
            'qrLibraryPage' => $page === 'students' || $page === 'qr-codes',
            'scannerPage' => $page === 'scanner'
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="<?= escapeHtml($basePath) ?>/assets/php-app.js" defer></script>
</body>

</html>