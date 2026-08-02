<?php
/*
|--------------------------------------------------------------------------
| Import Landmark — OpenStreetMap (via Overpass API)
|--------------------------------------------------------------------------
| Sumber: OpenStreetMap contributors, lisensi ODbL. Diambil via Overpass
| API (2026-08-02), dari area Indonesia (ISO3166-1=ID, admin_level=2).
|
| Jalankan: php data/import-landmarks.php
|
| Kriteria seleksi (riset semi-manual, BUKAN pipeline otomatis penuh):
|   - Gunung berapi (natural=volcano): SEMUA yang bernama, tanpa filter
|     elevasi - signifikansi flight ops (ash/NOTAM risk) tidak berkorelasi
|     dengan tinggi (mis. Anak Krakatau cuma ~157m tapi sangat relevan)
|   - Puncak gunung (natural=peak): bernama DAN elevasi >= 2000m - dipilih
|     supaya jadi landmark visual VFR yang benar2 menonjol (threshold 1000m
|     awal menghasilkan ~800 entri, terlalu banyak & banyak yang tidak
|     signifikan visual di daerah perbukitan biasa)
|   - Danau (way & relation/multipolygon natural=water+water=lake): bernama
|     DAN luas >= 5 km2 (dihitung shoelace formula dari geometri asli, bukan
|     estimasi/tebakan)
|   - Kota (place=city + tag population): semua hasil query (population tag
|     sudah jadi filter alami di data OSM Indonesia)
|
| KETERBATASAN YANG DIKETAHUI:
|   - Bukan data survey resmi - re-verifikasi sebelum dipakai operasional
|   - 1 danau (Matano) luasnya dikoreksi manual - geometri multipolygon-nya
|     terlalu kompleks (31 member ring tak berurutan) utk shoelace otomatis,
|     lihat catatan di landmarks-indonesia.json
|   - Threshold elevasi/luas adalah keputusan kurasi, bukan ambang baku -
|     manager bisa tambah/hapus landmark lain lewat UI
|
| Idempotent: upsert by external_id (belum ada di skema saat ini - landmark
| pakai matching by name+type sederhana karena OSM id tidak disimpan
| terpisah dalam landmarks-indonesia.json; re-run akan UPDATE row dgn nama+
| type sama, row source='manual' tidak pernah ditimpa).
*/

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/indonesia_db.php';

$landmarks = json_decode(file_get_contents(__DIR__ . '/seeds/landmarks-indonesia.json'), true);
if (!$landmarks) {
    fwrite(STDERR, "Gagal baca landmarks-indonesia.json\n");
    exit(1);
}

$pdo = db();
$now = now_iso();
$inserted = 0; $updated = 0; $skippedConflict = 0;

$pdo->beginTransaction();
foreach ($landmarks as $l) {
    $name = trim($l['name'] ?? '');
    $type = $l['type'] ?? '';
    if ($name === '' || !in_array($type, landmark_types(), true)) continue;

    $externalId = 'osm:' . md5($type . '|' . $name . '|' . $l['lat'] . ',' . $l['lon']);

    $fields = [
        ':n' => $name, ':t' => $type,
        ':la' => round((float)$l['lat'], 6), ':lo' => round((float)$l['lon'], 6),
        ':elev' => isset($l['elevation_m']) && $l['elevation_m'] !== null ? (int)$l['elevation_m'] : null,
        ':notes' => $l['notes'] ?? null,
    ];

    $stmt = $pdo->prepare("SELECT id FROM landmarks WHERE source='osm' AND external_id = :ext");
    $stmt->execute([':ext' => $externalId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $pdo->prepare(
            'UPDATE landmarks SET name=:n, type=:t, lat=:la, lon=:lo, elevation_m=:elev, notes=:notes, updated_at=:u WHERE id=:id'
        )->execute($fields + [':u' => $now, ':id' => $existingId]);
        $updated++;
        continue;
    }

    if (indonesia_find_conflicting_manual($pdo, 'landmarks', 'name', $name)) {
        $skippedConflict++;
        continue;
    }

    $pdo->prepare(
        'INSERT INTO landmarks (name,type,lat,lon,elevation_m,source,external_id,notes,created_at,updated_at)
         VALUES (:n,:t,:la,:lo,:elev,"osm",:ext,:notes,:c,:u)'
    )->execute($fields + [':ext' => $externalId, ':c' => $now, ':u' => $now]);
    $inserted++;
}
$pdo->commit();

echo "Selesai. Insert: $inserted, Update: $updated, Skip conflict manual: $skippedConflict.\n";
echo 'Total baris JSON: ' . count($landmarks) . "\n";
