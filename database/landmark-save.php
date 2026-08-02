<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/indonesia_db.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/database/index.php?cat=landmark');
}
csrf_check();

$pdo = db();
$id = (int)post('id');
$name = post('name');
$type = post('type');
$lat = post('lat');
$lon = post('lon');
$elevationM = post('elevation_m');
$notes = post('notes');

$errors = [];
if ($name === '') $errors[] = 'Nama landmark wajib diisi.';
if (!in_array($type, landmark_types(), true)) $errors[] = 'Tipe landmark tidak valid.';
if (!is_numeric($lat) || !is_numeric($lon) || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
    $errors[] = 'Koordinat tidak valid.';
}

if ($errors) {
    foreach ($errors as $err) flash($err, 'error');
    redirect('/database/index.php?cat=landmark' . ($id > 0 ? '&edit=' . $id : '&new=1'));
}

$fields = [
    ':n' => $name, ':t' => $type,
    ':la' => round((float)$lat, 6), ':lo' => round((float)$lon, 6),
    ':elev' => $elevationM !== '' && is_numeric($elevationM) ? (int)$elevationM : null,
    ':notes' => $notes !== '' ? $notes : null,
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id FROM landmarks WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        flash('Landmark tidak ditemukan.', 'error');
        redirect('/database/index.php?cat=landmark');
    }
    $fields[':u'] = now_iso();
    $fields[':id'] = $id;
    $pdo->prepare(
        'UPDATE landmarks SET name=:n, type=:t, lat=:la, lon=:lo, elevation_m=:elev, notes=:notes, updated_at=:u WHERE id=:id'
    )->execute($fields);
    flash('Landmark "' . $name . '" diperbarui.', 'success');
} else {
    $fields[':source'] = 'manual';
    $fields[':c'] = now_iso();
    $fields[':u'] = now_iso();
    $pdo->prepare(
        'INSERT INTO landmarks (name,type,lat,lon,elevation_m,source,notes,created_at,updated_at)
         VALUES (:n,:t,:la,:lo,:elev,:source,:notes,:c,:u)'
    )->execute($fields);
    flash('Landmark "' . $name . '" ditambahkan.', 'success');
}

redirect('/database/index.php?cat=landmark');
