<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/indonesia_db.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/database/index.php');
}
csrf_check();

$cats = indonesia_categories();
$cat = post('cat');
if (!isset($cats[$cat])) {
    flash('Kategori tidak valid.', 'error');
    redirect('/database/index.php');
}

$table = $cats[$cat]['table']; // dari whitelist internal, bukan input user langsung
$id = (int)post('id');
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash('Data tidak ditemukan.', 'error');
    redirect('/database/index.php?cat=' . $cat);
}

$pdo->prepare("DELETE FROM $table WHERE id = :id")->execute([':id' => $id]);
$label = $row['name'] ?? $row['ident'] ?? ('#' . $id);
flash('"' . $label . '" dihapus.', 'success');
redirect('/database/index.php?cat=' . $cat);
