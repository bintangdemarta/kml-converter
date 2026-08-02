<?php
/*
|--------------------------------------------------------------------------
| Auth — session, login state, role guard, CSRF
|--------------------------------------------------------------------------
| Fondasi yang dipakai ulang seluruh modul (Pilot Profile, Logbook, dll.).
*/

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

function auth_boot(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/** Registrasi user baru. Return [ok, error]. */
function register_user(string $username, string $email, string $password, string $role = 'pilot'): array
{
    $username = trim($username);
    $email = trim($email);

    if ($username === '' || $email === '' || $password === '') {
        return [false, 'Semua field wajib diisi.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Format email tidak valid.'];
    }
    if (strlen($password) < 6) {
        return [false, 'Password minimal 6 karakter.'];
    }

    $pdo = db();

    // User pertama otomatis jadi admin (bootstrap platform).
    $isFirst = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    if ($isFirst) {
        $role = 'admin';
    }
    if (!in_array($role, ['pilot', 'manager', 'admin'], true)) {
        $role = 'pilot';
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, xp, created_at)
             VALUES (:u, :e, :p, :r, 0, :c)'
        );
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':r' => $role,
            ':c' => now_iso(),
        ]);
    } catch (PDOException $ex) {
        if ((int)$ex->getCode() === 23000 || str_contains($ex->getMessage(), 'UNIQUE')) {
            return [false, 'Username atau email sudah terdaftar.'];
        }
        throw $ex;
    }

    return [true, null];
}

/** Verifikasi kredensial & buat session. Return [ok, error]. */
function login(string $identifier, string $password): array
{
    $identifier = trim($identifier);
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :id OR email = :id LIMIT 1');
    $stmt->execute([':id' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return [false, 'Username/email atau password salah.'];
    }

    auth_boot();
    session_regenerate_id(true); // cegah session fixation
    $_SESSION['user_id'] = (int)$user['id'];

    return [true, null];
}

function logout(): void
{
    auth_boot();
    $_SESSION = [];
    session_destroy();
}

/** User yang sedang login (array) atau null. Di-cache per request. */
function current_user(): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) {
        return $user;
    }
    $cached = true;

    auth_boot();
    if (empty($_SESSION['user_id'])) {
        return $user = null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Manager & admin dianggap punya kewenangan moderasi. */
function is_manager(): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], ['manager', 'admin'], true);
}

/** Wajib login, kalau tidak redirect ke halaman login. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('Silakan login terlebih dahulu.', 'error');
        redirect('/auth/login.php');
    }
}

/** Wajib salah satu role tertentu. */
function require_role(string ...$roles): void
{
    require_login();
    $u = current_user();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        flash('Akses ditolak: kamu tidak punya izin untuk halaman itu.', 'error');
        redirect('/pilot/dashboard.php');
    }
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    auth_boot();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Hidden input untuk disematkan di form. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Validasi token; kalau gagal, hentikan request. */
function csrf_check(): void
{
    auth_boot();
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
        http_response_code(419);
        exit('Invalid CSRF token. Muat ulang halaman dan coba lagi.');
    }
}
