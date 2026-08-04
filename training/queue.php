<?php
require_once __DIR__ . '/../lib/auth.php';
require_instructor();

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');

    $stmt = $pdo->prepare(
        "UPDATE checkride_requests
         SET status = 'claimed', instructor_id = :iid, claimed_at = :ts
         WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute([':iid' => $u['id'], ':ts' => now_iso(), ':id' => $id]);

    if ($stmt->rowCount() === 1) {
        flash('Check ride #' . $id . ' di-claim. Silakan review setelah observasi selesai.', 'success');
    } else {
        flash('Request sudah di-claim instructor lain atau tidak valid.', 'error');
    }
    redirect('/training/queue.php');
}

$filter = $_GET['status'] ?? 'active';
$where = '';
switch ($filter) {
    case 'pending': $where = "WHERE cr.status = 'pending'"; break;
    case 'mine':    $where = "WHERE cr.status = 'claimed' AND cr.instructor_id = " . (int)$u['id']; break;
    case 'all':     $where = ''; break;
    case 'active':
    default:
        $where = "WHERE cr.status IN ('pending','claimed')";
        break;
}

$rows = $pdo->query(
    "SELECT cr.*, pu.username AS pilot_name, c.name AS cert_name, iu.username AS instructor_name
     FROM checkride_requests cr
     JOIN users pu ON pu.id = cr.pilot_id
     JOIN certifications c ON c.id = cr.certification_id
     LEFT JOIN users iu ON iu.id = cr.instructor_id
     $where
     ORDER BY CASE cr.status
        WHEN 'pending' THEN 0 WHEN 'claimed' THEN 1 ELSE 2 END, cr.id DESC"
)->fetchAll();

$tabs = ['active' => 'Perlu Tindakan', 'pending' => 'Pending', 'mine' => 'Claim Saya', 'all' => 'Semua'];

$page_title = 'Instructor Queue';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Instructor Queue</h2>
    <p class="muted">Claim check ride request, lakukan observasi ground di bandara terkait, lalu review hasilnya.</p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="btn <?= $filter === $key ? '' : 'btn-ghost' ?>" style="margin-top:0;"
               href="<?= url('/training/queue.php?status=' . $key) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
        <p class="muted">Tidak ada check ride request pada filter ini.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Pilot</th><th>Sertifikasi</th><th>Bandara</th><th>Instructor</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e($r['pilot_name']) ?></td>
                        <td><?= e($r['cert_name']) ?></td>
                        <td><?= e($r['airport_icao']) ?></td>
                        <td><?= e($r['instructor_name'] ?? '-') ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" style="display:inline;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn-ok" style="margin:0;padding:6px 10px;">Claim</button>
                                </form>
                            <?php elseif ($r['status'] === 'claimed' && (int)$r['instructor_id'] === (int)$u['id']): ?>
                                <a class="btn" style="margin:0;padding:6px 10px;"
                                   href="<?= url('/training/review.php?id=' . (int)$r['id']) ?>">Review</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
