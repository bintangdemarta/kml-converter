<?php
/*
|--------------------------------------------------------------------------
| Taxiway — enum helper, jarak edge, routing (Dijkstra)
|--------------------------------------------------------------------------
| Dipakai modul Ground Operations - Taxiway Routing (taxiway/*.php).
*/

require_once __DIR__ . '/../parser.php'; // reuse calculateDistanceNM()

/** Tipe node taxiway yang valid. */
function taxi_node_types(): array
{
    return ['runway_threshold', 'runway_exit', 'junction', 'gate', 'holding_point', 'other'];
}

/** Label tampilan per tipe node (dropdown, legend peta, sidebar). */
function taxi_node_type_label(string $type): string
{
    $labels = [
        'runway_threshold' => 'Runway Threshold',
        'runway_exit'      => 'Runway Exit',
        'junction'         => 'Taxiway Junction',
        'gate'             => 'Gate / Parking Stand',
        'holding_point'    => 'Holding Point',
        'other'            => 'Other',
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/** Warna marker per tipe node (legend peta + Leaflet circleMarker). */
function taxi_type_colors(): array
{
    return [
        'runway_threshold' => '#ef4444',
        'runway_exit'      => '#f59e0b',
        'junction'         => '#38bdf8',
        'gate'             => '#22c55e',
        'holding_point'    => '#a78bfa',
        'other'            => '#94a3b8',
    ];
}

function taxi_type_color(string $type): string
{
    return taxi_type_colors()[$type] ?? '#94a3b8';
}

/**
 * Jarak great-circle (meter) antar 2 node. Reuse calculateDistanceNM() dari
 * parser.php (tidak menulis ulang haversine) lalu konversi NM -> meter.
 *
 * CATATAN PRESISI: calculateDistanceNM() membulatkan ke 2 desimal NM
 * (~ +-18.5 meter) sebelum dikembalikan. Untuk jarak rute (puluhan-ratusan
 * NM) ini dapat diabaikan, tapi untuk edge taxiway yang pendek (puluhan-
 * ratusan meter) ini adalah sumber kuantisasi yang nyata. Trade-off yang
 * disengaja demi reuse kode existing - jangan dikira bug.
 */
function taxi_edge_distance_meters(array $nodeA, array $nodeB): float
{
    $nm = calculateDistanceNM([
        sprintf('%.7F,%.7F', (float)$nodeA['lat'], (float)$nodeA['lon']),
        sprintf('%.7F,%.7F', (float)$nodeB['lat'], (float)$nodeB['lon']),
    ]);
    return round($nm * 1852, 1);
}

/** Recompute distance_m semua edge yang menempel ke satu node (dipanggil setelah reposisi node). */
function taxi_recompute_edges_for_node(PDO $pdo, int $nodeId): void
{
    $stmt = $pdo->prepare(
        'SELECT e.id, a.lat AS a_lat, a.lon AS a_lon, b.lat AS b_lat, b.lon AS b_lon
         FROM taxi_edges e
         JOIN taxi_nodes a ON a.id = e.from_node_id
         JOIN taxi_nodes b ON b.id = e.to_node_id
         WHERE e.from_node_id = :id OR e.to_node_id = :id'
    );
    $stmt->execute([':id' => $nodeId]);

    $upd = $pdo->prepare('UPDATE taxi_edges SET distance_m = :d WHERE id = :id');
    foreach ($stmt->fetchAll() as $row) {
        $d = taxi_edge_distance_meters(
            ['lat' => $row['a_lat'], 'lon' => $row['a_lon']],
            ['lat' => $row['b_lat'], 'lon' => $row['b_lon']]
        );
        $upd->execute([':d' => $d, ':id' => $row['id']]);
    }
}

/**
 * Dijkstra shortest path dari $fromId ke $toId dalam graph 1 bandara (icao).
 * Edge bidirectional=1 bisa dilalui 2 arah; bidirectional=0 cuma searah
 * dari from_node_id ke to_node_id.
 *
 * Return null kalau salah satu node tidak ada, beda icao, atau tidak ada
 * jalur. Kalau ketemu:
 *   ['path' => [node...], 'edges' => [edge...], 'distance_m' => float]
 * $path berisi seluruh node dari awal sampai akhir (termasuk endpoint),
 * $edges berisi edge row yang dilalui berurutan (count = count($path)-1).
 */
function find_taxi_route(PDO $pdo, string $icao, int $fromId, int $toId): ?array
{
    $icao = strtoupper($icao);

    $nodesStmt = $pdo->prepare('SELECT * FROM taxi_nodes WHERE icao = :icao');
    $nodesStmt->execute([':icao' => $icao]);
    $nodes = [];
    foreach ($nodesStmt->fetchAll() as $n) {
        $nodes[(int)$n['id']] = $n;
    }
    if (!isset($nodes[$fromId], $nodes[$toId])) {
        return null;
    }
    if ($fromId === $toId) {
        return ['path' => [$nodes[$fromId]], 'edges' => [], 'distance_m' => 0.0];
    }

    $edgesStmt = $pdo->prepare('SELECT * FROM taxi_edges WHERE icao = :icao');
    $edgesStmt->execute([':icao' => $icao]);

    // adjacency: nodeId => [ ['to' => nodeId, 'weight' => float, 'edge' => row], ... ]
    $adj = [];
    foreach ($edgesStmt->fetchAll() as $e) {
        $a = (int)$e['from_node_id'];
        $b = (int)$e['to_node_id'];
        $w = (float)$e['distance_m'];
        $adj[$a][] = ['to' => $b, 'weight' => $w, 'edge' => $e];
        if ((int)$e['bidirectional'] === 1) {
            $adj[$b][] = ['to' => $a, 'weight' => $w, 'edge' => $e];
        }
    }

    $dist = array_fill_keys(array_keys($nodes), INF);
    $dist[$fromId] = 0.0;
    $prevNode = [];
    $prevEdge = [];
    $visited = [];

    // SplPriorityQueue = max-heap bawaan PHP; simpan priority = -distance
    // supaya node dengan distance TERKECIL yang ter-extract duluan.
    $pq = new SplPriorityQueue();
    $pq->insert($fromId, 0.0);

    while (!$pq->isEmpty()) {
        $u = $pq->extract();
        if (isset($visited[$u])) {
            continue; // entry basi, sudah difinalisasi dgn dist lebih baik
        }
        $visited[$u] = true;

        if ($u === $toId) {
            break; // begitu target di-extract, dist-nya sudah final (Dijkstra)
        }

        foreach ($adj[$u] ?? [] as $link) {
            $v = $link['to'];
            if (isset($visited[$v])) {
                continue;
            }
            $alt = $dist[$u] + $link['weight'];
            if ($alt < $dist[$v]) {
                $dist[$v] = $alt;
                $prevNode[$v] = $u;
                $prevEdge[$v] = $link['edge'];
                $pq->insert($v, -$alt);
            }
        }
    }

    if ($dist[$toId] === INF) {
        return null; // tidak ada jalur
    }

    // Reconstruct path dari toId mundur ke fromId via prevNode/prevEdge.
    $pathIds = [$toId];
    $edges = [];
    $cur = $toId;
    while ($cur !== $fromId) {
        $edges[] = $prevEdge[$cur];
        $cur = $prevNode[$cur];
        $pathIds[] = $cur;
    }
    $pathIds = array_reverse($pathIds);
    $edges = array_reverse($edges);

    return [
        'path' => array_map(fn($id) => $nodes[$id], $pathIds),
        'edges' => $edges,
        'distance_m' => round($dist[$toId], 1),
    ];
}
