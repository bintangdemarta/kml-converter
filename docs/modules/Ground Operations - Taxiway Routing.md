# Ground Operations — Taxiway Routing

Status: ✅ Tool selesai, data WIII nyata sudah di-seed (sebagian belum tersambung penuh).

Data lengkap: [[WIII-Taxiway-Reference]]

## Konsep

Graph routing (node + edge) untuk **vacate runway** (runway exit → gate) dan **taxi to runway** (gate → holding point). Data **terkurasi manager/admin only** — beda dari [[modules/Live Map Dashboard]] yang community-owned.

## Data WIII (Soekarno-Hatta)

Sumber: OpenStreetMap (ODbL), via Overpass API — bukan survey AIP resmi.

- 267 node: 78 gate (real ref code F1-F7, A1-A7, dst), 160 junction, 21 holding point, 6 runway threshold, 2 runway exit
- 272 edge: 255 dari OSM asli + 17 "Inferred connector" (auto-bridge, jarak ≤200m, label jelas supaya bisa diverifikasi ulang)
- Graph belum 100% tersambung — komponen terbesar 166/267 node, sisanya beberapa klaster kecil (gap OSM asli, bisa dijembatani manual lewat UI)

## Algoritma Routing

`find_taxi_route()` di `lib/taxiway.php` — Dijkstra dengan `SplPriorityQueue`, mendukung edge searah (`bidirectional=0`) dan dua-arah.

## File Kunci

- `lib/taxiway.php` — Dijkstra + helper jarak/warna
- `taxiway/index.php` — peta graph + routing panel + mode Add Node/Add Edge
- `data/seed-wiii-taxiway.php` — seed data nyata (idempotent)

## Terkait

- [[PRD - IFFI FlightOps]] — §4.10
- [[modules/Indonesia Database]] — perluasan cakupan data Indonesia yang sama
