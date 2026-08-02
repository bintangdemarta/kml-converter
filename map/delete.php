<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/map/index.php');
}
csrf_check();

$u = current_user();
$pdo = db();
$id = (int)post('id');

$stmt = $pdo->prepare('SELECT * FROM routes WHERE id = :id');
$stmt->execute([':id' => $id]);
$r = $stmt->fetch();

if (!$r) {
    flash('Rute tidak ditemukan.', 'error');
    redirect('/map/index.php');
}
if ((int)$r['owner_id'] !== (int)$u['id'] && !is_manager()) {
    http_response_code(403);
    flash('Kamu tidak punya izin menghapus rute ini.', 'error');
    redirect('/map/index.php?id=' . $id);
}

$pdo->prepare('DELETE FROM routes WHERE id = :id')->execute([':id' => $id]);
flash('Rute dihapus.', 'success');
redirect('/map/index.php');
