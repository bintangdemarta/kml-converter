<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/indonesia_db.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/database/index.php?cat=reporting_point');
}
csrf_check();

$pdo = db();
$id = (int)post('id');
$ident = strtoupper(post('ident'));
$type = post('type');
$lat = post('lat');
$lon = post('lon');
$frequency = post('frequency');
$region = post('region');
$notes = post('notes');

$errors = [];
if ($ident === '') $errors[] = 'Ident wajib diisi.';
if (!in_array($type, reporting_point_types(), true)) $errors[] = 'Tipe reporting point tidak valid.';
if (!is_numeric($lat) || !is_numeric($lon) || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
    $errors[] = 'Koordinat tidak valid.';
}

if ($errors) {
    foreach ($errors as $err) flash($err, 'error');
    redirect('/database/index.php?cat=reporting_point' . ($id > 0 ? '&edit=' . $id : '&new=1'));
}

$fields = [
    ':ident' => $ident, ':type' => $type,
    ':lat' => round((float)$lat, 7), ':lon' => round((float)$lon, 7),
    ':freq' => $frequency !== '' ? $frequency : null,
    ':region' => $region !== '' ? $region : null,
    ':notes' => $notes !== '' ? $notes : null,
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id FROM reporting_points WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        flash('Reporting point tidak ditemukan.', 'error');
        redirect('/database/index.php?cat=reporting_point');
    }
    $fields[':u'] = now_iso();
    $fields[':id'] = $id;
    $pdo->prepare(
        'UPDATE reporting_points SET ident=:ident, type=:type, lat=:lat, lon=:lon,
         frequency=:freq, region=:region, notes=:notes, updated_at=:u WHERE id=:id'
    )->execute($fields);
    flash('Reporting point "' . $ident . '" diperbarui.', 'success');
} else {
    $fields[':source'] = 'manual';
    $fields[':c'] = now_iso();
    $fields[':u'] = now_iso();
    $pdo->prepare(
        'INSERT INTO reporting_points (ident,type,lat,lon,frequency,region,source,notes,created_at,updated_at)
         VALUES (:ident,:type,:lat,:lon,:freq,:region,:source,:notes,:c,:u)'
    )->execute($fields);
    flash('Reporting point "' . $ident . '" ditambahkan.', 'success');
}

redirect('/database/index.php?cat=reporting_point');
