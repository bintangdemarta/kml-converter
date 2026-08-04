# Community & Events

Status: Selesai (versi awal).

## Konsep

Satu sistem untuk event (one-time gathering) dan campaign (challenge berdurasi) - dibedakan lewat kolom "type", bukan dua fitur terpisah. Partisipasi dicatat seperti sistem absen: pilot RSVP dulu, manager tandai hadir/tidak setelahnya secara manual.

## Alur

1. Manager buat event/campaign lewat `community/event-new.php` (judul, deskripsi, tanggal mulai/selesai, opsional bandara).
2. Pilot lihat daftar upcoming/riwayat di `community/index.php`, RSVP di `community/event-view.php`.
3. Setelah acara selesai, manager tandai kehadiran per pilot (Hadir/Tidak Hadir) di halaman yang sama - seperti absen manual.
4. Leaderboard all-time (`community/leaderboard.php`) dihitung langsung dari `users.xp` dan agregasi `logbook` (total flight, total distance) yang sudah ada dari Flight Request System - tidak ada tabel baru untuk ini.
5. Statistik komunitas (total pilot, total flight, total distance, pilot aktif 7 hari terakhir) tampil di `community/index.php`.

## Skema Database

- `community_events` (id, type, title, description, airport_icao, start_at, end_at, created_by, created_at)
- `event_participants` (id, event_id, pilot_id, rsvp_at, attended, marked_by, marked_at) - attended: NULL belum ditandai, 1 hadir, 0 tidak hadir.

## Belum Ada (di luar scope versi awal)

- Tidak ada leaderboard periodik (mingguan/bulanan) - cuma all-time sesuai keputusan awal
- Belum ada notifikasi RSVP/reminder event
- Attendance tidak otomatis terhubung ke flight request/logbook (murni manual oleh manager)

## Terkait

- [[PRD - IFFI FlightOps]] - Sec 4.8
- [[modules/Pilot Profile and Logbook]] - sumber data XP/distance untuk leaderboard
