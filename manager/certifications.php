<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

$pdo = db();
$errors = [];
$old = ['code' => '', 'name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('_action') === 'delete') {
        $id = (int)post('id');
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM checkride_requests WHERE certification_id = :id');
        $countStmt->execute([':id' => $id]);
        $count = (int)$countStmt->fetchColumn();
        if ($count > 0) {
            flash('Tidak bisa hapus: sudah dipakai di ' . $count . ' check ride request.', 'error');
        } else {
            $pdo->prepare('DELETE FROM certifications WHERE id = :id')->execute([':id' => $id]);
            flash('Sertifikasi dihapus.', 'success');
        }
        redirect('/manager/certifications.php');
    }

    foreach ($old as $k => $_) {
        $old[$k] = post($k, $old[$k]);
    }
    $old['code'] = strtoupper(str_replace(' ', '_', $old['code']));

    if ($old['code'] === '') {
        $errors[] = 'Kode wajib diisi (mis. BASIC, TYPE_A320).';
    }
    if ($old['name'] === '') {
        $errors[] = 'Nama sertifikasi wajib diisi.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO certifications (code, name, description, created_at) VALUES (:c, :n, :d, :ts)'
            );
            $stmt->execute([
                ':c'  => $old['code'],
                ':n'  => $old['name'],
                ':d'  => $old['description'] !== '' ? $old['description'] : null,
                ':ts' => now_iso(),
            ]);
            flash('Sertifikasi "' . $old['name'] . '" ditambahkan.', 'success');
            redirect('/manager/certifications.php');
        } catch (PDOException $ex) {
            if (str_contains($ex->getMessage(), 'UNIQUE')) {
                $errors[] = 'Kode "' . $old['code'] . '" sudah dipakai.';
            } else {
                throw $ex;
            }
        }
    }
}

$certs = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM checkride_requests cr WHERE cr.certification_id = c.id) AS usage_count
     FROM certifications c ORDER BY c.name"
)->fetchAll();

$page_title = 'Kelola Sertifikasi';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Jenis Sertifikasi Training Academy</h2>

    <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <table>
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Deskripsi</th><th>Dipakai</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            <?php foreach ($certs as $c): ?>
                <tr>
                    <td><code><?= e($c['code']) ?></code></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="muted"><?= e($c['description'] ?? '') ?></td>
                    <td><?= (int)$c['usage_count'] ?></td>
                    <td>
                        <?php if ((int)$c['usage_count'] === 0): ?>
                            <form method="post" style="display:inline;margin:0;"
                                  onsubmit="return confirm('Hapus sertifikasi ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn-danger" style="margin:0;padding:6px 10px;">Hapus</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" style="max-width:520px;">
    <h2>Tambah Sertifikasi</h2>
    <form method="post">
        <?= csrf_field() ?>
        <label>Kode <span class="muted">(unik, huruf besar, mis. TYPE_A320)</span></label>
        <input name="code" value="<?= e($old['code']) ?>" placeholder="TYPE_A320" required>

        <label>Nama</label>
        <input name="name" value="<?= e($old['name']) ?>" placeholder="Type Rating A320" required>

        <label>Deskripsi <span class="muted">(opsional)</span></label>
        <textarea name="description" placeholder="Syarat & cakupan check ride..."><?= e($old['description']) ?></textarea>

        <button type="submit">Tambah</button>
    </form>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
