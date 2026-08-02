# PRD — IFFI FlightOps

> Versi wikilink dari `PRD — IFFI FlightOps.md` di root repo. Isi PRD asli dipertahankan, ditambah link ke catatan modul & status implementasi terkini. Kalau PRD asli berubah, sinkronkan manual ke sini.

**Room:** FlightOps War Room
**Versi:** 0.2 (Draft Berkembang)

---

## 1. Ringkasan Eksekutif

**IFFI FlightOps** adalah platform operasi virtual airline untuk komunitas Infinite Flight Indonesia (IFFI). Mencakup flight planning, dispatch, training, logbook, event, hingga manajemen pilot.

Terinspirasi [Waypoint Live](https://waypoint-live.app/), **bukan clone** — Waypoint Live fokus radar/tracking, IFFI FlightOps mencakup seluruh siklus operasi virtual airline.

### Tiga Ekosistem Terkait

1. **IFFI FlightOps** — platform utama (dokumen ini)
2. **Infinite Flight Utility Suite** — tools pendukung (KML/GPX/GeoJSON converter, waypoint generator, holding pattern generator, route validator)
3. **AI Aviation Assistant** — asisten flight planning, analisis rute, performa pesawat, cuaca, fuel, evaluasi penerbangan

---

## 2. Tujuan Produk

- Sistem manajemen operasi virtual airline lengkap untuk komunitas IFFI
- Standarisasi proses flight request, dispatch, logbook pilot
- Database penerbangan Indonesia yang detail (airport, airspace, reporting point, ground layout)
- Jalur pengembangan skill pilot melalui training academy
- Pondasi untuk integrasi eksternal (Navigraph, SimBrief, Discord, Infinite Flight API)

---

## 3. Modul & Status Implementasi

| Modul | Status |
|---|---|
| [[modules/Flight Request System]] | ✅ Selesai — fondasi auth/role untuk semua modul lain |
| [[modules/Live Map Dashboard]] | ✅ Selesai |
| [[modules/Ground Operations - Taxiway Routing]] | ✅ Tool selesai, data WIII nyata (267 node/272 edge) |
| [[modules/Indonesia Database]] | 🟡 Airport+Landmark selesai (685+572 data nyata), Airspace+Reporting Point menunggu API key |
| [[modules/Pilot Profile and Logbook]] | 🟡 Fondasi ada lewat Flight Request System |
| [[modules/Training Academy]] | ⬜ Belum digarap |
| [[modules/Community and Events]] | ⬜ Belum digarap |
| [[modules/Future Integrations]] | ⬜ Belum digarap |

MVP scope penuh & arsitektur tech stack besar masih belum diputuskan secara formal — pengembangan berjalan modul-per-modul berdasar prioritas diskusi.

---

## 4. Detail Modul

### 4.1 Pilot Dashboard
Titik masuk utama pilot — ringkasan profil, jam terbang, XP, rank, notifikasi request/dispatch. Lihat [[modules/Flight Request System]].

### 4.2 Flight Planner (IFR & VFR)
Perencanaan rute, cruise/fuel planning, SID/STAR/airway, holding pattern, route validation. Basis kode: `parser.php` (converter lama).

### 4.3 Interactive Map
Real route, ETA, progress, checklist manual. **Tidak** terhubung Infinite Flight API (keputusan sadar).

### 4.4 KML/Route Import-Export → [[modules/Live Map Dashboard]]
Basis: **Infinite Flight Converter** (PHP + Leaflet.js). Upgrade jadi dashboard komunitas — lihat [[Live-Map-Dashboard-Spec]] untuk spec yang sudah terealisasi.

### 4.5 Pilot Profile & Logbook → [[modules/Pilot Profile and Logbook]]

### 4.6 Flight Request System → [[modules/Flight Request System]]

```
Pilot → Request Flight → Manager review → Approve/Reject → Dispatch → Terbang → Logbook → XP → History
```

### 4.7 Training Academy → [[modules/Training Academy]]

### 4.8 Community & Events → [[modules/Community and Events]]

### 4.9 Indonesia Database → [[modules/Indonesia Database]]
Airport, Airspace, Reporting Point, Landmark, cuaca BMKG (di-skip dari scope saat ini).

### 4.10 Ground Operations — Taxiway Routing → [[modules/Ground Operations - Taxiway Routing]]
Vacate runway (exit→gate) & taxi to runway (gate→holding point). Pilot project: **WIII**. Data lengkap: [[WIII-Taxiway-Reference]].

### 4.11 Future Integrations → [[modules/Future Integrations]]
Navigraph, SimBrief, Discord, Infinite Flight API, Replay, AI Assistant.

---

## 5. Materi Teknis Aviation Pendukung

**Route & Airport:** CGK/WIII, DPS, HLP, YIA, JOG, SUB, BDO, BIK, PDG (Indonesia); NZAA, KLAX, KJFK, KORD, OMDB, RCTP, CYVR, KSFO, KONT, PASY, PADK, PASN, PADU, PAPB (internasional)

**Performa Pesawat:** A380, A350-1000, Cirrus SR22 GTS, TBM-930, C130J-30, Private Jet

**Navigasi & Prosedur:** SID, STAR, Approach, ILS, RNAV, Visual, Holding, Top of Climb/Descent, Flight Level

**Cuaca:** METAR, TAF, ATIS, QNH, Wind

**Diversion & Emergency:** PADK, PASY, PASN, PAPB, PADU

---

## 6. Infrastruktur

Lihat [[Infrastructure]] — server Proxmox, CI/CD, domain Cloudflare. Di luar cakupan produk PRD, tapi krusial untuk operasional.

---

## 7. Referensi File

| Catatan | Isi |
|---|---|
| [[WIII-Taxiway-Reference]] | Data node+edge taxiway WIII nyata (267 node, 272 edge) |
| [[Live-Map-Dashboard-Spec]] | Spec Live Map Dashboard (terealisasi) |
| [[WIBB-RWY18-Custom-Route]] | Catatan eksplorasi holding pattern — bukan scope resmi |

---

*Vault ini bagian dari repo git `kml-converter` (`docs/`). PRD asli (non-wikilink) tetap di root repo sebagai referensi resmi.*
