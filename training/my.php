<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$u = current_user();
$pdo = db();

$requests = $pdo->prepare(
    "SELECT cr.*, c.name AS cert_name, iu.username AS instructor_name
     FROM checkride_requests cr
     JOIN certifications c ON c.id = cr.certification_id
     LEFT JOIN users iu ON iu.id = cr.instructor_id
     WHERE cr.pilot_id = :pid
     ORDER BY cr.id DESC"
);
$requests->execute([':pid' => $u['id']]);
$rows = $requests->fetchAll();

$earned = array_values(array_filter($rows, fn($r) => $r['status'] === 'passed'));

$page_title = 'Training Academy';
require __DIR__ . '/../partials/header.php';
?>
<?php if (is_instructor()): ?>
<div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <span class="muted">Kamu instructor Training Academy.</span>
    <a class="btn" href="<?= url('/training/queue.php') ?>">Buka Instructor Queue</a>
</div>
<?php endif; ?>
<div class="card">
    <h2>Sertifikasi Saya</h2>
    <?php if (!$earned): ?>
        <p class="muted">Belum ada sertifikasi yang lulus. <a href="<?= url('/training/request.php') ?>">Ajukan check ride</a> untuk mulai.</p>
    <?php else: ?>
        <div class="stat-grid">
            <?php foreach ($earned as $c): ?>
                <div class="stat">
                    <div class="k">Certified</div>
                    <div class="v" style="font-size:16px;"><?= e($c['cert_name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;">Riwayat Check Ride</h2>
        <a class="btn" href="<?= url('/training/request.php') ?>">Request Check Ride Baru</a>
    </div>

    <?php if (!$rows): ?>
        <p class="muted">Belum ada request check ride.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Sertifikasi</th><th>Bandara</th><th>Instructor</th><th>Status</th><th>Catatan</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e($r['cert_name']) ?></td>
                        <td><?= e($r['airport_icao']) ?></td>
                        <td><?= e($r['instructor_name'] ?? '-') ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= e($r['review_note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
