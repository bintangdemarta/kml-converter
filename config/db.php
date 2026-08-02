<?php
/*
|--------------------------------------------------------------------------
| Database — SQLite (PDO)
|--------------------------------------------------------------------------
| Koneksi tunggal + inisialisasi schema (idempotent: CREATE IF NOT EXISTS).
| DB file dibuat otomatis di data/flightops.sqlite saat pertama diakses.
*/

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dataDir . '/flightops.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_schema($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT UNIQUE NOT NULL,
            email         TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role          TEXT NOT NULL DEFAULT 'pilot',
            xp            INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS flight_requests (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            pilot_id       INTEGER NOT NULL REFERENCES users(id),
            callsign       TEXT NOT NULL,
            aircraft       TEXT NOT NULL,
            dep_icao       TEXT NOT NULL,
            arr_icao       TEXT NOT NULL,
            route          TEXT,
            cruise_alt     INTEGER,
            flight_rules   TEXT NOT NULL DEFAULT 'IFR',
            distance_nm    REAL NOT NULL DEFAULT 0,
            remarks        TEXT,
            status         TEXT NOT NULL DEFAULT 'pending',
            manager_id     INTEGER REFERENCES users(id),
            review_note    TEXT,
            created_at     TEXT NOT NULL,
            reviewed_at    TEXT,
            dispatched_at  TEXT,
            completed_at   TEXT
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logbook (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            pilot_id     INTEGER NOT NULL REFERENCES users(id),
            request_id   INTEGER NOT NULL REFERENCES flight_requests(id),
            dep_icao     TEXT NOT NULL,
            arr_icao     TEXT NOT NULL,
            aircraft     TEXT NOT NULL,
            distance_nm  REAL NOT NULL DEFAULT 0,
            xp_awarded   INTEGER NOT NULL DEFAULT 0,
            filed_at     TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS routes (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            name         TEXT NOT NULL,
            category     TEXT NOT NULL DEFAULT 'Route',
            icao         TEXT,
            waypoints    TEXT NOT NULL,
            distance_nm  REAL NOT NULL DEFAULT 0,
            notes        TEXT,
            owner_id     INTEGER NOT NULL REFERENCES users(id),
            created_at   TEXT NOT NULL,
            updated_at   TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS taxi_nodes (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            icao         TEXT NOT NULL,
            ref_code     TEXT,
            name         TEXT NOT NULL,
            type         TEXT NOT NULL,
            lat          REAL NOT NULL,
            lon          REAL NOT NULL,
            notes        TEXT,
            created_at   TEXT NOT NULL,
            updated_at   TEXT NOT NULL
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_taxi_nodes_icao ON taxi_nodes(icao)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS taxi_edges (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            icao           TEXT NOT NULL,
            from_node_id   INTEGER NOT NULL REFERENCES taxi_nodes(id) ON DELETE CASCADE,
            to_node_id     INTEGER NOT NULL REFERENCES taxi_nodes(id) ON DELETE CASCADE,
            taxiway_name   TEXT,
            bidirectional  INTEGER NOT NULL DEFAULT 1,
            distance_m     REAL NOT NULL DEFAULT 0,
            created_at     TEXT NOT NULL
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_taxi_edges_icao ON taxi_edges(icao)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_taxi_edges_from ON taxi_edges(from_node_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_taxi_edges_to ON taxi_edges(to_node_id)');
}
