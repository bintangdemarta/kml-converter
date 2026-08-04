# IFFI FlightOps — Vault

Index utama dokumentasi project. Mulai dari sini.

## Dokumen Inti

- [[PRD - IFFI FlightOps]] — dokumen produk utama (versi wikilink dari `PRD — IFFI FlightOps.md` di root repo)
- [[Infrastructure]] — server, CI/CD, domain, jaringan

## Modul (status implementasi)

| Modul | Status |
|---|---|
| [[modules/Flight Request System]] | ✅ Selesai |
| [[modules/Live Map Dashboard]] | ✅ Selesai |
| [[modules/Ground Operations - Taxiway Routing]] | ✅ Selesai (data WIII nyata, sebagian belum tersambung) |
| [[modules/Indonesia Database]] | 🟡 Sebagian (Airport + Landmark selesai, Airspace + Reporting Point menunggu API key OpenAIP) |
| [[modules/Pilot Profile and Logbook]] | 🟡 Fondasi ada (lewat Flight Request System), belum ada achievement/badge |
| [[modules/Training Academy]] | ✅ Selesai (versi awal) |
| [[modules/Community and Events]] | ✅ Selesai (versi awal) |
| [[modules/Future Integrations]] | ⬜ Belum digarap |

## Referensi Data

- [[WIII-Taxiway-Reference]] — tabel node/edge taxiway WIII (data nyata dari OpenStreetMap)
- [[Live-Map-Dashboard-Spec]] — spec yang terealisasi dari Live Map Dashboard
- [[WIBB-RWY18-Custom-Route]] — catatan eksplorasi holding pattern (di luar scope resmi)

## Cara Pakai Vault Ini

- Semua `[[link]]` bisa diklik di Obsidian untuk lompat antar catatan
- Vault ini adalah bagian dari repo git `kml-converter` (folder `docs/`) — ikut ter-commit & push
- Update catatan modul setiap ada perubahan besar di kode, supaya dokumentasi tidak basi
