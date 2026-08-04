<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

$pdo = db();

// Filter status (default: yang perlu tindakan)
$filter = $_GET['status'] ?? 'active';
$where = '';
$params = [];
switch ($filter) {
    case 'pending':    $where = "WHERE fr.status = 'pending'"; break;
    case 'approved':   $where = "WHERE fr.status = 'approved'"; break;
    case 'dispatched': $where = "WHERE fr.status = 'dispatched'"; break;
    case 'all':        $where = ''; break;
    case 'active':
    default:
        $where = "WHERE fr.status IN ('pending','approved','dispatched')";
        break;
}

$rows = $pdo->query(
    "SELECT fr.*, pu.username AS pilot_name
     FROM flight_requests fr
     JOIN users pu ON pu.id = fr.pilot_id
     $where
     ORDER BY CASE fr.status
        WHEN 'pending' THEN 0 WHEN 'approved' THEN 1
        WHEN 'dispatched' THEN 2 ELSE 3 END, fr.id DESC"
)->fetchAll();

$tabs = ['active' => 'Perlu Tindakan', 'pending' => 'Pending', 'approved' => 'Approved', 'dispatched' => 'Dispatched', 'all' => 'Semua'];

$page_title = 'Ticket Queue';
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <span class="muted">Manager tools:</span>
    <a class="btn btn-ghost" style="margin:0;padding:8px 14px;" href="<?= url('/manager/instructors.php') ?>">Kelola Instructor</a>
    <a class="btn btn-ghost" style="margin:0;padding:8px 14px;" href="<?= url('/manager/certifications.php') ?>">Kelola Sertifikasi</a>
</div>

<div class="card">
    <h2>Ticket Queue</h2>
    <p class="muted">Kelola flight request dari pilot: approve/reject lalu dispatch.</p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="btn <?= $filter === $key ? '' : 'btn-ghost' ?>" style="margin-top:0;"
               href="<?= url('/manager/queue.php?status=' . $key) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
        <p class="muted">Tidak ada ticket pada filter ini.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Pilot</th><th>Callsign</th><th>Route</th><th>AC</th><th>Dist</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e($r['pilot_name']) ?></td>
                        <td><?= e($r['callsign']) ?></td>
                        <td><?= e($r['dep_icao']) ?> - <?= e($r['arr_icao']) ?></td>
                        <td><?= e($r['aircraft']) ?></td>
                        <td><?= number_format((float)$r['distance_nm'], 0) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" action="<?= url('/manager/review.php') ?>" style="display:inline;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="btn-ok" style="margin:0;padding:6px 10px;">Approve</button>
                                </form>
                                <a class="btn btn-danger" style="margin:0;padding:6px 10px;"
                                   href="<?= url('/manager/review.php?id=' . (int)$r['id'] . '&reject=1') ?>">Reject</a>
                            <?php elseif ($r['status'] === 'approved'): ?>
                                <form method="post" action="<?= url('/manager/dispatch.php') ?>" style="display:inline;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button style="margin:0;padding:6px 10px;">Dispatch</button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= url('/pilot/request-view.php?id=' . (int)$r['id']) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
