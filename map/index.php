<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../parser.php'; // reuse calculateDistanceNM()
auth_boot();

$u = current_user();
$pdo = db();
$errors = [];
$old = null;

/*
|--------------------------------------------------------------------------
| POST: create / update route
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();

    $id       = (int)post('id'); // 0 = create
    $name     = post('name');
    $category = post('category', 'Route');
    $icao     = strtoupper(post('icao'));
    $notes    = post('notes');
    $wpJson   = $_POST['waypoints_json'] ?? '';

    if (!in_array($category, route_categories(), true)) {
        $category = 'Route';
    }
    if ($name === '') {
        $errors[] = 'Nama rute wajib diisi.';
    }
    if ($icao !== '' && !preg_match('/^[A-Z]{4}$/', $icao)) {
        $errors[] = 'ICAO harus 4 huruf (mis. WIII), atau kosongkan.';
    }

    $points = json_decode($wpJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($points)) {
        $errors[] = 'Data waypoint rusak — coba klik ulang di peta.';
        $points = [];
    } elseif (count($points) < 2) {
        $errors[] = 'Rute butuh minimal 2 titik.';
    } elseif (count($points) > 500) {
        $errors[] = 'Terlalu banyak titik (maks 500).';
    } else {
        foreach ($points as $p) {
            $lat = $p['lat'] ?? null;
            $lon = $p['lon'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $errors[] = 'Ada titik dengan koordinat tidak valid.';
                break;
            }
        }
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM routes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            $errors[] = 'Rute tidak ditemukan.';
        } elseif ((int)$existing['owner_id'] !== (int)$u['id'] && !is_manager()) {
            http_response_code(403);
            flash('Kamu tidak punya izin mengedit rute ini.', 'error');
            redirect('/map/index.php?id=' . $id);
        }
    }

    if (!$errors) {
        $coords = array_map(
            fn($p) => round((float)$p['lat'], 6) . ',' . round((float)$p['lon'], 6),
            $points
        );
        $distance = calculateDistanceNM($coords);
        $cleanPoints = array_map(
            fn($p) => ['lat' => round((float)$p['lat'], 6), 'lon' => round((float)$p['lon'], 6)],
            $points
        );
        $waypointsJson = json_encode($cleanPoints);

        if ($id > 0) {
            $pdo->prepare(
                'UPDATE routes SET name=:n, category=:c, icao=:i, waypoints=:w, distance_nm=:d, notes=:nt, updated_at=:u WHERE id=:id'
            )->execute([
                ':n' => $name, ':c' => $category, ':i' => $icao !== '' ? $icao : null,
                ':w' => $waypointsJson, ':d' => $distance, ':nt' => $notes !== '' ? $notes : null,
                ':u' => now_iso(), ':id' => $id,
            ]);
            flash('Rute diperbarui (' . $distance . ' NM).', 'success');
            redirect('/map/index.php?id=' . $id);
        } else {
            $pdo->prepare(
                'INSERT INTO routes (name,category,icao,waypoints,distance_nm,notes,owner_id,created_at,updated_at)
                 VALUES (:n,:c,:i,:w,:d,:nt,:o,:ct,:ut)'
            )->execute([
                ':n' => $name, ':c' => $category, ':i' => $icao !== '' ? $icao : null,
                ':w' => $waypointsJson, ':d' => $distance, ':nt' => $notes !== '' ? $notes : null,
                ':o' => $u['id'], ':ct' => now_iso(), ':ut' => now_iso(),
            ]);
            $newId = (int)$pdo->lastInsertId();
            flash('Rute tersimpan (' . $distance . ' NM).', 'success');
            redirect('/map/index.php?id=' . $newId);
        }
    }

    // Validasi gagal: render ulang form dengan state dipertahankan (JANGAN redirect,
    // supaya titik-titik yang sudah diplot pengguna di peta tidak hilang).
    $old = compact('id', 'name', 'category', 'icao', 'notes');
    $old['waypoints_json'] = $wpJson;
}

/*
|--------------------------------------------------------------------------
| GET: filter list (browse sidebar)
|--------------------------------------------------------------------------
*/
$fCategory = $_GET['category'] ?? '';
$fIcao     = strtoupper(trim($_GET['icao'] ?? ''));
$fQ        = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($fCategory !== '' && in_array($fCategory, route_categories(), true)) {
    $where[] = 'category = :cat';
    $params[':cat'] = $fCategory;
}
if ($fIcao !== '') {
    $where[] = 'icao = :icao';
    $params[':icao'] = $fIcao;
}
if ($fQ !== '') {
    $where[] = 'name LIKE :q';
    $params[':q'] = '%' . $fQ . '%';
}

