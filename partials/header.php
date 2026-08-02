<?php
/**
 * Layout header + role-aware nav. Sertakan di awal tiap halaman:
 *   $page_title = '...'; require __DIR__ . '/../partials/header.php';
 */
require_once __DIR__ . '/../lib/auth.php';
auth_boot();

$u = current_user();
$title = isset($page_title) ? $page_title . ' — IFFI FlightOps' : 'IFFI FlightOps';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<style>
    :root {
        --bg: #0f172a;
        --panel: #1e293b;
        --panel-2: #263449;
        --text: #e2e8f0;
        --muted: #94a3b8;
        --accent: #38bdf8;
        --accent-2: #0ea5e9;
        --ok: #22c55e;
        --warn: #f59e0b;
        --danger: #ef4444;
        --border: #334155;
    }
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, Segoe UI, Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        margin: 0;
        line-height: 1.5;
    }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }
    .topbar {
        background: var(--panel);
        border-bottom: 1px solid var(--border);
        padding: 0 20px;
        display: flex;
        align-items: center;
        gap: 18px;
        height: 56px;
        flex-wrap: wrap;
    }
    .brand { font-weight: 700; font-size: 18px; color: #fff; margin-right: 8px; }
    .brand span { color: var(--accent); }
    .topbar nav { display: flex; gap: 14px; flex-wrap: wrap; }
    .topbar nav a { color: var(--muted); font-size: 14px; }
    .topbar nav a:hover { color: var(--text); text-decoration: none; }
    .topbar .spacer { flex: 1; }
    .userchip { font-size: 13px; color: var(--muted); }
    .userchip b { color: var(--text); }
    .role-badge {
        font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
        background: var(--panel-2); color: var(--accent);
        padding: 2px 8px; border-radius: 20px; margin-left: 6px;
    }
    .wrap { max-width: 960px; margin: 28px auto; padding: 0 20px; }
    .card {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 22px;
        margin-bottom: 20px;
    }
    h1, h2, h3 { color: #fff; margin-top: 0; }
    label { display: block; font-size: 13px; color: var(--muted); margin: 12px 0 4px; }
    input, select, textarea {
        width: 100%; padding: 10px; border-radius: 8px;
        border: 1px solid var(--border); background: #0b1220; color: var(--text);
        font-size: 14px; font-family: inherit;
    }
    textarea { min-height: 90px; resize: vertical; }
    .row { display: flex; gap: 14px; flex-wrap: wrap; }
    .row > div { flex: 1; min-width: 140px; }
    button, .btn {
        display: inline-block; padding: 10px 16px; margin-top: 14px;
        border: none; border-radius: 8px; cursor: pointer;
        background: var(--accent-2); color: #06232f; font-weight: 600; font-size: 14px;
    }
    button:hover, .btn:hover { background: var(--accent); text-decoration: none; }
    .btn-ghost { background: var(--panel-2); color: var(--text); }
    .btn-ok { background: var(--ok); color: #052e13; }
    .btn-danger { background: var(--danger); color: #2a0606; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid var(--border); font-size: 14px; }
    th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; }
    .badge {
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        padding: 3px 9px; border-radius: 20px; display: inline-block;
    }
    .badge.pending    { background: #422006; color: #fbbf24; }
    .badge.approved   { background: #052e2b; color: #34d399; }
    .badge.rejected   { background: #450a0a; color: #f87171; }
    .badge.dispatched { background: #082f49; color: #38bdf8; }
    .badge.completed  { background: #1e293b; color: #a5b4fc; }
    .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .flash.info  { background: #0c2a3a; color: #7dd3fc; }
    .flash.error { background: #3b0d0d; color: #fca5a5; }
    .flash.success { background: #052e16; color: #86efac; }
    .muted { color: var(--muted); }
    .stat-grid { display: flex; gap: 14px; flex-wrap: wrap; }
    .stat {
        flex: 1; min-width: 130px; background: var(--panel-2);
        border-radius: 10px; padding: 14px 16px;
    }
    .stat .k { font-size: 12px; color: var(--muted); text-transform: uppercase; }
    .stat .v { font-size: 22px; font-weight: 700; color: #fff; margin-top: 4px; }
    .progress { background: #0b1220; border-radius: 20px; height: 10px; overflow: hidden; margin-top: 8px; }
    .progress > div { height: 100%; background: var(--accent-2); }
</style>
</head>
<body>
<div class="topbar">
    <div class="brand">IFFI <span>FlightOps</span></div>
    <nav>
        <a href="<?= url('/map/index.php') ?>">Map Dashboard</a>
        <?php if ($u): ?>
            <a href="<?= url('/pilot/dashboard.php') ?>">Dashboard</a>
            <a href="<?= url('/pilot/request-new.php') ?>">New Request</a>
            <a href="<?= url('/pilot/history.php') ?>">History</a>
            <a href="<?= url('/logbook/index.php') ?>">Logbook</a>
            <?php if (is_manager()): ?>
                <a href="<?= url('/manager/queue.php') ?>">Ticket Queue</a>
            <?php endif; ?>
            <a href="<?= url('/index.php') ?>">Converter</a>
        <?php endif; ?>
    </nav>
    <div class="spacer"></div>
    <?php if ($u): ?>
        <span class="userchip">
            <b><?= e($u['username']) ?></b>
            <span class="role-badge"><?= e($u['role']) ?></span>
        </span>
        <a class="userchip" href="<?= url('/auth/logout.php') ?>">Logout</a>
    <?php else: ?>
        <a class="userchip" href="<?= url('/auth/login.php') ?>">Login</a>
        <a class="userchip" href="<?= url('/auth/register.php') ?>">Register</a>
    <?php endif; ?>
</div>

<div class="wrap">
<?php foreach (take_flashes() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
