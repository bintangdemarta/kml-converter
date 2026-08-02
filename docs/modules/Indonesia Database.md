# Indonesia Database

Status: 🟡 Sebagian. Airport + Landmark selesai dengan data nyata, Airspace + Reporting Point menunggu API key OpenAIP.

## Sub-domain

| Sub-domain | Sumber | Status |
|---|---|---|
| Airport | OurAirports.com (Public Domain) | ✅ 685 bandara diimpor |
| Landmark | OpenStreetMap (Overpass API) | ✅ 572 landmark (gunung/volcano/danau/kota) |
| Airspace | OpenAIP.net (CC BY-NC 4.0) | ⏳ Butuh API key, skema + skeleton import siap |
| Reporting Point | OpenAIP.net | ⏳ Sama, CRUD manual sudah bisa dipakai |

Cuaca BMKG (integrasi API live) di-skip dari scope ini, dibahas terpisah nanti.

## Kriteria Kurasi Landmark

- Gunung berapi: semua bernama, tanpa filter elevasi (signifikansi ash/NOTAM tidak berkorelasi dengan tinggi)
- Puncak gunung: bernama + elevasi ≥2000m
- Danau: bernama + luas ≥5km² (shoelace formula dari geometri asli, termasuk handle multipolygon spt Danau Toba)
- Kota: semua dengan tag population di OSM

## Provenance & Re-import

Semua tabel punya kolom `source` (`ourairports`/`osm`/`openaip`/`manual`) + `external_id`. Import script **upsert** (re-run = refresh, bukan duplikat), dan **tidak pernah menimpa** entri `source='manual'` yang dibuat manager lewat UI.

## File Kunci

- `lib/indonesia_db.php` — enum, province lookup, `indonesia_find_conflicting_manual()`
- `database/index.php` — browse + CRUD 4 kategori (tab switcher)
- `data/import-airports.php`, `data/import-landmarks.php` — siap jalan
- `data/import-airspaces.php`, `data/import-reporting-points.php` — skeleton, menunggu API key

## Terkait

- [[PRD - IFFI FlightOps]] — §4.9
- [[modules/Ground Operations - Taxiway Routing]] — perluasan cakupan yang sama
