<?php
/*
|--------------------------------------------------------------------------
| Migration: Training Academy
|--------------------------------------------------------------------------
| Menambahkan flag instructor ke users, tabel certifications, dan
| checkride_requests. Ground check ride: instructor observasi dari darat +
| cek radio lokal bandara (bukan terbang bareng), jadi tidak butuh kolom
| terkait sesi terbang bersama.
|
| Jalankan sekali: php data/migrate-training-academy.php
*/

require_once __DIR__ . '/../config/db.php';

$pdo = db();

$hasColumn = $pdo->query("PRAGMA table_info(users)")->fetchAll();
$hasInstructorCol = false;
foreach ($hasColumn as $col) {
    if ($col['name'] === 'is_instructor') {
        $hasInstructorCol = true;
        break;
    }
}
if (!$hasInstructorCol) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_instructor INTEGER NOT NULL DEFAULT 0");
    echo "users.is_instructor added\n";
} else {
    echo "users.is_instructor already exists, skip\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS certifications (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        code        TEXT UNIQUE NOT NULL,
        name        TEXT NOT NULL,
        description TEXT,
        created_at  TEXT NOT NULL
    )"
);
echo "certifications table ok\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS checkride_requests (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        pilot_id         INTEGER NOT NULL REFERENCES users(id),
        certification_id INTEGER NOT NULL REFERENCES certifications(id),
        airport_icao     TEXT NOT NULL,
        notes            TEXT,
        status           TEXT NOT NULL DEFAULT 'pending',
        instructor_id    INTEGER REFERENCES users(id),
        review_note      TEXT,
        created_at       TEXT NOT NULL,
        claimed_at       TEXT,
        reviewed_at      TEXT
    )"
);
echo "checkride_requests table ok\n";

// Seed starter certifications kalau belum ada sama sekali.
$count = (int)$pdo->query('SELECT COUNT(*) FROM certifications')->fetchColumn();
if ($count === 0) {
    $seed = [
        ['BASIC', 'Basic Checkride', 'Prosedur radio & pattern dasar di bandara non-towered/towered kecil.'],
        ['RADIO_COMM', 'Radio Communication', 'Kelancaran & kepatuhan fraseologi radio ATC standar.'],
        ['TAXI_PROC', 'Taxi Procedure', 'Kepatuhan prosedur taxi & ground movement sesuai clearance.'],
        ['INSTRUCTOR_CERT', 'Instructor Certification', 'Syarat untuk bisa ditugaskan sebagai instructor Training Academy.'],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO certifications (code, name, description, created_at) VALUES (:c, :n, :d, :ts)'
    );
    foreach ($seed as [$code, $name, $desc]) {
        $stmt->execute([':c' => $code, ':n' => $name, ':d' => $desc, ':ts' => date('Y-m-d H:i:s')]);
    }
    echo "seeded " . count($seed) . " starter certifications\n";
} else {
    echo "certifications already seeded ($count rows), skip\n";
}

echo "Migration done.\n";
