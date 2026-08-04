<?php
/*
|--------------------------------------------------------------------------
| Migration: Community & Events
|--------------------------------------------------------------------------
| Satu sistem untuk event (one-time gathering) dan campaign (challenge
| berdurasi) - dibedakan lewat kolom "type". Partisipasi dicatat seperti
| sistem absen: pilot RSVP dulu, manager tandai hadir/tidak setelahnya.
|
| Jalankan sekali: php data/migrate-community-events.php
*/

require_once __DIR__ . '/../config/db.php';

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS community_events (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        type         TEXT NOT NULL DEFAULT 'event',
        title        TEXT NOT NULL,
        description  TEXT,
        airport_icao TEXT,
        start_at     TEXT NOT NULL,
        end_at       TEXT,
        created_by   INTEGER NOT NULL REFERENCES users(id),
        created_at   TEXT NOT NULL
    )"
);
echo "community_events table ok\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS event_participants (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id   INTEGER NOT NULL REFERENCES community_events(id),
        pilot_id   INTEGER NOT NULL REFERENCES users(id),
        rsvp_at    TEXT NOT NULL,
        attended   INTEGER,
        marked_by  INTEGER REFERENCES users(id),
        marked_at  TEXT,
        UNIQUE(event_id, pilot_id)
    )"
);
echo "event_participants table ok\n";

echo "Migration done.\n";
