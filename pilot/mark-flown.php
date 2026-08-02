<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/xp.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pilot/dashboard.php');
}
csrf_check();

$u = current_user();
$pdo = db();
$id = (int)post('id');

$stmt = $pdo->prepare("SELECT * FROM flight_requests WHERE id = :id");
$stmt->execute([':id' => $id]);
$r = $stmt->fetch();

// Hanya pemilik & hanya dari status dispatched.
if (!$r || (int)$r['pilot_id'] !== (int)$u['id']) {
    http_response_code(403);
    flash('Request tidak ditemukan / bukan milikmu.', 'error');
    redirect('/pilot/dashboard.php');
}
if ($r['status'] !== 'dispatched') {
    flash('Flight hanya bisa ditandai selesai dari status dispatched (sekarang: ' . $r['status'] . ').', 'error');
    redirect('/pilot/request-view.php?id=' . $id);
}

$xp = xp_for_flight((float)$r['distance_nm']);

try {
    $pdo->beginTransaction();

    // Guard di level SQL: hanya jika masih dispatched (cegah double submit).
    $upd = $pdo->prepare(
        "UPDATE flight_requests SET status = 'completed', completed_at = :ts
         WHERE id = :id AND status = 'dispatched'"
    );
    $upd->execute([':ts' => now_iso(), ':id' => $id]);

    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        flash('Flight sudah tercatat sebelumnya.', 'error');
        redirect('/pilot/request-view.php?id=' . $id);
    }

    $log = $pdo->prepare(
        "INSERT INTO logbook (pilot_id, request_id, dep_icao, arr_icao, aircraft, distance_nm, xp_awarded, filed_at)
         VALUES (:pid, :rid, :dep, :arr, :ac, :dist, :xp, :ts)"
    );
    $log->execute([
        ':pid'  => $u['id'],
        ':rid'  => $id,
        ':dep'  => $r['dep_icao'],
        ':arr'  => $r['arr_icao'],
        ':ac'   => $r['aircraft'],
        ':dist' => $r['distance_nm'],
        ':xp'   => $xp,
        ':ts'   => now_iso(),
    ]);

    $pdo->prepare("UPDATE users SET xp = xp + :xp WHERE id = :id")
        ->execute([':xp' => $xp, ':id' => $u['id']]);

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $ex;
}

flash('Flight tercatat di logbook! +' . $xp . ' XP 🎉', 'success');
redirect('/pilot/request-view.php?id=' . $id);
