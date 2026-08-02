<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/xp.php';
require_login();

$u = current_user();
$pdo = db();

// Ringkasan request milik pilot ini
$counts = $pdo->prepare(
    "SELECT status, COUNT(*) c FROM flight_requests WHERE pilot_id = :pid GROUP BY status"
);
$counts->execute([':pid' => $u['id']]);
$byStatus = [];
foreach ($counts as $row) {
    $byStatus[$row['status']] = (int)$row['c'];
}

$logStats = $pdo->prepare(
    "SELECT COUNT(*) legs, COALESCE(SUM(distance_nm),0) nm FROM logbook WHERE pilot_id = :pid"
);
$logStats->execute([':pid' => $u['id']]);
$log = $logStats->fetch();

// Request terbaru
$recent = $pdo->prepare(
    "SELECT * FROM flight_requests WHERE pilot_id = :pid ORDER BY id DESC LIMIT 5"
);
$recent->execute([':pid' => $u['id']]);
$recent = $recent->fetchAll();

$xp = (int)$u['xp'];
$prog = rank_progress($xp);

$page_title = 'Dashboard';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Halo, <?= e($u['username']) ?> 👋</h2>
    <div class="stat-grid">
        <div class="stat">
            <div class="k">Rank</div>
            <div class="v" style="font-size:18px;"><?= e($prog['current_rank']) ?></div>
        </div>
        <div class="stat">
            <div class="k">XP</div>
            <div class="v"><?= number_format($xp) ?></div>
            <div class="progress"><div style="width:<?= (int)$prog['percent'] ?>%"></div></div>
            <div class="muted" style="font-size:12px;margin-top:6px;">
                <?php if ($prog['next_rank']): ?>
                    <?= (int)$prog['xp_to_next'] ?> XP lagi ke <?= e($prog['next_rank']) ?>
                <?php else: ?>
                    Rank tertinggi tercapai 🏆
                <?php endif; ?>
            </div>
        </div>
        <div class="stat">
            <div class="k">Total Legs</div>
            <div class="v"><?= (int)$log['legs'] ?></div>
        </div>
        <div class="stat">
            <div class="k">Total Distance</div>
            <div class="v"><?= number_format((float)$log['nm'], 0) ?> <span style="font-size:13px;">NM</span></div>
        </div>
    </div>
</div>

<div class="card">
    <h3>Ringkasan Request</h3>
    <div class="stat-grid">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'dispatched' => 'Dispatched', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $st => $label): ?>
            <div class="stat">
                <div class="k"><?= e($label) ?></div>
                <div class="v"><?= (int)($byStatus[$st] ?? 0) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <a class="btn" href="<?= url('/pilot/request-new.php') ?>" style="margin-top:18px;">+ New Flight Request</a>
</div>

<div class="card">
    <h3>Request Terbaru</h3>
    <?php if (!$recent): ?>
        <p class="muted">Belum ada request. <a href="<?= url('/pilot/request-new.php') ?>">Ajukan sekarang</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Route</th><th>Aircraft</th><th>Dist (NM)</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e($r['dep_icao']) ?> → <?= e($r['arr_icao']) ?></td>
                        <td><?= e($r['aircraft']) ?></td>
                        <td><?= number_format((float)$r['distance_nm'], 1) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><a href="<?= url('/pilot/request-view.php?id=' . (int)$r['id']) ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:12px;"><a href="<?= url('/pilot/history.php') ?>">Lihat semua →</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
