<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../parser.php'; // reuse calculateDistanceNM()
require_login();

$u = current_user();
$errors = [];
$old = [
    'callsign' => '', 'aircraft' => '', 'dep_icao' => '', 'arr_icao' => '',
    'route' => '', 'cruise_alt' => '', 'flight_rules' => 'IFR', 'remarks' => '',
];

/** Parse route "LAT,LON LAT,LON ..." dan hitung jarak NM via helper existing. */
function route_distance_nm(string $route): float
{
    $parts = preg_split('/\s+/', trim($route), -1, PREG_SPLIT_NO_EMPTY);
    $coords = [];
    foreach ($parts as $p) {
        $xy = explode(',', $p);
        if (count($xy) >= 2 && is_numeric(trim($xy[0])) && is_numeric(trim($xy[1]))) {
            $coords[] = trim($xy[0]) . ',' . trim($xy[1]);
        }
    }
    return count($coords) >= 2 ? (float)calculateDistanceNM($coords) : 0.0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($old as $k => $_) {
        $old[$k] = post($k, $old[$k]);
    }

    $old['dep_icao'] = strtoupper($old['dep_icao']);
    $old['arr_icao'] = strtoupper($old['arr_icao']);
    if (!in_array($old['flight_rules'], ['IFR', 'VFR'], true)) {
        $old['flight_rules'] = 'IFR';
    }

    // Validasi
    foreach (['callsign' => 'Callsign', 'aircraft' => 'Aircraft', 'dep_icao' => 'Departure', 'arr_icao' => 'Arrival'] as $f => $label) {
        if ($old[$f] === '') {
            $errors[] = "$label wajib diisi.";
        }
    }
    if ($old['dep_icao'] !== '' && !preg_match('/^[A-Z]{4}$/', $old['dep_icao'])) {
        $errors[] = 'Departure harus kode ICAO 4 huruf (mis. WIII).';
    }
    if ($old['arr_icao'] !== '' && !preg_match('/^[A-Z]{4}$/', $old['arr_icao'])) {
        $errors[] = 'Arrival harus kode ICAO 4 huruf (mis. WADD).';
    }

    $distance = route_distance_nm($old['route']);

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO flight_requests
             (pilot_id, callsign, aircraft, dep_icao, arr_icao, route, cruise_alt, flight_rules, distance_nm, remarks, status, created_at)
             VALUES (:pid, :cs, :ac, :dep, :arr, :rt, :alt, :fr, :dist, :rem, :st, :ct)'
        );
        $stmt->execute([
            ':pid'  => $u['id'],
            ':cs'   => $old['callsign'],
            ':ac'   => $old['aircraft'],
            ':dep'  => $old['dep_icao'],
            ':arr'  => $old['arr_icao'],
            ':rt'   => $old['route'],
            ':alt'  => $old['cruise_alt'] !== '' ? (int)$old['cruise_alt'] : null,
            ':fr'   => $old['flight_rules'],
            ':dist' => $distance,
            ':rem'  => $old['remarks'],
            ':st'   => 'pending',
            ':ct'   => now_iso(),
        ]);
        $id = (int)db()->lastInsertId();
        flash('Flight request diajukan (' . number_format($distance, 1) . ' NM). Menunggu review manager.', 'success');
        redirect('/pilot/request-view.php?id=' . $id);
    }
}

$page_title = 'New Flight Request';
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:640px;margin:0 auto;">
    <h2>New Flight Request</h2>
    <p class="muted">Jarak dihitung otomatis dari route (format Infinite Flight: <code>LAT,LON LAT,LON</code>). Butuh rute? Pakai <a href="<?= url('/index.php') ?>">Converter</a>.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="row">
            <div>
                <label>Callsign</label>
                <input name="callsign" value="<?= e($old['callsign']) ?>" placeholder="IFFI123" required>
            </div>
            <div>
                <label>Aircraft</label>
                <input name="aircraft" value="<?= e($old['aircraft']) ?>" placeholder="A320" required>
            </div>
        </div>
        <div class="row">
            <div>
                <label>Departure (ICAO)</label>
                <input name="dep_icao" value="<?= e($old['dep_icao']) ?>" placeholder="WIII" maxlength="4" style="text-transform:uppercase" required>
            </div>
            <div>
                <label>Arrival (ICAO)</label>
                <input name="arr_icao" value="<?= e($old['arr_icao']) ?>" placeholder="WADD" maxlength="4" style="text-transform:uppercase" required>
            </div>
        </div>
        <div class="row">
            <div>
                <label>Cruise Altitude (ft)</label>
                <input name="cruise_alt" value="<?= e($old['cruise_alt']) ?>" placeholder="36000" inputmode="numeric">
            </div>
            <div>
                <label>Flight Rules</label>
                <select name="flight_rules">
                    <option value="IFR" <?= $old['flight_rules'] === 'IFR' ? 'selected' : '' ?>>IFR</option>
                    <option value="VFR" <?= $old['flight_rules'] === 'VFR' ? 'selected' : '' ?>>VFR</option>
                </select>
            </div>
        </div>

        <label>Route <span class="muted">(opsional, untuk hitung jarak & XP)</span></label>
        <textarea name="route" placeholder="-6.125,106.655 -8.744,115.167"><?= e($old['route']) ?></textarea>

        <label>Remarks</label>
        <textarea name="remarks" placeholder="Catatan tambahan..."><?= e($old['remarks']) ?></textarea>

        <button type="submit">Submit Request</button>
    </form>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
