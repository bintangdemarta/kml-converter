<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/xp.php';
require_login();

$pdo = db();

$rows = $pdo->query(
    "SELECT u.id, u.username, u.xp,
        (SELECT COUNT(*) FROM logbook lb WHERE lb.pilot_id = u.id) AS flight_count,
        (SELECT COALESCE(SUM(lb.distance_nm),0) FROM logbook lb WHERE lb.pilot_id = u.id) AS total_distance
     FROM users u
     WHERE u.role != 'admin'
     ORDER BY u.xp DESC, total_distance DESC"
)->fetchAll();

$page_title = 'Leaderboard';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Leaderboard (All Time)</h2>
    <p class="muted">Diurutkan berdasarkan total XP.</p>

    <?php if (!$rows): ?>
        <p class="muted">Belum ada data pilot.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Pilot</th><th>Rank</th><th>XP</th><th>Flights</th><th>Distance (NM)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['username']) ?></td>
                        <td><?= e(rank_for_xp((int)$r['xp'])) ?></td>
                        <td><?= (int)$r['xp'] ?></td>
                        <td><?= (int)$r['flight_count'] ?></td>
                        <td><?= number_format((float)$r['total_distance'], 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
