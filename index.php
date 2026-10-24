<?php
require_once __DIR__ . '/api/config.php';

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
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Administrator Login | SSU Library</title>
      <link rel="stylesheet" href="<?= escapeHtml($basePath) ?>/assets/php-app.css">
    </head>
    <body class="login-page">
      <main class="login-card">
        <section class="login-brand">
          <p class="eyebrow">Samar State University</p>
          <h1>Library Access Portal</h1>
          <p>Secure student entry, QR verification, and visitor logging for the library.</p>
          <div class="login-note"><strong>Quick verification</strong><br>Scan a student QR code to validate the account, log the visit, and prevent duplicate entry attempts.</div>
        </section>
        <section class="login-form">
          <p class="eyebrow">Administrator Login</p>
          <h2>Welcome back</h2>
          <?php if ($loginError): ?><div class="alert error" role="alert"><?= escapeHtml($loginError) ?></div><?php endif; ?>
          <form method="post" action="index.php" class="stack">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <label>Email address<input type="email" name="email" autocomplete="username" required></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button primary full" type="submit">Sign in</button>
          </form>
        </section>
      </main>
    </body>
    </html>
    <?php
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
  <?php if ($page === 'dashboard'): ?><meta http-equiv="refresh" content="15"><?php endif; ?>
  <title><?= escapeHtml($pageTitles[$page]) ?> | SSU Library</title>
  <link rel="stylesheet" href="<?= escapeHtml($basePath) ?>/assets/php-app.css">
  <?php if ($page === 'students' || $page === 'qr-codes'): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
  <?php endif; ?>
  <?php if ($page === 'scanner'): ?>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js" defer></script>
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
          <span aria-hidden="true"><?= ['dashboard' => '▦', 'students' => '♙', 'qr-codes' => '▦', 'scanner' => '▣', 'logs' => '◷', 'reports' => '▥', 'settings' => '⚙'][$key] ?></span><?= escapeHtml($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <form method="post" class="logout-form">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
      <button class="nav-link logout-link" type="submit"><span aria-hidden="true">↪</span>Logout</button>
    </form>
    <div class="sidebar-user"><strong><?= escapeHtml($admin['full_name']) ?></strong><span><?= escapeHtml($admin['email']) ?></span></div>
  </aside>
  <main class="main-content">
    <header class="mobile-header">
      <a class="brand" href="index.php?page=dashboard"><span class="brand-mark">SSU</span><span><small>Library</small><strong>QR Entry</strong></span></a>
      <button class="button secondary mobile-menu-button" type="button" onclick="document.body.classList.toggle('nav-open')">Menu</button>
    </header>
    <nav class="mobile-nav" aria-label="Mobile navigation">
      <?php foreach ($pageTitles as $key => $label): ?><a href="index.php?page=<?= escapeHtml($key) ?>"><?= escapeHtml($label) ?></a><?php endforeach; ?>
      <form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit">Logout</button></form>
    </nav>
    <?php if ($flashMessage): ?><div class="alert success" role="status"><?= escapeHtml($flashMessage) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert error" role="alert"><?= escapeHtml($flashError) ?></div><?php endif; ?>
    <?php if ($dbError): ?><div class="alert error" role="alert">Data could not be loaded: <?= escapeHtml($dbError) ?></div><?php endif; ?>

    <?php if ($page === 'dashboard' && $dashboard): ?>
      <div class="page-heading"><div><p class="eyebrow">Overview</p><h1>Admin Dashboard</h1></div><a class="button primary" href="index.php?page=students">Manage Students</a></div>
      <p class="muted small">Live database data · Refreshes every 15 seconds · Updated <?= escapeHtml(date('g:i:s A')) ?></p>
      <section class="stats-grid">
        <?php foreach ([
          ['Total Registered Students', $dashboard['metrics']['total_students']],
          ["Today's Library Visits", $dashboard['metrics']['today_visits']],
          ['Active Student Accounts', $dashboard['metrics']['active_student_accounts']],
          ["Today's Study Visits", $dashboard['metrics']['study_visits_today']],
          ["Today's Borrow Book Visits", $dashboard['metrics']['borrow_book_visits_today']]
        ] as [$label, $value]): ?>
          <article class="card stat-card"><p class="muted"><?= escapeHtml($label) ?></p><strong><?= (int) $value ?></strong></article>
        <?php endforeach; ?>
      </section>
      <section class="content-grid dashboard-grid">
        <article class="card"><h2>Visitor Activity Chart</h2><div class="chart">
          <?php foreach ($dashboard['activity'] as $day): ?><div class="chart-day"><span><?= (int) $day['visits'] ?></span><div class="chart-bar" title="<?= escapeHtml($day['date']) ?>: <?= (int) $day['visits'] ?> visits" style="height:<?= max(3, (int) round($day['visits'] / $maxVisits * 100)) ?>%"></div><small><?= escapeHtml($day['label']) ?></small></div><?php endforeach; ?>
        </div></article>
        <article class="card"><h2>Quick Actions</h2><div class="quick-links"><a href="index.php?page=scanner">Scan New Entry <span>→</span></a><a href="index.php?page=students">Manage Students <span>→</span></a><a href="index.php?page=logs">View Visitor Logs <span>→</span></a></div></article>
      </section>
      <section class="content-grid">
        <article class="card"><h2>Recent Library Visits</h2>
          <?php if ($dashboard['recent_visits']): foreach ($dashboard['recent_visits'] as $visit): ?>
            <div class="list-row"><div><strong><?= escapeHtml($visit['full_name']) ?></strong><small><?= escapeHtml($visit['student_number']) ?> · <?= escapeHtml($visit['purpose']) ?></small></div><time><?= escapeHtml($visit['visit_date']) ?> <?= escapeHtml($visit['time_in']) ?></time></div>
          <?php endforeach; else: ?><p class="muted">No visits have been recorded yet.</p><?php endif; ?>
        </article>
        <article class="card"><h2>Latest Student Registrations</h2>
          <?php if ($dashboard['latest_students']): foreach ($dashboard['latest_students'] as $student): ?>
            <div class="list-row"><div><strong><?= escapeHtml($student['full_name']) ?></strong><small><?= escapeHtml($student['student_number']) ?> · <?= escapeHtml($student['program']) ?></small></div><time><?= escapeHtml(date('M j, Y', strtotime($student['created_at']))) ?></time></div>
          <?php endforeach; else: ?><p class="muted">No student records found.</p><?php endif; ?>
        </article>
      </section>

    <?php elseif ($page === 'students'): ?>
      <div class="page-heading"><div><p class="eyebrow">Student records</p><h1>Student Management</h1></div><a class="button primary" href="#student-form">Add New Student</a></div>
      <?php if ($createdStudent): ?>
        <section class="card qr-confirmation"><div class="generated-qr" data-qr-token="<?= escapeHtml($createdStudent['qr_identifier']) ?>"></div><div><strong>Student account created: <?= escapeHtml($createdStudent['full_name']) ?></strong><p class="muted"><?= escapeHtml($createdStudent['student_number']) ?> · QR code is ready to download.</p><button type="button" class="button primary download-qr">Download QR code</button></div><button type="button" class="button secondary dismiss-qr" onclick="this.closest('.qr-confirmation').remove()">Dismiss</button></section>
      <?php endif; ?>
      <section class="card" id="student-form">
        <h2>Add or Edit Student</h2>
        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="create_student"><input type="hidden" name="page" value="students"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
          <?php foreach (['student_number' => 'Student ID number', 'full_name' => 'Full name', 'email' => 'Email address', 'program' => 'Course / program', 'year_level' => 'Year level'] as $field => $label): ?>
            <label><?= escapeHtml($label) ?><input name="<?= escapeHtml($field) ?>" type="<?= $field === 'email' ? 'email' : 'text' ?>" required></label>
          <?php endforeach; ?>
          <div class="form-actions"><button class="button primary" type="submit">Create student and QR code</button></div>
        </form>
      </section>
      <form method="get" class="search-row"><input type="hidden" name="page" value="students"><label>Search students<input name="q" value="<?= escapeHtml($query) ?>" placeholder="Search by ID, name, email, or program"></label><button class="button secondary" type="submit">Search</button></form>
      <section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Student ID</th><th>Full Name</th><th>Email</th><th>Course</th><th>Year</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($students as $student): ?><tr>
          <td><?= escapeHtml($student['student_number']) ?></td><td><?= escapeHtml($student['full_name']) ?></td><td><?= escapeHtml($student['email']) ?></td><td><?= escapeHtml($student['program']) ?></td><td><?= escapeHtml($student['year_level']) ?></td><td><span class="badge <?= $student['account_status'] === 'active' ? 'active' : 'inactive' ?>"><?= escapeHtml($student['account_status']) ?></span></td>
          <td><div class="action-row"><a href="index.php?page=students&view=<?= (int) $student['id'] ?>">View</a><button type="button" class="link-button edit-student" data-id="<?= (int) $student['id'] ?>" data-student="<?= escapeHtml(json_encode($student, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">Edit</button>
            <form method="post" class="confirm-action" data-confirm="<?= escapeHtml(($student['account_status'] === 'active' ? 'Deactivate ' : 'Activate ') . $student['full_name'] . '?' . ($student['account_status'] === 'active' ? ' Their visit history will be kept.' : ' Generate a new QR code before they check in.')) ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="page" value="students"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><input type="hidden" name="account_status" value="<?= $student['account_status'] === 'active' ? 'inactive' : 'active' ?>"><button class="link-button <?= $student['account_status'] === 'active' ? 'danger-text' : '' ?>" type="submit"><?= $student['account_status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
            <form method="post" class="confirm-action" data-confirm="<?= escapeHtml('Permanently delete ' . $student['full_name'] . ' (' . $student['student_number'] . '), their QR code, and all visit history? This cannot be undone.') ?>"><input type="hidden" name="action" value="delete_student"><input type="hidden" name="page" value="students"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button class="link-button danger-text" type="submit">Delete</button></form>
          </div></td>
        </tr><?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="7" class="empty-state">No student records<?= $query !== '' ? ' match your search' : '' ?>.</td></tr><?php endif; ?>
      </tbody></table></div></section>
      <?php if ($studentProfile): ?>
        <section class="card profile-card"><div class="page-heading"><div><p class="eyebrow">Student profile</p><h2><?= escapeHtml($studentProfile['full_name']) ?></h2></div><a class="button secondary" href="index.php?page=students">Close</a></div>
          <dl class="profile-grid"><?php foreach (['Student ID' => $studentProfile['student_number'], 'Email' => $studentProfile['email'], 'Program' => $studentProfile['program'], 'Year level' => $studentProfile['year_level'], 'Account status' => $studentProfile['account_status'], 'QR status' => $studentProfile['qr_status'] ?: 'Not generated'] as $label => $value): ?><div><dt><?= escapeHtml($label) ?></dt><dd><?= escapeHtml($value) ?></dd></div><?php endforeach; ?></dl>
          <h3>Visit history</h3><?php if ($studentProfile['visit_history']): ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Time</th><th>Purpose</th></tr></thead><tbody><?php foreach ($studentProfile['visit_history'] as $visit): ?><tr><td><?= escapeHtml($visit['visit_date']) ?></td><td><?= escapeHtml($visit['time_in']) ?></td><td><?= escapeHtml($visit['purpose']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="muted">No visit history.</p><?php endif; ?>
        </section>
      <?php endif; ?>

    <?php elseif ($page === 'qr-codes'): ?>
      <div class="page-heading"><div><p class="eyebrow">Student credentials</p><h1>QR Code Management</h1></div></div>
      <p class="muted">Generate a secure QR code for an active student. A regenerated code immediately revokes the previous code.</p>
      <?php if ($generatedQr): ?><section class="card qr-confirmation"><div class="generated-qr" data-qr-token="<?= escapeHtml($generatedQr['qr_identifier']) ?>"></div><div><strong><?= escapeHtml($generatedQr['full_name']) ?></strong><p class="muted"><?= escapeHtml($generatedQr['student_number']) ?> · Save or print this QR code now.</p><button type="button" class="button primary download-qr">Download PNG</button></div></section><?php endif; ?>
      <section class="student-cards">
        <?php $activeCount = 0; foreach ($students as $student): if ($student['account_status'] !== 'active') continue; $activeCount++; ?>
          <article class="card student-qr-card"><div class="qr-placeholder" aria-hidden="true">▦</div><h2><?= escapeHtml($student['full_name']) ?></h2><p class="muted"><?= escapeHtml($student['student_number']) ?></p><form method="post" class="confirm-action" data-confirm="<?= escapeHtml('Generate a new QR code for ' . $student['full_name'] . '? The current code will stop working.') ?>"><input type="hidden" name="action" value="generate_qr"><input type="hidden" name="page" value="qr-codes"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button class="button primary" type="submit"><?= $student['qr_status'] === 'active' ? 'Regenerate QR' : 'Generate QR' ?></button></form></article>
        <?php endforeach; ?>
        <?php if (!$activeCount): ?><p class="muted">No active student accounts are available.</p><?php endif; ?>
      </section>

    <?php elseif ($page === 'scanner'): ?>
      <div class="content-grid scanner-grid"><section class="card"><div class="page-heading"><div><p class="eyebrow">Scan station</p><h1>Library Entry Scanner</h1></div></div>
        <div class="scanner-controls"><label>Camera<select id="camera-select" disabled><option>Finding cameras…</option></select></label><label>Purpose<select id="scan-purpose"><option value="STUDY">Study</option><option value="BORROW_BOOK">Borrow book</option></select></label><button class="button secondary" id="restart-camera" type="button">Restart Camera</button></div>
        <div id="qr-reader" class="qr-reader"></div><p id="scan-message" class="scan-message" role="status">Requesting camera access…</p><div id="scan-result" class="scan-result" hidden></div>
      </section><section class="card"><h2>Recent Successful Scans</h2><div id="recent-scans" class="recent-scans"><?php foreach (array_slice($logs, 0, 5) as $visit): ?><div class="recent-scan"><strong><?= escapeHtml($visit['full_name']) ?></strong><span><?= escapeHtml($visit['time_in']) ?> · <?= escapeHtml($visit['purpose']) ?></span></div><?php endforeach; ?></div></section></div>

    <?php elseif ($page === 'logs'): ?>
      <div class="page-heading"><div><p class="eyebrow">Library activity</p><h1>Visitor Logs</h1></div></div>
      <section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Student ID</th><th>Student Name</th><th>Program</th><th>Year Level</th><th>Visit Date</th><th>Time In</th><th>Purpose</th></tr></thead><tbody>
        <?php foreach ($logs as $log): ?><tr><td><?= escapeHtml($log['student_number']) ?></td><td><?= escapeHtml($log['full_name']) ?></td><td><?= escapeHtml($log['program']) ?></td><td><?= escapeHtml($log['year_level']) ?></td><td><?= escapeHtml($log['visit_date']) ?></td><td><?= escapeHtml($log['time_in']) ?></td><td><?= escapeHtml($log['purpose']) ?></td></tr><?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="7" class="empty-state">No visitor logs have been recorded.</td></tr><?php endif; ?>
      </tbody></table></div></section>

    <?php elseif ($page === 'reports'): ?>
      <div class="page-heading"><div><p class="eyebrow">Library activity</p><h1>Reports and Analytics</h1></div></div><section class="card"><p class="muted">Daily, monthly, and quarterly visitor statistics will appear here.</p></section>

    <?php elseif ($page === 'settings'): ?>
      <div class="page-heading"><div><p class="eyebrow">System configuration</p><h1>Settings</h1></div></div><section class="card"><p class="muted">Duplicate check-in prevention is enforced once per student per day. Visit purpose can be selected at the scanner.</p></section>
    <?php endif; ?>
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
