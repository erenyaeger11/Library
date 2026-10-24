<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

define('APP_ENV', $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development');

/**
 * Reads an environment variable with an optional fallback value.
 *
 * @param string $name Environment variable name.
 * @param string $default Value returned when the variable is unset.
 * @return string Resolved environment variable value.
 */
function appEnv(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    return isset($_ENV[$name]) ? (string) $_ENV[$name] : $default;
}

/** Returns the MySQL connection settings from environment variables. */
function getDbConfig(): array
{
    return [
        'host' => appEnv('DB_HOST', '127.0.0.1'),
        'port' => appEnv('DB_PORT', '3306'),
        'dbname' => appEnv('DB_NAME', 'ssu_library_qr'),
        'user' => appEnv('DB_USER', 'root'),
        'pass' => appEnv('DB_PASS'),
        'charset' => 'utf8mb4'
    ];
}

/** Opens and reuses the PDO connection to the configured MySQL database. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = getDbConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    return $pdo;
}

/**
 * Sends a JSON response and ends the request.
 *
 * @param array $payload Response data to encode.
 * @param int $status HTTP status code.
 */
function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Requires a logged-in administrator and returns the session identity. */
function requireAuth(): array
{
    startSession();

    if (empty($_SESSION['admin'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    return $_SESSION['admin'];
}

/** Starts a secure PHP session if the request has not started one already. */
function startSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/** Decodes a JSON request body into an associative array. */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Trims a string and removes HTML tags before storing user-provided text. */
function sanitizeString(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    return trim(strip_tags($value));
}

/** Creates a cryptographically random QR identifier for a student. */
function generateQrIdentifier(): string
{
    return bin2hex(random_bytes(32));
}

/** Produces the database hash used to look up a QR identifier securely. */
function hashQrIdentifier(string $identifier): string
{
    return hash_hmac('sha256', $identifier, appEnv('QR_HASH_SECRET', 'ssu-library-qr-secret'));
}
