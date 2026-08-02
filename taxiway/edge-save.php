<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/taxiway.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/taxiway/index.php');
}
csrf_check();

$pdo = db();
$fromId = (int)post('from_node_id');
$toId   = (int)post('to_node_id');
$taxiwayName = post('taxiway_name');
$bidirectional = isset($_POST['bidirectional']) ? 1 : 0; // checkbox: bukan post()!

$errors = [];
if ($fromId === $toId) {
    $errors[] = 'Edge tidak boleh menghubungkan node ke dirinya sendiri.';
}

$stmt = $pdo->prepare('SELECT * FROM taxi_nodes WHERE id IN (:a, :b)');
$stmt->execute([':a' => $fromId, ':b' => $toId]);
$byId = [];
foreach ($stmt->fetchAll() as $f) {
    $byId[(int)$f['id']] = $f;
}

if (!isset($byId[$fromId]) || !isset($byId[$toId])) {
    $errors[] = 'Salah satu node tidak ditemukan.';
} elseif ($byId[$fromId]['icao'] !== $byId[$toId]['icao']) {
    $errors[] = 'Kedua node harus berada di ICAO yang sama.';
}

if (!$errors) {
    $icao = $byId[$fromId]['icao'];
    $dup = $pdo->prepare(
        'SELECT COUNT(*) FROM taxi_edges WHERE icao = :icao
         AND ((from_node_id = :a AND to_node_id = :b) OR (from_node_id = :b AND to_node_id = :a))'
    );
    $dup->execute([':icao' => $icao, ':a' => $fromId, ':b' => $toId]);
    if ((int)$dup->fetchColumn() > 0) {
        $errors[] = 'Edge antara kedua titik ini sudah ada.';
    }
}

if ($errors) {
    foreach ($errors as $err) {
        flash($err, 'error');
    }
    redirect('/taxiway/index.php' . (isset($byId[$fromId]) ? '?icao=' . $byId[$fromId]['icao'] : ''));
}

$icao = $byId[$fromId]['icao'];
$distance = taxi_edge_distance_meters($byId[$fromId], $byId[$toId]);

$pdo->prepare(
    'INSERT INTO taxi_edges (icao,from_node_id,to_node_id,taxiway_name,bidirectional,distance_m,created_at)
     VALUES (:i,:f,:t,:tn,:b,:d,:c)'
)->execute([
    ':i' => $icao, ':f' => $fromId, ':t' => $toId,
    ':tn' => $taxiwayName !== '' ? $taxiwayName : null,
    ':b' => $bidirectional, ':d' => $distance, ':c' => now_iso(),
]);

flash('Edge ditambahkan (' . $distance . ' m).', 'success');
redirect('/taxiway/index.php?icao=' . $icao);
