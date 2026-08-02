<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$u = current_user();
$pdo = db();

// Manager/admin bisa lihat logbook semua pilot; pilot hanya miliknya.
$scope = $_GET['scope'] ?? 'me';
$canSeeAll = is_manager();
if ($scope === 'all' && $canSeeAll) {
    $rows = $pdo->query(
        "SELECT lb.*, pu.username AS pilot_name
         FROM logbook lb JOIN users pu ON pu.id = lb.pilot_id
         ORDER BY lb.id DESC"
    )->fetchAll();
    $totals = $pdo->query("SELECT COUNT(*) legs, COALESCE(SUM(distance_nm),0) nm, COALESCE(SUM(xp_awarded),0) xp FROM logbook")->fetch();
} else {
    $scope = 'me';
    $stmt = $pdo->prepare(
        "SELECT lb.*, :name AS pilot_name FROM logbook lb WHERE lb.pilot_id = :pid ORDER BY lb.id DESC"
    );
    $stmt->execute([':name' => $u['username'], ':pid' => $u['id']]);
    $rows = $stmt->fetchAll();
    $tt = $pdo->prepare("SELECT COUNT(*) legs, COALESCE(SUM(distance_nm),0) nm, COALESCE(SUM(xp_awarded),0) xp FROM logbook WHERE pilot_id = :pid");
    $tt->execute([':pid' => $u['id']]);
    $totals = $tt->fetch();
}

$page_title = 'Logbook';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;">Logbook</h2>
        <?php if ($canSeeAll): ?>
            <div style="display:flex;gap:8px;">
                <a class="btn <?= $scope === 'me' ? '' : 'btn-ghost' ?>" style="margin:0;" href="<?= url('/logbook/index.php?scope=me') ?>">Saya</a>
                <a class="btn <?= $scope === 'all' ? '' : 'btn-ghost' ?>" style="margin:0;" href="<?= url('/logbook/index.php?scope=all') ?>">Semua Pilot</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="stat-grid" style="margin-top:16px;">
        <div class="stat"><div class="k">Total Legs</div><div class="v"><?= (int)$totals['legs'] ?></div></div>
        <div class="stat"><div class="k">Total Distance</div><div class="v"><?= number_format((float)$totals['nm'], 0) ?> <span style="font-size:13px;">NM</span></div></div>
        <div class="stat"><div class="k">Total XP</div><div class="v"><?= number_format((int)$totals['xp']) ?></div></div>
    </div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <p class="muted">Belum ada entri logbook. Selesaikan flight yang sudah di-dispatch untuk mengisi logbook.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($scope === 'all'): ?><th>Pilot</th><?php endif; ?>
                    <th>Route</th><th>Aircraft</th><th>Dist (NM)</th><th>XP</th><th>Filed</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $lb): ?>
                    <tr>
                        <td><?= (int)$lb['id'] ?></td>
                        <?php if ($scope === 'all'): ?><td><?= e($lb['pilot_name']) ?></td><?php endif; ?>
                        <td><?= e($lb['dep_icao']) ?> → <?= e($lb['arr_icao']) ?></td>
                        <td><?= e($lb['aircraft']) ?></td>
                        <td><?= number_format((float)$lb['distance_nm'], 1) ?></td>
                        <td>+<?= (int)$lb['xp_awarded'] ?></td>
                        <td class="muted" style="font-size:12px;"><?= e($lb['filed_at']) ?></td>
                        <td><a href="<?= url('/pilot/request-view.php?id=' . (int)$lb['request_id']) ?>">Flight</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
