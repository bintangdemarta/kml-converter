<?php
/*
|--------------------------------------------------------------------------
| XP & Rank
|--------------------------------------------------------------------------
| Formula sederhana berbasis jarak penerbangan (NM). Dipakai saat pilot
| menandai flight selesai (mark-as-flown).
*/

/** XP untuk satu leg berdasarkan jarak. Minimal 10 XP. */
function xp_for_flight(float $distanceNm): int
{
    return (int)max(10, round($distanceNm / 10) + 10);
}

/** Daftar rank berdasarkan akumulasi XP (ambang bawah => nama rank). */
function rank_tiers(): array
{
    return [
        0     => 'Cadet',
        100   => 'Second Officer',
        300   => 'First Officer',
        700   => 'Senior First Officer',
        1500  => 'Captain',
        3000  => 'Senior Captain',
        6000  => 'Training Captain',
    ];
}

/** Nama rank untuk XP tertentu. */
function rank_for_xp(int $xp): string
{
    $rank = 'Cadet';
    foreach (rank_tiers() as $threshold => $name) {
        if ($xp >= $threshold) {
            $rank = $name;
        }
    }
    return $rank;
}

/** Info progres ke rank berikutnya: [current, next, xpToNext, percent]. */
function rank_progress(int $xp): array
{
    $tiers = rank_tiers();
    $thresholds = array_keys($tiers);
    $current = 0;
    $next = null;

    foreach ($thresholds as $t) {
        if ($xp >= $t) {
            $current = $t;
        } elseif ($next === null) {
            $next = $t;
        }
    }

    if ($next === null) {
        return [
            'current_rank' => rank_for_xp($xp),
            'next_rank'    => null,
            'xp_to_next'   => 0,
            'percent'      => 100,
        ];
    }

    $span = $next - $current;
    $done = $xp - $current;
    return [
        'current_rank' => $tiers[$current],
        'next_rank'    => $tiers[$next],
        'xp_to_next'   => $next - $xp,
        'percent'      => $span > 0 ? (int)round($done / $span * 100) : 0,
    ];
}
