# Flight Request System

Status: ✅ Selesai. Modul pertama IFFI FlightOps, jadi fondasi auth/role untuk modul lain.

## Alur

```
Pilot → Request Flight → Manager review → Approve/Reject → Dispatch → Pilot terbang → Mark as Flown → Logbook → XP
```

## Arsitektur

- Stack: PHP + SQLite (PDO), session-based auth
- Role: `pilot` / `manager` / `admin` — user pertama yang register otomatis jadi `admin`
- Reuse `calculateDistanceNM()` dari `parser.php` (project converter lama) untuk hitung jarak rute & XP

## File Kunci

- `lib/auth.php` — auth, CSRF, role guard (dipakai SEMUA modul berikutnya)
- `lib/xp.php` — formula XP & rank
- `pilot/request-new.php`, `pilot/dashboard.php`, `pilot/mark-flown.php`
- `manager/queue.php`, `manager/review.php`, `manager/dispatch.php`
- `logbook/index.php`

## Terkait

- [[Pilot Profile and Logbook]] — logbook & XP jadi bagian dari modul ini untuk saat ini, belum dipisah
- [[Infrastructure]] — cara deploy & CI/CD modul ini (dan semua modul lain)
- [[PRD - IFFI FlightOps]] — §4.6
