<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

$u = current_user();
$pdo = db();
$errors = [];
$old = ['type' => 'event', 'title' => '', 'description' => '', 'airport_icao' => '', 'start_at' => '', 'end_at' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($old as $k => $_) {
        $old[$k] = post($k, $old[$k]);
    }
    $old['airport_icao'] = strtoupper($old['airport_icao']);
    if (!in_array($old['type'], ['event', 'campaign'], true)) {
        $old['type'] = 'event';
    }

    if ($old['title'] === '') {
        $errors[] = 'Judul wajib diisi.';
    }
    if ($old['start_at'] === '') {
        $errors[] = 'Tanggal/waktu mulai wajib diisi.';
    }
    if ($old['airport_icao'] !== '' && !preg_match('/^[A-Z]{4}$/', $old['airport_icao'])) {
        $errors[] = 'Kode bandara harus ICAO 4 huruf (mis. WIII).';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO community_events
             (type, title, description, airport_icao, start_at, end_at, created_by, created_at)
             VALUES (:t, :ti, :d, :ap, :sa, :ea, :cb, :ts)'
        );
        $stmt->execute([
            ':t'  => $old['type'],
            ':ti' => $old['title'],
            ':d'  => $old['description'] !== '' ? $old['description'] : null,
            ':ap' => $old['airport_icao'] !== '' ? $old['airport_icao'] : null,
            ':sa' => $old['start_at'],
            ':ea' => $old['end_at'] !== '' ? $old['end_at'] : null,
            ':cb' => $u['id'],
            ':ts' => now_iso(),
        ]);
        $id = (int)$pdo->lastInsertId();
        flash(ucfirst($old['type']) . ' "' . $old['title'] . '" dibuat.', 'success');
        redirect('/community/event-view.php?id=' . $id);
    }
}

$page_title = 'Buat Event/Campaign';
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:560px;margin:0 auto;">
    <h2>Buat Event/Campaign Baru</h2>

    <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>

        <label>Tipe</label>
        <select name="type">
            <option value="event" <?= $old['type'] === 'event' ? 'selected' : '' ?>>Event (one-time gathering)</option>
            <option value="campaign" <?= $old['type'] === 'campaign' ? 'selected' : '' ?>>Campaign (challenge berdurasi)</option>
        </select>

        <label>Judul</label>
        <input name="title" value="<?= e($old['title']) ?>" placeholder="Fly-in WIII Weekend" required>

        <label>Deskripsi <span class="muted">(opsional)</span></label>
        <textarea name="description" placeholder="Detail acara, aturan campaign, dll..."><?= e($old['description']) ?></textarea>

        <div class="row">
            <div>
                <label>Mulai</label>
                <input type="datetime-local" name="start_at" value="<?= e($old['start_at']) ?>" required>
            </div>
            <div>
                <label>Selesai <span class="muted">(opsional, untuk campaign)</span></label>
                <input type="datetime-local" name="end_at" value="<?= e($old['end_at']) ?>">
            </div>
        </div>

        <label>Bandara <span class="muted">(opsional, untuk fly-in di bandara tertentu)</span></label>
        <input name="airport_icao" value="<?= e($old['airport_icao']) ?>" placeholder="WIII" maxlength="4" style="text-transform:uppercase">

        <button type="submit">Buat</button>
    </form>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
