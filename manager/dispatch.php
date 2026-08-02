<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('manager', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/manager/queue.php');
}
csrf_check();

$u = current_user();
$pdo = db();
$id = (int)post('id');

$stmt = $pdo->prepare("SELECT * FROM flight_requests WHERE id = :id");
$stmt->execute([':id' => $id]);
$r = $stmt->fetch();

if (!$r) {
    flash('Request tidak ditemukan.', 'error');
    redirect('/manager/queue.php');
}
if ($r['status'] !== 'approved') {
    flash('Hanya request berstatus approved yang bisa di-dispatch (status sekarang: ' . $r['status'] . ').', 'error');
    redirect('/manager/queue.php');
}

$upd = $pdo->prepare(
    "UPDATE flight_requests
     SET status = 'dispatched', dispatched_at = :ts, manager_id = :mid
     WHERE id = :id AND status = 'approved'"
);
$upd->execute([':ts' => now_iso(), ':mid' => $u['id'], ':id' => $id]);

flash('Request #' . $id . ' dispatched. Pilot bisa mulai terbang.', 'success');
redirect('/manager/queue.php');
