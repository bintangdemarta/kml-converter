<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/taxiway/index.php');
}
csrf_check();

$pdo = db();
$id = (int)post('id');

$stmt = $pdo->prepare('SELECT * FROM taxi_edges WHERE id = :id');
$stmt->execute([':id' => $id]);
$e = $stmt->fetch();

if (!$e) {
    flash('Edge tidak ditemukan.', 'error');
    redirect('/taxiway/index.php');
}

$pdo->prepare('DELETE FROM taxi_edges WHERE id = :id')->execute([':id' => $id]);
flash('Edge dihapus.', 'success');
redirect('/taxiway/index.php?icao=' . $e['icao']);
