<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/indonesia_db.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/database/index.php?cat=airport');
}
csrf_check();

$pdo = db();
$id = (int)post('id');
$name = post('name');
$type = post('type');
$icao = strtoupper(post('icao'));
$iata = strtoupper(post('iata'));
$lat = post('lat');
$lon = post('lon');
$elevationFt = post('elevation_ft');
$municipality = post('municipality');
$province = post('province');
$notes = post('notes');

$errors = [];
if ($name === '') $errors[] = 'Nama airport wajib diisi.';
if (!in_array($type, airport_types(), true)) $errors[] = 'Tipe airport tidak valid.';
if ($icao !== '' && !preg_match('/^[A-Z0-9]{3,4}$/', $icao)) $errors[] = 'ICAO harus 3-4 karakter huruf/angka.';
if (!is_numeric($lat) || !is_numeric($lon) || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
    $errors[] = 'Koordinat tidak valid.';
}

if ($errors) {
    foreach ($errors as $err) flash($err, 'error');
    redirect('/database/index.php?cat=airport' . ($id > 0 ? '&edit=' . $id : '&new=1'));
}

$fields = [
    ':name' => $name, ':type' => $type,
    ':icao' => $icao !== '' ? $icao : null, ':iata' => $iata !== '' ? $iata : null,
    ':lat' => round((float)$lat, 7), ':lon' => round((float)$lon, 7),
    ':elev' => $elevationFt !== '' && is_numeric($elevationFt) ? (int)$elevationFt : null,
    ':muni' => $municipality !== '' ? $municipality : null,
    ':prov' => $province !== '' ? $province : null,
    ':notes' => $notes !== '' ? $notes : null,
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id FROM airports WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        flash('Airport tidak ditemukan.', 'error');
        redirect('/database/index.php?cat=airport');
    }
    $fields[':u'] = now_iso();
    $fields[':id'] = $id;
    $pdo->prepare(
        'UPDATE airports SET name=:name, type=:type, icao=:icao, iata=:iata, lat=:lat, lon=:lon,
         elevation_ft=:elev, municipality=:muni, province=:prov, notes=:notes, updated_at=:u WHERE id=:id'
    )->execute($fields);
    flash('Airport "' . $name . '" diperbarui.', 'success');
} else {
    $fields[':source'] = 'manual';
    $fields[':c'] = now_iso();
    $fields[':u'] = now_iso();
    $pdo->prepare(
        'INSERT INTO airports (name,type,icao,iata,lat,lon,elevation_ft,municipality,province,source,notes,created_at,updated_at)
         VALUES (:name,:type,:icao,:iata,:lat,:lon,:elev,:muni,:prov,:source,:notes,:c,:u)'
    )->execute($fields);
    flash('Airport "' . $name . '" ditambahkan.', 'success');
}

redirect('/database/index.php?cat=airport');
