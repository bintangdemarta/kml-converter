<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/taxiway/index.php');
}
csrf_check();

$pdo = db();
$id = (int)post('id');

$stmt = $pdo->prepare('SELECT * FROM taxi_nodes WHERE id = :id');
$stmt->execute([':id' => $id]);
$n = $stmt->fetch();

if (!$n) {
    flash('Node tidak ditemukan.', 'error');
    redirect('/taxiway/index.php');
}

$pdo->prepare('DELETE FROM taxi_nodes WHERE id = :id')->execute([':id' => $id]);
// ON DELETE CASCADE di taxi_edges otomatis membersihkan edge yang menempel.

flash('Node "' . $n['name'] . '" dihapus (beserta edge yang terhubung).', 'success');
redirect('/taxiway/index.php?icao=' . $n['icao']);