$sql = 'SELECT r.*, u.username AS owner_name FROM routes r JOIN users u ON u.id = r.owner_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.updated_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$routes = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Tentukan mode & rute aktif
|--------------------------------------------------------------------------
*/
$mode = 'browse';
$activeRoute = null;
$viewId = (int)($_GET['id'] ?? 0);

if ($old !== null) {
    // Habis POST gagal validasi: tetap di mode create/edit dengan state $old.
    $mode = $old['id'] > 0 ? 'edit' : 'create';
} elseif ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT r.*, u.username AS owner_name FROM routes r JOIN users u ON u.id = r.owner_id WHERE r.id = :id');
    $stmt->execute([':id' => $viewId]);
    $activeRoute = $stmt->fetch();
    if ($activeRoute) {
        $canEdit = $u && ((int)$activeRoute['owner_id'] === (int)$u['id'] || is_manager());
        $mode = (isset($_GET['edit']) && $canEdit) ? 'edit' : 'view';
    } else {
        flash('Rute #' . $viewId . ' tidak ditemukan.', 'error');
    }
} elseif (isset($_GET['new']) && $u) {
    $mode = 'create';
}

$canEditActive = $u && $activeRoute && ((int)$activeRoute['owner_id'] === (int)$u['id'] || is_manager());

