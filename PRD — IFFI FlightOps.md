# PRD — IFFI FlightOps

**Room:** FlightOps War Room
**Versi:** 0.2 (Draft Berkembang)
**Status:** Beberapa modul sudah punya data/spec konkret (Ground Ops WIII, Live Map Dashboard); MVP scope & tech stack besar masih belum diputuskan

---

## 1. Ringkasan Eksekutif

**IFFI FlightOps** adalah platform operasi virtual airline untuk komunitas Infinite Flight Indonesia (IFFI). Platform ini bukan sekadar situs komunitas, melainkan sistem operasi lengkap yang menghubungkan pilot, dispatcher, instruktur, dan admin dalam satu ekosistem digital — mencakup flight planning, dispatch, training, logbook, event, hingga manajemen pilot.

Platform ini terinspirasi dari [Waypoint Live](https://waypoint-live.app/), namun **bukan clone**. Waypoint Live berfokus pada radar/tracking, sedangkan IFFI FlightOps mencakup seluruh siklus operasi virtual airline.

### Tiga Ekosistem Terkait

1. **IFFI FlightOps** — platform utama (dokumen ini)
2. **Infinite Flight Utility Suite** — tools pendukung (KML/GPX/GeoJSON converter, waypoint generator, holding pattern generator, route validator)
3. **AI Aviation Assistant** — asisten untuk flight planning, analisis rute, performa pesawat, cuaca, fuel, evaluasi penerbangan

---

## 2. Tujuan Produk

- Menyediakan sistem manajemen operasi virtual airline yang lengkap untuk komunitas IFFI
- Menstandarkan proses flight request, dispatch, dan logbook pilot
- Membangun database penerbangan Indonesia yang detail (airport, airspace, reporting point, ground layout)
- Membuka jalur pengembangan skill pilot melalui training academy
- Menjadi pondasi yang dapat berkembang ke integrasi eksternal (Navigraph, SimBrief, Discord, Infinite Flight API)

---

## 3. Prinsip & Keputusan yang Sudah Diambil

| Keputusan | Status |
|---|---|
| Fokus PRD dahulu, tech stack belum dibahas | ✅ Settled |
| Flight Request System masuk sebagai bagian **Core** | ✅ Settled |
| Interactive Map **belum** menggunakan Infinite Flight API | ✅ Settled |
| Map hanya menampilkan real route, ETA, progress, checklist manual | ✅ Settled |
| Bukan clone Waypoint Live — arah ke platform operasi VA lengkap | ✅ Settled |
| Data taxiway ditelusuri dari Google Earth/satelit (mirip alur KML) | ✅ Settled |
| Bandara pertama untuk fitur taxiway routing: **WIII** (Soekarno-Hatta) | ✅ Settled |
| Live Map Dashboard: basemap default satelit (Esri) + toggle street | ✅ Settled |
| Live Map Dashboard: save/load rute pakai login (bukan copy-paste doang) | ✅ Settled |
| Live Map Dashboard: sifat rute publik/komunitas, bukan privat per-user | ✅ Settled |
| Live Map Dashboard: login pakai role `member`/`admin` untuk moderasi | ✅ Settled |
| Live Map Dashboard: storage pakai SQLite (bukan MySQL, biar ringan & gak ngiket tech stack besar) | ✅ Settled |
| MVP scope (modul mana duluan) | ⏳ Belum settled |
| Arsitektur teknis / tech stack (untuk platform besar IFFI FlightOps) | ⏳ Belum settled |
| Model data Indonesia Database | ⏳ Belum settled |

---

## 4. Modul Inti (Core Modules)

### 4.1 Pilot Dashboard
Titik masuk utama pilot — ringkasan profil, jam terbang, XP, rank, notifikasi request/dispatch.

### 4.2 Flight Planner (IFR & VFR)
- Perencanaan rute IFR dan VFR
- Cruise planning, fuel planning
- SID, STAR, airway
- Holding pattern (manual & otomatis)
- Route validation

### 4.3 Interactive Map
- Menampilkan real route, ETA, progress penerbangan
- Checklist prosedur yang ditekan manual oleh pilot
- **Tidak** terhubung ke Infinite Flight API (keputusan sadar untuk MVP)

### 4.4 KML/Route Import-Export & Validation Engine
- Import KML dari Google Earth
- Export rute ke format yang kompatibel dengan Infinite Flight
- Validasi rute otomatis
- Basis kode sudah ada: project **Infinite Flight Converter** (PHP + Leaflet.js), mendukung KML/GPX/GeoJSON, holding pattern generator dasar, distance calculator (haversine)

**Rencana upgrade — Live Map Dashboard *(next project, spec sudah lengkap, belum dieksekusi)*:**

Referensi: [`Live-Map-Dashboard-Spec.md`](./Live-Map-Dashboard-Spec.md)

Upgrade dari input lat/lon manual menjadi peta interaktif ala Google Earth Pro — klik bebas di mana saja di dunia untuk membuat waypoint, real-time output siap paste ke Infinite Flight. Sifatnya jadi **dashboard komunitas**:
- Basemap satelit (Esri World Imagery) default, toggle ke street (OSM)
- Klik untuk tambah titik, drag untuk reposisi, hapus titik spesifik
- Save/load rute publik — semua rute bisa dilihat & dipakai siapa saja
- Login dengan role `member`/`admin` (admin bisa moderasi rute siapa pun)
- Storage SQLite (ringan, tidak mengikat ke keputusan tech stack besar IFFI FlightOps)
- Kategori rute: `Holding`/`SID`/`STAR`/`Taxiway`/`Route`/`Other`, plus filter per bandara (ICAO)
- Berpotensi jadi **fondasi auth system** untuk Pilot Profile & modul lain di platform besar nanti
- Rencana bertahap: MVP (map + login dasar + save/load) → drag/edit lanjutan + moderasi admin → search lokasi + overlay data taxiway → integrasi lebih dalam ke IFFI FlightOps

### 4.5 Pilot Profile & Logbook
- Jam terbang, XP, level, rank
- Achievement / pencapaian

### 4.6 Flight Request System *(fitur paling matang saat ini)*

**Alur (flow):**

```
Pilot
  ↓
Request Flight
  ↓
Manager menerima ticket
  ↓
Approve / Reject
  ↓
Dispatch
  ↓
Pilot terbang
  ↓
Logbook
  ↓
XP
  ↓
History
```

### 4.7 Training Academy
- Check ride
- Instructor
- Sertifikasi

### 4.8 Community & Events
- Event, campaign
- Leaderboard, statistik komunitas

### 4.9 Indonesia Database
- Airport
- Airspace
- Reporting point
- Landmark
- Data cuaca BMKG

### 4.10 Ground Operations — Taxiway Routing *(modul baru)*

Modul baru yang memperluas cakupan **Indonesia Database** dan **Interactive Map** ke level operasi darat (ground ops), yang sebelumnya belum dibahas secara eksplisit.

**Ruang lingkup:**
- Path taxiway per bandara, disusun dari hasil trace Google Earth/citra satelit (metode serupa alur pembuatan KML yang sudah pernah dilakukan)
- **Vacate runway** → routing taxiway menuju gate/parking stand (arrival)
- **Taxi to runway** → routing dari gate/parking stand menuju holding point runway (departure)
- Berpotensi terhubung dengan checklist procedure yang sudah ada di Interactive Map, sehingga pilot bisa mengikuti tahapan taxi secara terstruktur

**Bandara pilot project:**
- **WIII** (Soekarno-Hatta International Airport) sebagai bandara pertama
- Setelah pola kerja untuk WIII terbentuk, dapat direplikasi ke bandara lain yang sudah sering dibahas (CGK — catatan: CGK adalah kode IATA, WIII adalah kode ICAO untuk bandara yang sama; DPS, HLP, YIA, JOG, SUB, BDO, BIK, PDG, dst.)

**Alur kerja pembuatan data taxiway (usulan awal, masih terbuka untuk didiskusikan):**
1. Trace layout taxiway WIII dari citra satelit/Google Earth
2. Konversi hasil trace menjadi format data yang bisa dipakai sistem (kemungkinan mengikuti pola KML → parser yang sudah dibuat sebelumnya di Website Converter)
3. Definisikan titik-titik penting: runway exit, taxiway junction, gate/parking stand, holding point
4. Susun logika routing: vacate → taxi to gate, gate → taxi to runway
5. Uji coba integrasi dengan Interactive Map & checklist procedure

**Catatan terbuka:**
- Perlu ditentukan tingkat detail data (apakah cukup jalur utama taxiway, atau sampai ke setiap taxiway kecil)
- Perlu ditentukan bagaimana sistem menentukan rute taxi optimal (statis per skenario, atau dihitung dinamis)

**Progres data WIII (in progress):**

Referensi: [`WIII-Taxiway-Reference.md`](./WIII-Taxiway-Reference.md) (tabel per zona) dan [`wiii-taxiway-data.json`](./wiii-taxiway-data.json) (struktur node + edge siap dipakai sistem)

- ✅ Nama & posisi taxiway sudah teridentifikasi untuk ketiga runway (06/24, 07L/25R, 07R/25L), zona penghubung tengah (WC1/WC2), dan area terminal/apron (Terminal 1, 2, 3, general aviation/cargo) — **48 node** sudah masuk struktur JSON
- ✅ Beberapa koordinat presisi sudah dihitung dari data resmi AIP (jarak threshold → rapid exit taxiway): **N4** (2151m dari THR 07L) dan **N6** (2158m dari THR 25R), pakai geodesic projection dari ARP
- ⏳ Belum ada: koordinat threshold runway 07R/25L & 06/24 (belum ada anchor terpisah), peta konektivitas antar-taxiway (edge-to-edge untuk graph routing), lokasi holding point/IHP, data gate/stand spesifik per connector
- 📝 Langkah berikutnya untuk WIII: cari anchor threshold 07R/25L (biar S4/S6 bisa dihitung), lalu petakan konektivitas edge-to-edge

### 4.11 Future Integrations *(belum digarap)*
- Navigraph
- SimBrief
- Discord
- Infinite Flight API
- Replay
- AI Assistant

---

## 5. Materi Teknis Aviation Pendukung

Berikut adalah kompetensi/topik teknis yang sudah pernah dibahas dan relevan sebagai basis pengetahuan untuk fitur-fitur di atas:

**Route & Airport (contoh yang sudah dianalisis):**
- Indonesia: CGK/WIII, DPS, HLP, YIA, JOG, SUB, BDO, BIK, PDG
- Internasional: NZAA, KLAX, KJFK, KORD, OMDB, RCTP, CYVR, KSFO, KONT, PASY, PADK, PASN, PADU, PAPB, Guam, dll.

**Performa Pesawat:**
- A380, A350-1000, Cirrus SR22 GTS, TBM-930, C130J-30, Private Jet

**Navigasi & Prosedur:**
- SID, STAR, Approach, ILS, RNAV, Visual, Holding, Top of Climb, Top of Descent, Flight Level

**Cuaca:**
- METAR, TAF, ATIS, QNH, Wind

**Diversion & Emergency:**
- PADK, PASY, PASN, PAPB, PADU — analisis kesesuaian runway/ILS untuk heavy aircraft

---

## 6. Topik Terkait (Di Luar Cakupan Langsung PRD Ini)

Topik-topik berikut pernah dibahas di room yang sama namun tidak masuk cakupan produk IFFI FlightOps secara langsung:

- **Data Science** — roadmap belajar bertahap
- **Proxmox** — ZFS, storage pool, dashboard Mikrotik, Cloudflare, monitoring
- **Peltier** — TEC1-12706/12710, power supply, heatsink

---

## 7. Langkah Berikutnya

- [ ] Tentukan MVP scope — modul wajib di versi pertama vs nice-to-have
- [ ] Susun user personas & user stories (Pilot, Dispatcher/Manager, Instructor, Admin)
- [x] Trace nama & posisi taxiway WIII dari satellite/live map view — lihat `WIII-Taxiway-Reference.md`
- [x] Susun data taxiway WIII jadi struktur JSON (node + edge) — lihat `wiii-taxiway-data.json`
- [x] Hitung koordinat presisi untuk sebagian titik (N4, N6) pakai data resmi AIP + geodesic projection
- [x] Bahas & susun spec lengkap Live Map Dashboard (upgrade KML Converter) — lihat `Live-Map-Dashboard-Spec.md`
- [ ] Cari anchor threshold runway 07R/25L & 06/24 (biar S4/S6 dan taxiway zona itu bisa dihitung presisi)
- [ ] Petakan konektivitas antar-taxiway (edge-to-edge) untuk graph routing
- [ ] Eksekusi implementasi Live Map Dashboard fase 1 (MVP) — menunggu sinyal mulai
- [ ] Detailkan PRD per modul, dimulai dari Flight Request System (paling matang) dan Ground Operations
- [ ] Diskusi arsitektur teknis besar setelah PRD modul inti selesai

---

## 8. Referensi File Terkait

Dokumen ini adalah **master PRD**, didukung file-file berikut yang berisi detail teknis per topik:

| File | Isi |
|---|---|
| `WIII-Taxiway-Reference.md` | Tabel referensi taxiway WIII per zona (nama & posisi relatif) |
| `wiii-taxiway-data.json` | Struktur data node + edge taxiway WIII, siap dipakai sistem |
| `Live-Map-Dashboard-Spec.md` | Spec lengkap upgrade KML Converter jadi dashboard komunitas interaktif |
| `WIBB-RWY18-Custom-Route.md` | Contoh custom holding pattern (Dabo Singkep, 7 lap) — hasil eksplorasi fitur waypoint generator, bukan bagian resmi scope produk |

---

*Dokumen ini adalah draft yang terus berkembang seiring diskusi di FlightOps War Room.*