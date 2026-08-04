<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$u = current_user();
$pdo = db();

$certs = $pdo->query('SELECT id, code, name FROM certifications ORDER BY name')->fetchAll();

$errors = [];
$old = ['certification_id' => '', 'airport_icao' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($old as $k => $_) {
        $old[$k] = post($k, $old[$k]);
    }
    $old['airport_icao'] = strtoupper($old['airport_icao']);

    if ($old['certification_id'] === '' || !ctype_digit($old['certification_id'])) {
        $errors[] = 'Pilih sertifikasi yang mau diambil.';
    }
    if ($old['airport_icao'] === '') {
        $errors[] = 'Bandara lokasi check ride wajib diisi.';
    } elseif (!preg_match('/^[A-Z]{4}$/', $old['airport_icao'])) {
        $errors[] = 'Kode bandara harus ICAO 4 huruf (mis. WIII).';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO checkride_requests
             (pilot_id, certification_id, airport_icao, notes, status, created_at)
             VALUES (:pid, :cid, :ap, :n, :st, :ts)'
        );
        $stmt->execute([
            ':pid' => $u['id'],
            ':cid' => (int)$old['certification_id'],
            ':ap'  => $old['airport_icao'],
            ':n'   => $old['notes'] !== '' ? $old['notes'] : null,
            ':st'  => 'pending',
            ':ts'  => now_iso(),
        ]);
        flash('Check ride request diajukan. Menunggu instructor claim.', 'success');
        redirect('/training/my.php');
    }
}

$page_title = 'Request Check Ride';
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:560px;margin:0 auto;">
    <h2>Request Check Ride</h2>
    <p class="muted">Check ride dilakukan di darat: instructor mengamati dari ground dan memantau radio lokal bandara yang kamu pilih. Bukan terbang bareng.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if (!$certs): ?>
        <p class="muted">Belum ada jenis sertifikasi yang dibuat manager. Hubungi manager dulu.</p>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>

            <label>Sertifikasi</label>
            <select name="certification_id" required>
                <option value="">-- Pilih sertifikasi --</option>
                <?php foreach ($certs as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $old['certification_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?> (<?= e($c['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Bandara lokasi check ride (ICAO)</label>
            <input name="airport_icao" value="<?= e($old['airport_icao']) ?>" placeholder="WIII" maxlength="4" style="text-transform:uppercase" required>

            <label>Catatan untuk instructor <span class="muted">(opsional: jam sesi, frekuensi radio, dll)</span></label>
            <textarea name="notes" placeholder="Contoh: rencana pattern work jam 20:00 WIB, freq tower 118.1"><?= e($old['notes']) ?></textarea>

            <button type="submit">Ajukan Check Ride</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
