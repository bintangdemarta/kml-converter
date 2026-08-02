<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/taxiway.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/taxiway/index.php');
}
csrf_check();

$pdo = db();
$id      = (int)post('id'); // 0 = create
$icao    = strtoupper(post('icao'));
$refCode = post('ref_code');
$name    = post('name');
$type    = post('type');
$lat     = post('lat');
$lon     = post('lon');
$notes   = post('notes');

$errors = [];
if (!preg_match('/^[A-Z]{4}$/', $icao)) {
    $errors[] = 'ICAO harus 4 huruf.';
}
if ($name === '') {
    $errors[] = 'Nama node wajib diisi.';
}
if (!in_array($type, taxi_node_types(), true)) {
    $errors[] = 'Tipe node tidak valid.';
}
if (!is_numeric($lat) || !is_numeric($lon)
    || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
    $errors[] = 'Koordinat tidak valid.';
}

if ($errors) {
    foreach ($errors as $err) {
        flash($err, 'error');
    }
    redirect('/taxiway/index.php' . ($icao !== '' ? '?icao=' . $icao : ''));
}

$lat = round((float)$lat, 7);
$lon = round((float)$lon, 7);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM taxi_nodes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('Node tidak ditemukan.', 'error');
        redirect('/taxiway/index.php');
    }

    $pdo->prepare(
        'UPDATE taxi_nodes SET ref_code=:r, name=:n, type=:t, lat=:la, lon=:lo, notes=:no, updated_at=:u WHERE id=:id'
    )->execute([
        ':r' => $refCode !== '' ? $refCode : null, ':n' => $name, ':t' => $type,
        ':la' => $lat, ':lo' => $lon, ':no' => $notes !== '' ? $notes : null,
        ':u' => now_iso(), ':id' => $id,
    ]);

    // Reposisi -> edge yang menempel jadi stale, recompute jaraknya.
    if (abs((float)$existing['lat'] - $lat) > 1e-9 || abs((float)$existing['lon'] - $lon) > 1e-9) {
        taxi_recompute_edges_for_node($pdo, $id);
    }
    flash('Node "' . $name . '" diperbarui.', 'success');
} else {
    // Warning lunak: node lain yang sangat dekat (icao sama) - kemungkinan duplikat.
    $near = $pdo->prepare('SELECT * FROM taxi_nodes WHERE icao = :icao');
    $near->execute([':icao' => $icao]);
    foreach ($near->fetchAll() as $n) {
        $d = taxi_edge_distance_meters(['lat' => $lat, 'lon' => $lon], $n);
        if ($d < 3.0) {
            flash('Catatan: node ini sangat dekat dengan "' . $n['name'] . '" (~' . round($d) . 'm). Pastikan bukan duplikat.', 'info');
            break;
        }
    }

    $pdo->prepare(
        'INSERT INTO taxi_nodes (icao,ref_code,name,type,lat,lon,notes,created_at,updated_at)
         VALUES (:i,:r,:n,:t,:la,:lo,:no,:c,:u)'
    )->execute([
        ':i' => $icao, ':r' => $refCode !== '' ? $refCode : null, ':n' => $name, ':t' => $type,
        ':la' => $lat, ':lo' => $lon, ':no' => $notes !== '' ? $notes : null,
        ':c' => now_iso(), ':u' => now_iso(),
    ]);
    flash('Node "' . $name . '" ditambahkan.', 'success');
}

redirect('/taxiway/index.php?icao=' . $icao);
