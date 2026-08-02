# Live Map Dashboard — Spec (Terealisasi)

Modul: [[modules/Live Map Dashboard]]. Dokumen ini adalah spec yang **sudah dibangun**, bukan spec rencana — mencatat keputusan desain final untuk referensi ke depan.

## Keputusan Final

| Keputusan | Implementasi |
|---|---|
| Basemap default | Esri World Imagery (satelit) |
| Toggle basemap | OSM (street), via `L.control.layers` |
| Input waypoint | Klik peta (tambah), drag marker (reposisi), klik kanan/popup (hapus) |
| Sifat rute | Publik — semua orang bisa lihat, hanya pemilik/manager bisa edit |
| Auth | Reuse sistem role dari [[modules/Flight Request System]] (pilot/manager/admin) — bukan role terpisah "member" seperti draft awal PRD |
| Storage | SQLite (sesuai keputusan awal), tabel `routes` dengan kolom `source`/`external_id` (pola sama dgn [[modules/Indonesia Database]]) |
| Kategori rute | Holding / SID / STAR / Taxiway / Route / Other |
| Filter | Kategori, ICAO, nama |

## Perbedaan dari Draft PRD Awal

- PRD awal menyebut role terpisah "member"/"admin" khusus modul ini — di implementasi, **di-reuse dari sistem role app yang sudah ada** (`pilot`/`manager`/`admin`) supaya tidak ada auth ganda.
- "Fondasi auth system" yang disebut PRD sebagai potensi — sudah **terwujud**: modul ini dan semua modul setelahnya (Ground Ops, Indonesia Database) pakai `lib/auth.php` yang sama.

## Belum Digarap (dari rencana bertahap PRD)

- Search lokasi (geocoding)
- Overlay data taxiway langsung di peta ini (saat ini taxiway punya halaman terpisah: [[modules/Ground Operations - Taxiway Routing]])
- Integrasi lebih dalam ke checklist procedure Interactive Map

## File

`map/index.php`, `map/delete.php`
