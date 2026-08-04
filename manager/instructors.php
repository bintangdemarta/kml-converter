<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    $action = post('action');

    if (in_array($action, ['grant', 'revoke'], true)) {
        $flag = $action === 'grant' ? 1 : 0;
        $pdo->prepare('UPDATE users SET is_instructor = :f WHERE id = :id')
            ->execute([':f' => $flag, ':id' => $id]);
        flash('Instructor status pilot #' . $id . ' diupdate.', 'success');
    }
    redirect('/manager/instructors.php');
}

$rows = $pdo->query(
    "SELECT id, username, role, xp, is_instructor FROM users WHERE role != 'admin' ORDER BY username"
)->fetchAll();

$page_title = 'Kelola Instructor';
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Kelola Instructor Training Academy</h2>
    <p class="muted">Assign atau cabut status instructor untuk pilot. Manager/admin otomatis punya akses instructor tanpa perlu di-assign.</p>

    <table>
        <thead>
            <tr><th>Username</th><th>Role</th><th>XP</th><th>Instructor?</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['username']) ?></td>
                    <td><?= e($r['role']) ?></td>
                    <td><?= (int)$r['xp'] ?></td>
                    <td><?= ((int)$r['is_instructor'] === 1) ? 'Ya' : 'Tidak' ?></td>
                    <td>
                        <form method="post" style="display:inline;margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <?php if ((int)$r['is_instructor'] === 1): ?>
                                <input type="hidden" name="action" value="revoke">
                                <button class="btn-danger" style="margin:0;padding:6px 10px;">Cabut</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="grant">
                                <button class="btn-ok" style="margin:0;padding:6px 10px;">Assign</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
