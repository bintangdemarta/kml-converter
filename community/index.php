<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$pdo = db();

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role != 'admin') AS total_pilots,
        (SELECT COUNT(*) FROM logbook) AS total_flights,
        (SELECT COALESCE(SUM(distance_nm),0) FROM logbook) AS total_distance,
        (SELECT COUNT(DISTINCT pilot_id) FROM logbook WHERE filed_at >= datetime('now','-7 days')) AS active_week"
)->fetch();

$upcoming = $pdo->query(
    "SELECT ce.*, u.username AS creator_name,
        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = ce.id) AS rsvp_count
     FROM community_events ce
     JOIN users u ON u.id = ce.created_by
     WHERE ce.start_at >= datetime('now')
     ORDER BY ce.start_at ASC"
)->fetchAll();

$past = $pdo->query(
    "SELECT ce.*, u.username AS creator_name,
        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = ce.id) AS rsvp_count
     FROM community_events ce
     JOIN users u ON u.id = ce.created_by
     WHERE ce.start_at < datetime('now')
     ORDER BY ce.start_at DESC
     LIMIT 10"
)->fetchAll();

$page_title = 'Community & Events';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Statistik Komunitas</h2>
    <div class="stat-grid">
        <div class="stat">
            <div class="k">Total Pilot</div>
            <div class="v"><?= (int)$stats['total_pilots'] ?></div>
        </div>
        <div class="stat">
            <div class="k">Total Flight</div>
            <div class="v"><?= (int)$stats['total_flights'] ?></div>
        </div>
        <div class="stat">
            <div class="k">Total Distance</div>
            <div class="v"><?= number_format((float)$stats['total_distance'], 0) ?> NM</div>
        </div>
        <div class="stat">
            <div class="k">Aktif Minggu Ini</div>
            <div class="v"><?= (int)$stats['active_week'] ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;">Event & Campaign</h2>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-ghost" style="margin:0;" href="<?= url('/community/leaderboard.php') ?>">Leaderboard</a>
            <?php if (is_manager()): ?>
                <a class="btn" style="margin:0;" href="<?= url('/community/event-new.php') ?>">Buat Baru</a>
            <?php endif; ?>
        </div>
    </div>

    <h3>Akan Datang</h3>
    <?php if (!$upcoming): ?>
        <p class="muted">Belum ada event/campaign yang dijadwalkan.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Judul</th><th>Tipe</th><th>Mulai</th><th>Bandara</th><th>RSVP</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($upcoming as $ev): ?>
                    <tr>
                        <td><?= e($ev['title']) ?></td>
                        <td><?= e(ucfirst($ev['type'])) ?></td>
                        <td><?= e($ev['start_at']) ?></td>
                        <td><?= e($ev['airport_icao'] ?? '-') ?></td>
                        <td><?= (int)$ev['rsvp_count'] ?></td>
                        <td><a href="<?= url('/community/event-view.php?id=' . (int)$ev['id']) ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Riwayat</h3>
    <?php if (!$past): ?>
        <p class="muted">Belum ada event/campaign yang selesai.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Judul</th><th>Tipe</th><th>Mulai</th><th>RSVP</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($past as $ev): ?>
                    <tr>
                        <td><?= e($ev['title']) ?></td>
                        <td><?= e(ucfirst($ev['type'])) ?></td>
                        <td><?= e($ev['start_at']) ?></td>
                        <td><?= (int)$ev['rsvp_count'] ?></td>
                        <td><a href="<?= url('/community/event-view.php?id=' . (int)$ev['id']) ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