$page_title = 'Map Dashboard';
require __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
    .wrap { max-width: 1400px; }
    .map-layout { display: flex; gap: 20px; flex-wrap: wrap; }
    .map-sidebar { flex: 0 0 320px; }
    .map-sidebar .filters { margin-bottom: 14px; }
    .map-sidebar .filters select, .map-sidebar .filters input { margin-bottom: 8px; }
    .route-list { max-height: 640px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; }
    .route-item { display: block; padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text); text-decoration: none; }
    .route-item:hover { background: var(--panel-2); text-decoration: none; }
    .route-item.active { background: var(--panel-2); border-left: 3px solid var(--accent); }
    .route-item .rname { font-weight: 600; color: #fff; }
    .route-item .rmeta { font-size: 12px; color: var(--muted); margin-top: 3px; }
    .map-main { flex: 1; min-width: 340px; }
    #map { height: 560px; border-radius: 10px; margin-top: 12px; }
    .cat-chip {
        font-size: 11px; text-transform: uppercase; letter-spacing: .4px;
        background: var(--panel-2); color: var(--accent); padding: 2px 8px; border-radius: 20px;
    }
</style>

<div class="map-layout">
    <div class="map-sidebar">
        <div class="card">
            <h3 style="margin-top:0;">Browse Routes</h3>
            <form method="get" class="filters">
                <label>Kategori</label>
                <select name="category" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <?php foreach (route_categories() as $c): ?>
                        <option value="<?= e($c) ?>" <?= $fCategory === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>ICAO</label>
                <input name="icao" value="<?= e($fIcao) ?>" placeholder="WIII" maxlength="4" style="text-transform:uppercase">
                <label>Cari nama</label>
                <input name="q" value="<?= e($fQ) ?>" placeholder="Cari rute...">
                <button type="submit" class="btn-ghost">Filter</button>
            </form>

            <?php if ($u): ?>
                <a class="btn" href="<?= url('/map/index.php?new=1') ?>" style="width:100%;text-align:center;box-sizing:border-box;">+ New Route</a>
            <?php endif; ?>

            <div class="route-list" style="margin-top:14px;">
                <?php if (!$routes): ?>
                    <div style="padding:14px;" class="muted">Belum ada rute pada filter ini.</div>
                <?php endif; ?>
                <?php foreach ($routes as $r): ?>
                    <a class="route-item <?= $activeRoute && (int)$activeRoute['id'] === (int)$r['id'] ? 'active' : '' ?>"
                       href="<?= url('/map/index.php?id=' . (int)$r['id']) ?>">
                        <span class="rname"><?= e($r['name']) ?></span>
                        <span class="cat-chip" style="margin-left:6px;"><?= e($r['category']) ?></span>
                        <div class="rmeta">
                            <?= $r['icao'] ? e($r['icao']) . ' · ' : '' ?><?= number_format((float)$r['distance_nm'], 1) ?> NM · by <?= e($r['owner_name']) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="map-main">
        <div class="card">
            <?php foreach ($errors as $err): ?>
                <div class="flash error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <?php if ($mode === 'browse'): ?>
                <h2 style="margin-top:0;">Live Map Dashboard</h2>
                <p class="muted">Pilih rute di daftar sebelah kiri, atau klik <b>+ New Route</b> untuk membuat rute baru. Klik peta untuk menambah waypoint, drag titik untuk reposisi.</p>
            <?php elseif ($mode === 'view'): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h2 style="margin:0;"><?= e($activeRoute['name']) ?></h2>
                    <span class="cat-chip"><?= e($activeRoute['category']) ?></span>
                </div>
                <p class="muted" style="margin:6px 0 0;">
                    <?= $activeRoute['icao'] ? e($activeRoute['icao']) . ' · ' : '' ?>
                    <?= number_format((float)$activeRoute['distance_nm'], 1) ?> NM · dibuat oleh <?= e($activeRoute['owner_name']) ?>
                </p>
                <?php if ($activeRoute['notes']): ?>
                    <p style="margin-top:10px;"><?= nl2br(e($activeRoute['notes'])) ?></p>
                <?php endif; ?>

                <?php if ($canEditActive): ?>
                    <div style="margin-top:14px;display:flex;gap:10px;">
                        <a class="btn" href="<?= url('/map/index.php?id=' . (int)$activeRoute['id'] . '&edit=1') ?>">Edit Route</a>
                        <form method="post" action="<?= url('/map/delete.php') ?>"
                              onsubmit="return confirm('Hapus rute ini? Tindakan tidak bisa dibatalkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$activeRoute['id'] ?>">
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    </div>
                <?php endif; ?>

                <label style="margin-top:16px;">Output <span class="muted">(siap paste ke Infinite Flight)</span></label>
                <textarea id="output" readonly></textarea>
            <?php elseif ($mode === 'create' || $mode === 'edit'): ?>
                <h2 style="margin-top:0;"><?= $mode === 'edit' ? 'Edit Route' : 'New Route' ?></h2>
                <p class="muted">Klik peta untuk menambah titik, drag marker untuk reposisi, buka popup marker untuk hapus titik.</p>

                <form method="post" id="routeForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)($old['id'] ?? $activeRoute['id'] ?? 0) ?>">
                    <input type="hidden" name="waypoints_json" id="waypoints_json" value="">

                    <div class="row">
                        <div>
                            <label>Nama Rute</label>
                            <input name="name" value="<?= e($old['name'] ?? $activeRoute['name'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label>Kategori</label>
                            <select name="category">
                                <?php $curCat = $old['category'] ?? $activeRoute['category'] ?? 'Route'; ?>
                                <?php foreach (route_categories() as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $curCat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>ICAO <span class="muted">(opsional)</span></label>
                            <input name="icao" value="<?= e($old['icao'] ?? $activeRoute['icao'] ?? '') ?>" maxlength="4" style="text-transform:uppercase">
                        </div>
                    </div>

                    <label>Notes <span class="muted">(opsional)</span></label>
                    <textarea name="notes"><?= e($old['notes'] ?? $activeRoute['notes'] ?? '') ?></textarea>

                    <label>Output <span class="muted">(live, siap paste ke Infinite Flight)</span></label>
                    <textarea id="output" readonly></textarea>
                    <div id="distance-preview" class="muted" style="margin-top:6px;font-size:13px;"></div>

                    <button type="submit">Save Route</button>
                    <a class="btn btn-ghost" href="<?= url($mode === 'edit' && $activeRoute ? '/map/index.php?id=' . (int)$activeRoute['id'] : '/map/index.php') ?>">Batal</a>
                </form>
            <?php endif; ?>

            <div id="map"></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const mode = <?= json_encode($mode) ?>;
const editable = (mode === 'create' || mode === 'edit');

<?php
$initialWaypointsRaw = $old['waypoints_json'] ?? $activeRoute['waypoints'] ?? '[]';
?>
let points = [];
try {
    const raw = JSON.parse(<?= json_encode($initialWaypointsRaw) ?>);
    points = Array.isArray(raw) ? raw.map(p => ({ lat: +p.lat, lon: +p.lon })) : [];
} catch (e) { points = []; }

const map = L.map('map').setView(points[0] ? [points[0].lat, points[0].lon] : [-6.2, 106.8], points.length ? 8 : 4);

const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
    maxZoom: 19
}).addTo(map);
const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
});
L.control.layers({ 'Satellite (Esri)': sat, 'Street (OSM)': osm }).addTo(map);

