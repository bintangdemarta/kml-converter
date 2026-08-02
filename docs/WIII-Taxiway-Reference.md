# WIII Taxiway Reference (Soekarno-Hatta)

Data nyata dari OpenStreetMap (ODbL), diambil via Overpass API. Detail modul: [[modules/Ground Operations - Taxiway Routing]]

⚠️ **Bukan data survey AIP resmi** — re-verifikasi sebelum dipakai operasional serius.

## Runway Threshold

| Ref | Nama | Koordinat |
|---|---|---|
| THR-07L | Runway Threshold 07L | -6.1209858, 106.6388829 |
| THR-25R | Runway Threshold 25R | -6.1089588, 106.6690617 |
| THR-07R | Runway Threshold 07R | -6.1425269, 106.6436997 |
| THR-25L | Runway Threshold 25L | -6.1302809, 106.6743844 |
| THR-06 | Runway Threshold 06 | -6.1138634, 106.6446203 |
| THR-24 | Runway Threshold 24 | -6.1038368, 106.6697436 |

## Ringkasan Node (267 total)

| Tipe | Jumlah | Catatan |
|---|---|---|
| Gate | 78 | Ref code asli (F1-F7, E1-E7, A1-A7, B1-B7, C1-C7, D1-D7, dst) |
| Junction | 160 | Titik sambungan antar taxiway |
| Holding Point | 21 | Tag `holding_position:type=runway` di OSM |
| Runway Threshold | 6 | Landmark, tidak tersambung ke graph edge (memang disengaja) |
| Runway Exit | 2 | Junction dalam radius 40m dari centerline runway |

## Edge (272 total)

- 255 dari OSM asli, bernama sesuai taxiway (SP2, EC1, EC2, SCX, S2, NC4, N8M, N7M, S8, N3M, N6M, N3, dst — puluhan taxiway teridentifikasi)
- 72 "Apron connector" — stub gate ke taxiway terdekat (garis lurus perkiraan, <400m)
- 17 "Inferred connector (auto-bridge)" — jembatan otomatis antar klaster terpisah (≤200m, unverified vs AIP/imagery)
- 122 edge tanpa nama taxiway spesifik di OSM

## Konektivitas

Graph **belum 100% tersambung** — komponen terbesar 166/267 node (62%), sisanya beberapa klaster kecil terpisah (gap di data OSM asli, bukan gap di tool kita). Manager/admin bisa menyambungkan manual lewat `taxiway/index.php` mode "Add Edge".

## Sumber Data Mentah

`data/seeds/wiii-taxi-nodes.json` dan `data/seeds/wiii-taxi-edges.json` di repo — bisa di-re-generate/diperbarui via proses riset Overpass API yang sama.
