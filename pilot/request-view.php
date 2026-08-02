<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$u = current_user();
$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT fr.*, pu.username AS pilot_name, mu.username AS manager_name
     FROM flight_requests fr
     JOIN users pu ON pu.id = fr.pilot_id
     LEFT JOIN users mu ON mu.id = fr.manager_id
     WHERE fr.id = :id"
);
$stmt->execute([':id' => $id]);
$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    flash('Request tidak ditemukan.', 'error');
    redirect('/pilot/history.php');
}

// Hanya pemilik atau manager/admin yang boleh melihat.
$isOwner = (int)$r['pilot_id'] === (int)$u['id'];
if (!$isOwner && !is_manager()) {
    http_response_code(403);
    flash('Akses ditolak.', 'error');
    redirect('/pilot/dashboard.php');
}

$page_title = 'Request #' . $id;
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;">Flight Request #<?= (int)$r['id'] ?></h2>
        <?= status_badge($r['status']) ?>
    </div>

    <div class="stat-grid" style="margin-top:16px;">
        <div class="stat"><div class="k">Callsign</div><div class="v" style="font-size:18px;"><?= e($r['callsign']) ?></div></div>
        <div class="stat"><div class="k">Aircraft</div><div class="v" style="font-size:18px;"><?= e($r['aircraft']) ?></div></div>
        <div class="stat"><div class="k">Route</div><div class="v" style="font-size:18px;"><?= e($r['dep_icao']) ?> → <?= e($r['arr_icao']) ?></div></div>
        <div class="stat"><div class="k">Distance</div><div class="v" style="font-size:18px;"><?= number_format((float)$r['distance_nm'], 1) ?> NM</div></div>
    </div>

    <table style="margin-top:18px;">
        <tr><th>Pilot</th><td><?= e($r['pilot_name']) ?></td></tr>
        <tr><th>Flight Rules</th><td><?= e($r['flight_rules']) ?></td></tr>
        <tr><th>Cruise Alt</th><td><?= $r['cruise_alt'] !== null ? e($r['cruise_alt']) . ' ft' : '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Route (raw)</th><td style="font-family:monospace;font-size:12px;word-break:break-all;"><?= $r['route'] !== '' && $r['route'] !== null ? e($r['route']) : '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Remarks</th><td><?= $r['remarks'] !== '' && $r['remarks'] !== null ? nl2br(e($r['remarks'])) : '<span class="muted">—</span>' ?></td></tr>
        <tr><th>Dibuat</th><td class="muted"><?= e($r['created_at']) ?></td></tr>
        <?php if ($r['manager_name']): ?>
            <tr><th>Reviewed by</th><td><?= e($r['manager_name']) ?> <span class="muted">(<?= e($r['reviewed_at']) ?>)</span></td></tr>
        <?php endif; ?>
        <?php if ($r['review_note']): ?>
            <tr><th>Catatan Manager</th><td><?= nl2br(e($r['review_note'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($r['dispatched_at']): ?>
            <tr><th>Dispatched</th><td class="muted"><?= e($r['dispatched_at']) ?></td></tr>
        <?php endif; ?>
        <?php if ($r['completed_at']): ?>
            <tr><th>Completed</th><td class="muted"><?= e($r['completed_at']) ?></td></tr>
        <?php endif; ?>
    </table>

    <?php // Aksi pilot: Mark as flown (hanya saat dispatched & pemilik) ?>
    <?php if ($isOwner && $r['status'] === 'dispatched'): ?>
        <form method="post" action="<?= url('/pilot/mark-flown.php') ?>" style="margin-top:6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="btn-ok">✈️ Mark as Flown</button>
        </form>
        <p class="muted" style="font-size:13px;">Tekan setelah selesai terbang untuk mencatat logbook & mendapat XP.</p>
    <?php endif; ?>

    <?php // Aksi manager: review / dispatch ?>
    <?php if (is_manager() && in_array($r['status'], ['pending', 'approved'], true)): ?>
        <div style="margin-top:14px;">
            <a class="btn btn-ghost" href="<?= url('/manager/queue.php') ?>">Kelola di Ticket Queue →</a>
        </div>
    <?php endif; ?>

    <p style="margin-top:18px;"><a href="<?= url($isOwner ? '/pilot/history.php' : '/manager/queue.php') ?>">← Kembali</a></p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
