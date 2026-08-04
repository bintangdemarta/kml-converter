# Training Academy

Status: Selesai (versi awal).

## Konsep

Check ride dilakukan di darat (ground observation): instructor mengamati pilot dari ground dan memantau radio lokal bandara yang dipilih pilot. Bukan sesi terbang bersama di udara.

## Alur

1. Manager assign status instructor ke pilot tertentu secara manual (`manager/instructors.php`). Manager/admin otomatis punya akses instructor tanpa perlu di-assign.
2. Manager membuat jenis sertifikasi lewat `manager/certifications.php` (bebas, tidak ada daftar baku - kode unik + nama + deskripsi opsional).
3. Pilot mengajukan check ride lewat `training/request.php`: pilih sertifikasi, bandara ICAO lokasi check ride, catatan opsional (jam sesi, frekuensi radio, dll).
4. Instructor melihat antrian di `training/queue.php`, claim request yang masih pending (assign ke diri sendiri).
5. Setelah observasi ground selesai, instructor review di `training/review.php`: catat hasil observasi, tandai passed/failed.
6. Pilot melihat sertifikasi yang sudah didapat + riwayat lengkap di `training/my.php`.

## Skema Database

- `users.is_instructor` (INTEGER, default 0) - flag manual dari manager.
- `certifications` (id, code, name, description, created_at) - jenis sertifikasi, dikelola manager.
- `checkride_requests` (id, pilot_id, certification_id, airport_icao, notes, status, instructor_id, review_note, created_at, claimed_at, reviewed_at) - status: pending -> claimed -> passed/failed.

Seed awal (bisa dihapus/ditambah manager kapan saja): BASIC (Basic Checkride), RADIO_COMM (Radio Communication), TAXI_PROC (Taxi Procedure), INSTRUCTOR_CERT (Instructor Certification).

## Belum Ada (di luar scope versi awal)

- Sertifikasi belum menggerbang akses fitur lain (misal wajib punya cert tertentu sebelum boleh terbang rute tertentu)
- Belum ada notifikasi (email/Telegram) saat request di-claim atau direview
- Instructor belum bisa reassign/lepas claim ke instructor lain

## Terkait

- [[PRD - IFFI FlightOps]] - Sec 4.7
- [[modules/Pilot Profile and Logbook]] - sertifikasi tampil di halaman pilot, tidak mempengaruhi rank/XP saat ini
