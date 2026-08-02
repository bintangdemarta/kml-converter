# Live Map Dashboard

Status: ✅ Selesai. Upgrade dari converter KML/GPX/GeoJSON lama jadi peta interaktif komunitas.

Spec lengkap yang terealisasi: [[Live-Map-Dashboard-Spec]]

## Konsep

- Rute bersifat **publik/komunitas** — siapa saja bisa lihat, hanya user login yang bisa create/edit rute sendiri
- **Beda dari** [[modules/Ground Operations - Taxiway Routing]]: rute di sini community-owned (siapa saja create), sedangkan taxiway graph itu data referensi terkurasi (manager/admin only)

## Fitur

- Peta Leaflet: basemap Esri satelit default + toggle OSM
- Klik peta untuk tambah waypoint, drag untuk reposisi, hapus titik
- Kategori rute: Holding / SID / STAR / Taxiway / Route / Other
- Filter by kategori, ICAO, nama
- Create/edit digabung 1 file (`map/index.php`) — validasi gagal re-render form dengan state dipertahankan, supaya titik yang sudah diplot user tidak hilang

## File Kunci

- `map/index.php` — browse + view + create + edit + peta
- `map/delete.php` — POST-only delete, ownership-or-manager guard

## Terkait

- [[PRD - IFFI FlightOps]] — §4.4
