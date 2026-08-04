<?php
require_once __DIR__ . '/../lib/auth.php';
require_instructor();

$u = current_user();
$pdo = db();

function load_claimed(PDO $pdo, int $id, int $instructorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT cr.*, pu.username AS pilot_name, c.name AS cert_name
         FROM checkride_requests cr
         JOIN users pu ON pu.id = cr.pilot_id
         JOIN certifications c ON c.id = cr.certification_id
         WHERE cr.id = :id"
    );
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    if (!$r || (int)$r['instructor_id'] !== $instructorId) {
        return null;
    }
    return $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id = (int)post('id');
    $decision = post('decision');
    $note = post('note');
    $r = load_claimed($pdo, $id, (int)$u['id']);

    if (!$r || $r['status'] !== 'claimed') {
        flash('Check ride tidak ditemukan / bukan claim kamu / sudah direview.', 'error');
        redirect('/training/queue.php');
    }
    if (!in_array($decision, ['passed', 'failed'], true)) {
        flash('Keputusan tidak valid.', 'error');
        redirect('/training/review.php?id=' . $id);
    }

    $stmt = $pdo->prepare(
        "UPDATE checkride_requests
         SET status = :st, review_note = :note, reviewed_at = :ts
         WHERE id = :id AND status = 'claimed'"
    );
    $stmt->execute([
        ':st'   => $decision,
        ':note' => $note !== '' ? $note : null,
        ':ts'   => now_iso(),
        ':id'   => $id,
    ]);

    flash('Check ride #' . $id . ' ditandai ' . $decision . '.', 'success');
    redirect('/training/queue.php');
}

$id = (int)($_GET['id'] ?? 0);
$r = load_claimed($pdo, $id, (int)$u['id']);
if (!$r || $r['status'] !== 'claimed') {
    flash('Check ride tidak tersedia untuk direview.', 'error');
    redirect('/training/queue.php');
}

$page_title = 'Review Check Ride #' . $id;
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:560px;margin:0 auto;">
    <h2>Review Check Ride #<?= (int)$r['id'] ?></h2>
    <p class="muted">
        Pilot: <b><?= e($r['pilot_name']) ?></b> &middot;
        Sertifikasi: <b><?= e($r['cert_name']) ?></b> &middot;
        Bandara: <b><?= e($r['airport_icao']) ?></b>
    </p>
    <?php if ($r['notes']): ?>
        <p class="muted">Catatan pilot: <?= nl2br(e($r['notes'])) ?></p>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

        <label>Catatan hasil observasi <span class="muted">(kepatuhan radio, prosedur, dll)</span></label>
        <textarea name="note" placeholder="Contoh: readback jelas, sedikit telat report posisi final..."></textarea>

        <div style="display:flex;gap:10px;">
            <button type="submit" name="decision" value="passed" class="btn-ok">Passed</button>
            <button type="submit" name="decision" value="failed" class="btn-danger">Failed</button>
        </div>
        <a class="btn btn-ghost" href="<?= url('/training/queue.php') ?>">Batal</a>
    </form>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
