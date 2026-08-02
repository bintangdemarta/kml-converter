<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

$u = current_user();
$pdo = db();

/** Ambil request pending; kalau tidak valid, kembali ke queue. */
function load_pending(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM flight_requests WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

// --- POST: terapkan keputusan approve/reject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id = (int)post('id');
    $decision = post('decision');
    $note = post('note');
    $r = load_pending($pdo, $id);

    if (!$r) {
        flash('Request tidak ditemukan.', 'error');
        redirect('/manager/queue.php');
    }
    if ($r['status'] !== 'pending') {
        flash('Request #' . $id . ' sudah diproses (status: ' . $r['status'] . ').', 'error');
        redirect('/manager/queue.php');
    }
    if (!in_array($decision, ['approve', 'reject'], true)) {
        flash('Keputusan tidak valid.', 'error');
        redirect('/manager/queue.php');
    }

    $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare(
        "UPDATE flight_requests
         SET status = :st, manager_id = :mid, review_note = :note, reviewed_at = :ts
         WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute([
        ':st'   => $newStatus,
        ':mid'  => $u['id'],
        ':note' => $note !== '' ? $note : null,
        ':ts'   => now_iso(),
        ':id'   => $id,
    ]);

    flash('Request #' . $id . ' ' . $newStatus . '.', 'success');
    redirect('/manager/queue.php');
}

// --- GET ?reject=1: tampilkan form alasan reject ---
$id = (int)($_GET['id'] ?? 0);
$r = load_pending($pdo, $id);
if (!$r || $r['status'] !== 'pending') {
    flash('Request tidak tersedia untuk direview.', 'error');
    redirect('/manager/queue.php');
}

$page_title = 'Reject Request #' . $id;
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:520px;margin:0 auto;">
    <h2>Reject Request #<?= (int)$r['id'] ?></h2>
    <p class="muted"><?= e($r['callsign']) ?> — <?= e($r['dep_icao']) ?> → <?= e($r['arr_icao']) ?> (<?= e($r['aircraft']) ?>)</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="decision" value="reject">
        <label>Alasan penolakan <span class="muted">(opsional, tampil ke pilot)</span></label>
        <textarea name="note" placeholder="Contoh: cruise altitude tidak sesuai untuk arah rute..."></textarea>
        <button type="submit" class="btn-danger">Konfirmasi Reject</button>
        <a class="btn btn-ghost" href="<?= url('/manager/queue.php') ?>">Batal</a>
    </form>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
