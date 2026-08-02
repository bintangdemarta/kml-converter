<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$u = current_user();
$stmt = db()->prepare("SELECT * FROM flight_requests WHERE pilot_id = :pid ORDER BY id DESC");
$stmt->execute([':pid' => $u['id']]);
$rows = $stmt->fetchAll();

$page_title = 'My Flight History';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>My Flight Requests</h2>
    <?php if (!$rows): ?>
        <p class="muted">Belum ada request. <a href="<?= url('/pilot/request-new.php') ?>">Ajukan sekarang</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Callsign</th><th>Route</th><th>Aircraft</th><th>Rules</th><th>Dist (NM)</th><th>Status</th><th>Dibuat</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e($r['callsign']) ?></td>
                        <td><?= e($r['dep_icao']) ?> → <?= e($r['arr_icao']) ?></td>
                        <td><?= e($r['aircraft']) ?></td>
                        <td><?= e($r['flight_rules']) ?></td>
                        <td><?= number_format((float)$r['distance_nm'], 1) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="muted" style="font-size:12px;"><?= e($r['created_at']) ?></td>
                        <td><a href="<?= url('/pilot/request-view.php?id=' . (int)$r['id']) ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
