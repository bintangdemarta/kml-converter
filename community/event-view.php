<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();

$u = current_user();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('_action');

    if ($action === 'rsvp') {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO event_participants (event_id, pilot_id, rsvp_at)
             VALUES (:eid, :pid, :ts)'
        );
        $stmt->execute([':eid' => $id, ':pid' => $u['id'], ':ts' => now_iso()]);
        flash('RSVP tercatat.', 'success');
    } elseif ($action === 'cancel_rsvp') {
        $pdo->prepare('DELETE FROM event_participants WHERE event_id = :eid AND pilot_id = :pid')
            ->execute([':eid' => $id, ':pid' => $u['id']]);
        flash('RSVP dibatalkan.', 'success');
    } elseif ($action === 'mark_attendance' && is_manager()) {
        $participantId = (int)post('participant_id');
        $attended = post('attended') === '1' ? 1 : 0;
        $pdo->prepare(
            'UPDATE event_participants SET attended = :att, marked_by = :mb, marked_at = :ts WHERE id = :pid'
        )->execute([':att' => $attended, ':mb' => $u['id'], ':ts' => now_iso(), ':pid' => $participantId]);
        flash('Absensi diupdate.', 'success');
    }
    redirect('/community/event-view.php?id=' . $id);
}

$stmt = $pdo->prepare(
    "SELECT ce.*, u.username AS creator_name FROM community_events ce
     JOIN users u ON u.id = ce.created_by WHERE ce.id = :id"
);
$stmt->execute([':id' => $id]);
$ev = $stmt->fetch();

if (!$ev) {
    flash('Event/campaign tidak ditemukan.', 'error');
    redirect('/community/index.php');
}

$participants = $pdo->prepare(
    "SELECT ep.*, u.username FROM event_participants ep
     JOIN users u ON u.id = ep.pilot_id WHERE ep.event_id = :id ORDER BY ep.rsvp_at ASC"
);
$participants->execute([':id' => $id]);
$participants = $participants->fetchAll();

$myRsvp = null;
foreach ($participants as $p) {
    if ((int)$p['pilot_id'] === (int)$u['id']) {
        $myRsvp = $p;
        break;
    }
}

$page_title = $ev['title'];
require __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2><?= e($ev['title']) ?> <span class="badge pending" style="margin-left:8px;"><?= e(ucfirst($ev['type'])) ?></span></h2>
    <p class="muted">
        Dibuat oleh <?= e($ev['creator_name']) ?> &middot;
        Mulai: <?= e($ev['start_at']) ?>
        <?php if ($ev['end_at']): ?> &middot; Selesai: <?= e($ev['end_at']) ?><?php endif; ?>
        <?php if ($ev['airport_icao']): ?> &middot; Bandara: <?= e($ev['airport_icao']) ?><?php endif; ?>
    </p>
    <?php if ($ev['description']): ?>
        <p><?= nl2br(e($ev['description'])) ?></p>
    <?php endif; ?>

    <form method="post" style="display:inline;margin:0;">
        <?= csrf_field() ?>
        <?php if ($myRsvp): ?>
            <input type="hidden" name="_action" value="cancel_rsvp">
            <button type="submit" class="btn-danger">Batalkan RSVP</button>
        <?php else: ?>
            <input type="hidden" name="_action" value="rsvp">
            <button type="submit" class="btn-ok">RSVP - Saya Ikut</button>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>Peserta (<?= count($participants) ?>)</h2>
    <?php if (!$participants): ?>
        <p class="muted">Belum ada yang RSVP.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Pilot</th><th>RSVP Pada</th><th>Kehadiran</th><?php if (is_manager()): ?><th>Aksi</th><?php endif; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $p): ?>
                    <tr>
                        <td><?= e($p['username']) ?></td>
                        <td><?= e($p['rsvp_at']) ?></td>
                        <td>
                            <?php if ($p['attended'] === null): ?>
                                <span class="muted">Belum ditandai</span>
                            <?php elseif ((int)$p['attended'] === 1): ?>
                                <span class="badge passed">Hadir</span>
                            <?php else: ?>
                                <span class="badge failed">Tidak Hadir</span>
                            <?php endif; ?>
                        </td>
                        <?php if (is_manager()): ?>
                            <td style="white-space:nowrap;">
                                <form method="post" style="display:inline;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="mark_attendance">
                                    <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="attended" value="1">
                                    <button class="btn-ok" style="margin:0;padding:6px 10px;">Hadir</button>
                                </form>
                                <form method="post" style="display:inline;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="mark_attendance">
                                    <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="attended" value="0">
                                    <button class="btn-danger" style="margin:0;padding:6px 10px;">Tidak Hadir</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
