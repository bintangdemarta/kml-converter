<?php
/*
|--------------------------------------------------------------------------
| Indonesia Database — enum helpers, province lookup, import safety
|--------------------------------------------------------------------------
| Dipakai modul Indonesia Database (database/*.php, data/import-*.php).
*/

/** Metadata kategori: label tampilan, nama tabel, bentuk geometri. */
function indonesia_categories(): array
{
    return [
        'airport'         => ['label' => 'Airport', 'table' => 'airports', 'geom' => 'point'],
        'airspace'        => ['label' => 'Airspace', 'table' => 'airspaces', 'geom' => 'polygon'],
        'reporting_point' => ['label' => 'Reporting Point', 'table' => 'reporting_points', 'geom' => 'point'],
        'landmark'        => ['label' => 'Landmark', 'table' => 'landmarks', 'geom' => 'point'],
    ];
}

function airport_types(): array
{
    return ['large_airport', 'medium_airport', 'small_airport', 'heliport', 'seaplane_base', 'closed'];
}

function airport_type_label(string $type): string
{
    $labels = [
        'large_airport' => 'Large Airport', 'medium_airport' => 'Medium Airport',
        'small_airport' => 'Small Airport', 'heliport' => 'Heliport',
        'seaplane_base' => 'Seaplane Base', 'closed' => 'Closed',
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function airport_type_color(string $type): string
{
    $colors = [
        'large_airport' => '#ef4444', 'medium_airport' => '#f59e0b',
        'small_airport' => '#22c55e', 'heliport' => '#38bdf8',
        'seaplane_base' => '#a78bfa', 'closed' => '#64748b',
    ];
    return $colors[$type] ?? '#94a3b8';
}

function reporting_point_types(): array
{
    return ['vor', 'ndb', 'fix', 'dme', 'waypoint'];
}

function reporting_point_type_label(string $type): string
{
    return strtoupper($type) === $type ? strtoupper($type) : ucfirst($type);
}

function reporting_point_type_color(string $type): string
{
    $colors = ['vor' => '#f59e0b', 'ndb' => '#a78bfa', 'fix' => '#38bdf8', 'dme' => '#22c55e', 'waypoint' => '#94a3b8'];
    return $colors[$type] ?? '#94a3b8';
}

function airspace_types(): array
{
    return ['FIR', 'UIR', 'CTR', 'TMA', 'Restricted', 'Danger', 'Prohibited', 'other'];
}

function airspace_type_color(string $type): string
{
    $colors = [
        'FIR' => '#38bdf8', 'UIR' => '#0ea5e9', 'CTR' => '#22c55e', 'TMA' => '#a78bfa',
        'Restricted' => '#f59e0b', 'Danger' => '#ef4444', 'Prohibited' => '#dc2626', 'other' => '#94a3b8',
    ];
    return $colors[$type] ?? '#94a3b8';
}

function landmark_types(): array
{
    return ['mountain', 'lake', 'city', 'coastline', 'other'];
}

function landmark_type_label(string $type): string
{
    $labels = ['mountain' => 'Mountain', 'lake' => 'Lake', 'city' => 'City', 'coastline' => 'Coastline', 'other' => 'Other'];
    return $labels[$type] ?? ucfirst($type);
}

function landmark_type_color(string $type): string
{
    $colors = ['mountain' => '#a16207', 'lake' => '#0ea5e9', 'city' => '#ef4444', 'coastline' => '#22c55e', 'other' => '#94a3b8'];
    return $colors[$type] ?? '#94a3b8';
}

/**
 * Lookup kode ISO 3166-2:ID -> nama provinsi. Kode yang tidak dikenal
 * (mis. provinsi hasil DOB Papua 2022 yang belum diverifikasi kodenya)
 * di-fallback ke raw code apa adanya oleh id_province_name() - JANGAN
 * dibuang, supaya data tidak hilang hanya karena lookup belum lengkap.
 */
function id_province_lookup(): array
{
    return [
        'ID-AC' => 'Aceh', 'ID-BA' => 'Bali', 'ID-BB' => 'Kepulauan Bangka Belitung',
        'ID-BE' => 'Bengkulu', 'ID-BT' => 'Banten', 'ID-GO' => 'Gorontalo',
        'ID-JA' => 'Jambi', 'ID-JB' => 'Jawa Barat', 'ID-JI' => 'Jawa Timur',
        'ID-JK' => 'DKI Jakarta', 'ID-JT' => 'Jawa Tengah', 'ID-KB' => 'Kalimantan Barat',
        'ID-KI' => 'Kalimantan Timur', 'ID-KR' => 'Kepulauan Riau', 'ID-KS' => 'Kalimantan Selatan',
        'ID-KT' => 'Kalimantan Tengah', 'ID-KU' => 'Kalimantan Utara', 'ID-LA' => 'Lampung',
        'ID-MA' => 'Maluku', 'ID-MU' => 'Maluku Utara', 'ID-NB' => 'Nusa Tenggara Barat',
        'ID-NT' => 'Nusa Tenggara Timur', 'ID-PA' => 'Papua', 'ID-PB' => 'Papua Barat',
        'ID-RI' => 'Riau', 'ID-SA' => 'Sulawesi Utara', 'ID-SB' => 'Sumatera Barat',
        'ID-SG' => 'Sulawesi Tenggara', 'ID-SN' => 'Sulawesi Selatan', 'ID-SR' => 'Sulawesi Barat',
        'ID-SS' => 'Sumatera Selatan', 'ID-ST' => 'Sulawesi Tengah', 'ID-SU' => 'Sumatera Utara',
        'ID-YO' => 'DI Yogyakarta',
        // Provinsi hasil DOB Papua (2022-2023), kode diverifikasi dari data riil OurAirports
        // (dicek municipality tiap bandara per kode, bukan ditebak):
        'ID-PD' => 'Papua Barat Daya',   // Werur, Marinda (Waisai/Raja Ampat), Teminabuan
        'ID-PP' => 'Papua Pegunungan',   // Wamena, Oksibil, Dekai
        'ID-PS' => 'Papua Selatan',      // Merauke, Tanah Merah, Bade
        'ID-PT' => 'Papua Tengah',       // Timika, Nabire
        // ID-U-A SENGAJA tidak dipetakan - satu-satunya bandara berkode ini ("(Duplicate)Ranai
        // Airport") adalah artefak duplikat data di OurAirports sendiri, bukan provinsi asli.
        // Fallback raw-code di id_province_name() menangani kode lain yang belum dikenal.
    ];
}

function id_province_name(string $isoRegion): string
{
    return id_province_lookup()[$isoRegion] ?? $isoRegion;
}

/**
 * Cek apakah sudah ada row MANUAL (dibuat manager lewat UI, source='manual')
 * dengan identifier yang sama. Dipakai import script sebelum INSERT baru,
 * supaya tidak membuat entri duplikat visual terhadap data yang manager
 * sudah input sendiri. $table dan $identColumn berasal dari konstanta
 * internal (BUKAN input user) - aman diinterpolasi ke SQL di sini, TAPI
 * fungsi ini tidak boleh dipanggil dengan nilai dinamis dari request HTTP.
 */
function indonesia_find_conflicting_manual(PDO $pdo, string $table, string $identColumn, string $identValue): ?array
{
    if ($identValue === '') {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $identColumn = :v AND source = 'manual' LIMIT 1");
    $stmt->execute([':v' => $identValue]);
    return $stmt->fetch() ?: null;
}
