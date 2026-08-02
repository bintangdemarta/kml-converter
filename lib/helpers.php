<?php
/*
|--------------------------------------------------------------------------
| Helpers — utilitas kecil dipakai lintas halaman
|--------------------------------------------------------------------------
*/

/** Escape output untuk cegah XSS. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Base URL path aplikasi (folder tempat app di-host), mis. /kml-converter */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    // Root aplikasi = folder induk dari /lib
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $base = ($docRoot && str_starts_with($appRoot, $docRoot))
        ? substr($appRoot, strlen($docRoot))
        : '';
    return $base = rtrim($base, '/');
}

/** Bangun URL absolut dari path relatif app, mis. url('/auth/login.php'). */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Redirect ke path app lalu stop. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Set flash message (tampil sekali di request berikutnya). */
function flash(string $message, string $type = 'info'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['_flash'][] = ['message' => $message, 'type' => $type];
}

/** Ambil & kosongkan flash messages. */
function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/** Timestamp ISO seragam untuk kolom created_at dll. */
function now_iso(): string
{
    return date('Y-m-d H:i:s');
}

/** Ambil field POST yang sudah di-trim. */
function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}
