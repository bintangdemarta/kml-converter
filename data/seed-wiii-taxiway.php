<?php
/*
|--------------------------------------------------------------------------
| Seed data taxiway WIII (Soekarno-Hatta) dari OpenStreetMap
|--------------------------------------------------------------------------
| Jalankan SEKALI setelah setup awal (php data/seed-wiii-taxiway.php) untuk
| mengisi Ground Ops - Taxiway Routing dengan data nyata, supaya tidak perlu
| plot node satu-satu lewat UI.
|
| SUMBER: OpenStreetMap contributors, lisensi ODbL (https://openstreetmap.org).
| Diambil via Overpass API (2026-08-02), diproses jadi graph node+edge:
|   - Node "junction" & "runway_exit": titik sambungan antar taxiway (dari
|     node OSM yang dipakai bersama >=2 way, digabung kalau jaraknya <15m
|     karena OSM kadang menggambar taxiway yang bersambung visual tapi pakai
|     node id beda)
|   - Node "holding_point": dari tag aeroway=holding_position
|   - Node "gate": dari tag aeroway=gate, disambungkan ke node taxiway
|     terdekat via garis lurus perkiraan (BUKAN jalur taxi asli) kalau jarak
|     <400m
|   - Node "runway_threshold": endpoint way aeroway=runway (mengisi gap yang
|     dulu belum ada koordinat threshold 07R/25L & 06/24)
|
| KETERBATASAN YANG DIKETAHUI (bukan bug, tapi batasan sumber data):
|   - BUKAN data survey AIP resmi - re-verifikasi sebelum dipakai operasional
|     serius, terutama holding point & runway exit
|   - Graph belum 100% tersambung: peta OSM WIII punya beberapa "pulau"
|     taxiway yang belum lengkap terhubung satu sama lain (~41 komponen
|     terpisah, komponen terbesar 67 node). Routing WORK dalam satu
|     komponen, tapi gate/holding point di komponen berbeda belum bisa
|     dicari rutenya sampai dijembatani manual
|   - 13 node terisolasi total tanpa edge sama sekali: 6 runway threshold
|     (memang disengaja, cuma titik referensi), 6 gate yang jaraknya >400m
|     dari taxiway terdekat, 1 junction
|   - Sebagian nama junction memakai fallback "way<id>" kalau segmen taxiway
|     OSM itu tidak punya tag ref/nama
|
| Manager/admin bisa memperbaiki/melengkapi semua ini lewat Ground Ops UI
| (taxiway/index.php) - itulah tujuan tool-nya dibangun.
*/

require __DIR__ . '/../config/db.php';

$nodes = json_decode(file_get_contents(__DIR__ . '/seeds/wiii-taxi-nodes.json'), true);
$edges = json_decode(file_get_contents(__DIR__ . '/seeds/wiii-taxi-edges.json'), true);

$pdo = db();
$now = date('Y-m-d H:i:s');

$existing = (int)$pdo->query("SELECT COUNT(*) FROM taxi_nodes WHERE icao='WIII'")->fetchColumn();
if ($existing > 0) {
    echo "WIII sudah punya $existing node di database. Seed dibatalkan (hapus data WIII dulu lewat UI kalau mau re-seed).\n";
    exit(1);
}

$pdo->beginTransaction();

$insNode = $pdo->prepare(
    'INSERT INTO taxi_nodes (icao,ref_code,name,type,lat,lon,notes,created_at,updated_at)
     VALUES (:i,:r,:n,:t,:la,:lo,:no,:c,:u)'
);
$dbIdByRef = [];
foreach ($nodes as $refCode => $n) {
    $insNode->execute([
        ':i' => 'WIII', ':r' => $refCode, ':n' => $n['name'], ':t' => $n['type'],
        ':la' => $n['lat'], ':lo' => $n['lon'], ':no' => $n['notes'],
        ':c' => $now, ':u' => $now,
    ]);
    $dbIdByRef[$refCode] = (int)$pdo->lastInsertId();
}

$insEdge = $pdo->prepare(
    'INSERT INTO taxi_edges (icao,from_node_id,to_node_id,taxiway_name,bidirectional,distance_m,created_at)
     VALUES (:i,:f,:t,:tn,:b,:d,:c)'
);
$edgeCount = 0;
foreach ($edges as $e) {
    if (!isset($dbIdByRef[$e['from_ref']], $dbIdByRef[$e['to_ref']])) continue;
    $insEdge->execute([
        ':i' => 'WIII', ':f' => $dbIdByRef[$e['from_ref']], ':t' => $dbIdByRef[$e['to_ref']],
        ':tn' => $e['taxiway_name'], ':b' => $e['bidirectional'], ':d' => $e['distance_m'], ':c' => $now,
    ]);
    $edgeCount++;
}

$pdo->commit();

echo "Selesai: " . count($dbIdByRef) . " node dan $edgeCount edge WIII dimuat dari OpenStreetMap.\n";
echo "Buka taxiway/index.php?icao=WIII untuk lihat & lengkapi lewat UI.\n";
