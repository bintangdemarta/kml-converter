<?php
/*
|--------------------------------------------------------------------------
| Import Airport — OurAirports.com (Public Domain)
|--------------------------------------------------------------------------
| Sumber: https://ourairports.com/data/ (dataset harian, Public Domain,
| tanpa jaminan akurasi). Baris Indonesia diambil dari
| https://ourairports.com/countries/ID/airports.csv
|
| Jalankan: php data/import-airports.php [path/ke/airports.csv]
|   (tanpa argumen -> download langsung dari URL di atas)
|
| Kolom yang dipakai: ident (-> icao, SELALU terisi, dipakai sbg sumber
| utama), iata_code (-> iata), type, name, latitude_deg, longitude_deg,
| elevation_ft, iso_region (-> province via id_province_name()),
| municipality, gps_code (dicatat di notes kalau beda dari ident).
| Kolom icao_code TIDAK dipakai sbg sumber utama - 63% baris Indonesia
| kosong di kolom ini (diverifikasi manual sebelum menulis script ini),
| kolom ident jauh lebih reliable (0% kosong).
|
| Skip: type='closed' (noise operasional) dan type di luar airport_types()
| (mis. balloonport - tidak ada di dataset Indonesia saat ini, tapi
| dijaga untuk masa depan).
|
| Idempotent: row source='ourairports' di-UPDATE by external_id
| ('ourairports:<id numerik OurAirports>'). Row source='manual' (dibuat
| manager lewat UI) TIDAK PERNAH ditimpa - dicek via
| indonesia_find_conflicting_manual() sebelum INSERT baru.
*/

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/indonesia_db.php';

$csvPath = $argv[1] ?? null;
$csvUrl  = 'https://ourairports.com/countries/ID/airports.csv';
$raw = $csvPath ? file_get_contents($csvPath) : file_get_contents($csvUrl);
if ($raw === false) {
    fwrite(STDERR, "Gagal mengambil CSV.\n");
    exit(1);
}

$fh = fopen('php://temp', 'r+');
fwrite($fh, $raw);
rewind($fh);
$header = fgetcsv($fh);
if (!$header) {
    fwrite(STDERR, "CSV kosong/rusak.\n");
    exit(1);
}
$rows = [];
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) !== count($header)) continue; // baris malformed, skip
    $rows[] = array_combine($header, $r);
}
fclose($fh);

$pdo = db();
$now = now_iso();
$inserted = 0; $updated = 0; $skippedClosed = 0; $skippedType = 0; $skippedConflict = 0; $skippedBadCoord = 0;

$pdo->beginTransaction();
foreach ($rows as $row) {
    $type = $row['type'] ?? '';
    if ($type === 'closed') { $skippedClosed++; continue; }
    if (!in_array($type, airport_types(), true)) { $skippedType++; continue; }

    $lat = $row['latitude_deg'] ?? null;
    $lon = $row['longitude_deg'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lon)) { $skippedBadCoord++; continue; }

    $name = trim($row['name'] ?? '');
    if ($name === '') continue;

    $externalId = 'ourairports:' . $row['id'];
    $icao = strtoupper(trim($row['ident'] ?? ''));
    $iata = strtoupper(trim($row['iata_code'] ?? '')) ?: null;

    $notes = null;
    $gps = strtoupper(trim($row['gps_code'] ?? ''));
    if ($gps !== '' && $gps !== $icao) $notes = 'GPS code: ' . $gps;

    $fields = [
        'icao' => $icao ?: null, 'iata' => $iata, 'name' => $name, 'type' => $type,
        'lat' => round((float)$lat, 7), 'lon' => round((float)$lon, 7),
        'elevation_ft' => is_numeric($row['elevation_ft'] ?? null) ? (int)$row['elevation_ft'] : null,
        'municipality' => trim($row['municipality'] ?? '') ?: null,
        'province' => id_province_name(trim($row['iso_region'] ?? '')),
        'notes' => $notes,
    ];

    $stmt = $pdo->prepare("SELECT id FROM airports WHERE source='ourairports' AND external_id = :ext");
    $stmt->execute([':ext' => $externalId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $pdo->prepare(
            'UPDATE airports SET icao=:icao, iata=:iata, name=:name, type=:type, lat=:lat, lon=:lon,
             elevation_ft=:elev, municipality=:muni, province=:prov, notes=:notes, updated_at=:u WHERE id=:id'
        )->execute([
            ':icao' => $fields['icao'], ':iata' => $fields['iata'], ':name' => $fields['name'],
            ':type' => $fields['type'], ':lat' => $fields['lat'], ':lon' => $fields['lon'],
            ':elev' => $fields['elevation_ft'], ':muni' => $fields['municipality'],
            ':prov' => $fields['province'], ':notes' => $fields['notes'],
            ':u' => $now, ':id' => $existingId,
        ]);
        $updated++;
        continue;
    }

    if ($icao !== '' && indonesia_find_conflicting_manual($pdo, 'airports', 'icao', $icao)) {
        $skippedConflict++;
        continue;
    }

    $pdo->prepare(
        'INSERT INTO airports (icao,iata,name,type,lat,lon,elevation_ft,municipality,province,source,external_id,notes,created_at,updated_at)
         VALUES (:icao,:iata,:name,:type,:lat,:lon,:elev,:muni,:prov,"ourairports",:ext,:notes,:c,:u)'
    )->execute([
        ':icao' => $fields['icao'], ':iata' => $fields['iata'], ':name' => $fields['name'],
        ':type' => $fields['type'], ':lat' => $fields['lat'], ':lon' => $fields['lon'],
        ':elev' => $fields['elevation_ft'], ':muni' => $fields['municipality'], ':prov' => $fields['province'],
        ':ext' => $externalId, ':notes' => $fields['notes'], ':c' => $now, ':u' => $now,
    ]);
    $inserted++;
}
$pdo->commit();

echo "Selesai. Insert: $inserted, Update: $updated, Skip closed: $skippedClosed, Skip type: $skippedType, Skip conflict manual: $skippedConflict, Skip bad coord: $skippedBadCoord.\n";
echo 'Total baris CSV: ' . count($rows) . "\n";