let markers = [];
let polyline = null;

function redraw() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    if (polyline) { map.removeLayer(polyline); polyline = null; }

    if (points.length) {
        polyline = L.polyline(points.map(p => [p.lat, p.lon]), {
            color: '#38bdf8', weight: 3, interactive: false
        }).addTo(map);
    }

    points.forEach((p, idx) => {
        const marker = L.marker([p.lat, p.lon], { draggable: editable }).addTo(map);
        if (editable) {
            marker.bindPopup('Point ' + (idx + 1) + ' <button type="button" onclick="removePoint(' + idx + ')">Hapus</button>');
            marker.on('dragend', e => {
                const ll = e.target.getLatLng();
                points[idx] = { lat: ll.lat, lon: ll.lng };
                redraw();
            });
            marker.on('contextmenu', e => { L.DomEvent.preventDefault(e); removePoint(idx); });
        }
        markers.push(marker);
    });

    syncOutput();
}

function removePoint(idx) {
    points.splice(idx, 1);
    redraw();
}

function clientHaversineNM(pts) {
    let total = 0;
    const R = 6371;
    const toRad = d => d * Math.PI / 180;
    for (let i = 0; i < pts.length - 1; i++) {
        const dLat = toRad(pts[i + 1].lat - pts[i].lat);
        const dLon = toRad(pts[i + 1].lon - pts[i].lon);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(pts[i].lat)) * Math.cos(toRad(pts[i + 1].lat)) * Math.sin(dLon / 2) ** 2;
        total += R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)) * 0.539957;
    }
    return total;
}

function syncOutput() {
    const text = points.map(p => p.lat.toFixed(6) + ',' + p.lon.toFixed(6)).join(' ');
    const out = document.getElementById('output');
    if (out) out.value = text;
    const hid = document.getElementById('waypoints_json');
    if (hid) hid.value = JSON.stringify(points);
    const dist = document.getElementById('distance-preview');
    if (dist) {
        dist.textContent = points.length >= 2
            ? clientHaversineNM(points).toFixed(1) + ' NM (perkiraan, jarak final dihitung ulang saat disimpan)'
            : '';
    }
}

if (editable) {
    map.on('click', e => {
        points.push({ lat: e.latlng.lat, lon: e.latlng.lng });
        redraw();
    });
}

document.getElementById('routeForm')?.addEventListener('submit', e => {
    if (points.length < 2) {
        e.preventDefault();
        alert('Tambahkan minimal 2 titik di peta dulu.');
        return;
    }
    syncOutput();
});

redraw();
if (points.length) map.fitBounds(points.map(p => [p.lat, p.lon]));
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
