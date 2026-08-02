<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/indonesia_db.php';
auth_boot();

$u = current_user();
$canEdit = is_manager();
$pdo = db();

$cats = indonesia_categories();
$cat = $_GET['cat'] ?? 'airport';
if (!isset($cats[$cat])) $cat = 'airport';

$items = [];
$mode = 'browse'; // browse|create|edit
$editItem = null;

if ($cat === 'airport') {
    $q = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? '';
    $province = $_GET['province'] ?? '';

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(name LIKE :q OR icao LIKE :q OR iata LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    if ($type !== '' && in_array($type, airport_types(), true)) {
        $where[] = 'type = :t';
        $params[':t'] = $type;
    }
    if ($province !== '') {
        $where[] = 'province = :p';
        $params[':p'] = $province;
    }
    $sql = 'SELECT * FROM airports' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY name LIMIT 1000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $provinces = $pdo->query('SELECT DISTINCT province FROM airports WHERE province IS NOT NULL ORDER BY province')->fetchAll(PDO::FETCH_COLUMN);

    if (isset($_GET['new']) && $canEdit) {
        $mode = 'create';
    } elseif (isset($_GET['edit']) && $canEdit) {
        $stmt = $pdo->prepare('SELECT * FROM airports WHERE id = :id');
        $stmt->execute([':id' => (int)$_GET['edit']]);
        $editItem = $stmt->fetch();
        if ($editItem) $mode = 'edit';
    }
}

$page_title = 'Indonesia Database';
require __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
    .wrap { max-width: 1400px; }
    .db-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .db-tabs a { padding: 8px 16px; border-radius: 8px; background: var(--panel-2); color: var(--text); text-decoration: none; font-size: 14px; }
    .db-tabs a.active { background: var(--accent); color: #06232f; font-weight: 600; }
    .db-layout { display: flex; gap: 20px; flex-wrap: wrap; }
    .db-sidebar { flex: 0 0 340px; }
    .db-main { flex: 1; min-width: 340px; }
    #map { height: 560px; border-radius: 10px; margin-top: 12px; }
    .item-list { max-height: 520px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; }
    .item-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 13px; gap: 8px; }
    .item-row .meta { color: var(--muted); font-size: 12px; }
    .item-row form { margin: 0; }
    .item-row button { margin: 0; padding: 4px 8px; font-size: 12px; }
    .cat-chip { font-size: 11px; text-transform: uppercase; background: var(--panel-2); color: var(--accent); padding: 2px 8px; border-radius: 20px; }
</style>

<div class="db-tabs">
    <?php foreach ($cats as $key => $meta): ?>
        <a class="<?= $cat === $key ? 'active' : '' ?>" href="<?= url('/database/index.php?cat=' . $key) ?>"><?= e($meta['label']) ?></a>
    <?php endforeach; ?>
</div>

<?php foreach (take_flashes() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<?php if ($cat !== 'airport'): ?>
    <div class="card">
        <h2 style="margin-top:0;"><?= e($cats[$cat]['label']) ?></h2>
        <p class="muted">Coming soon — kategori ini sedang dalam pengembangan.</p>
    </div>
<?php else: ?>
<div class="db-layout">
    <div class="db-sidebar">
        <div class="card">
            <h3 style="margin-top:0;">Filter Airport</h3>
            <form method="get">
                <input type="hidden" name="cat" value="airport">
                <label>Cari (nama/ICAO/IATA)</label>
                <input name="q" value="<?= e($q) ?>" placeholder="Soekarno-Hatta / WIII / CGK">
                <label>Tipe</label>
                <select name="type">
                    <option value="">Semua Tipe</option>
                    <?php foreach (airport_types() as $t): ?>
                        <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(airport_type_label($t)) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Provinsi</label>
                <select name="province">
                    <option value="">Semua Provinsi</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?= e($p) ?>" <?= $province === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-ghost">Filter</button>
            </form>

            <?php if ($canEdit): ?>
                <a class="btn" href="<?= url('/database/index.php?cat=airport&new=1') ?>" style="width:100%;text-align:center;box-sizing:border-box;">+ Add Airport</a>
            <?php endif; ?>

            <p class="muted" style="font-size:12px;margin-top:10px;"><?= count($items) ?> bandara ditampilkan (maks 1000).</p>

            <div class="item-list">
                <?php foreach ($items as $it): ?>
                    <div class="item-row">
                        <div>
                            <b><?= e($it['name']) ?></b>
                            <?= $it['icao'] ? ' <span class="cat-chip">' . e($it['icao']) . '</span>' : '' ?>
                            <div class="meta"><?= e(airport_type_label($it['type'])) ?><?= $it['province'] ? ' · ' . e($it['province']) : '' ?></div>
                        </div>
                        <?php if ($canEdit): ?>
                            <div style="display:flex;gap:6px;">
                                <a class="btn btn-ghost" style="margin:0;padding:4px 8px;font-size:12px;" href="<?= url('/database/index.php?cat=airport&edit=' . (int)$it['id']) ?>">Edit</a>
                                <form method="post" action="<?= url('/database/delete.php') ?>" onsubmit="return confirm('Hapus <?= e(addslashes($it['name'])) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="cat" value="airport">
                                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                    <button type="submit" class="btn-danger">Del</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$items): ?><div style="padding:14px;" class="muted">Tidak ada hasil.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="db-main">
        <?php if ($mode === 'create' || $mode === 'edit'): ?>
            <div class="card" style="max-width:520px;">
                <h3 style="margin-top:0;"><?= $mode === 'edit' ? 'Edit Airport' : 'Add Airport' ?></h3>
                <p class="muted">Klik peta untuk isi koordinat otomatis, atau isi manual.</p>
                <form method="post" action="<?= url('/database/airport-save.php') ?>" id="airportForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)($editItem['id'] ?? 0) ?>">
                    <div class="row">
                        <div>
                            <label>Nama</label>
                            <input name="name" id="af_name" value="<?= e($editItem['name'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label>Tipe</label>
                            <select name="type">
                                <?php foreach (airport_types() as $t): ?>
                                    <option value="<?= e($t) ?>" <?= ($editItem['type'] ?? '') === $t ? 'selected' : '' ?>><?= e(airport_type_label($t)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div>
                            <label>ICAO</label>
                            <input name="icao" value="<?= e($editItem['icao'] ?? '') ?>" maxlength="4" style="text-transform:uppercase">
                        </div>
                        <div>
                            <label>IATA</label>
                            <input name="iata" value="<?= e($editItem['iata'] ?? '') ?>" maxlength="3" style="text-transform:uppercase">
                        </div>
                        <div>
                            <label>Elevasi (ft)</label>
                            <input name="elevation_ft" value="<?= e($editItem['elevation_ft'] ?? '') ?>" inputmode="numeric">
                        </div>
                    </div>
                    <div class="row">
                        <div>
                            <label>Latitude</label>
                            <input name="lat" id="af_lat" value="<?= e($editItem['lat'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label>Longitude</label>
                            <input name="lon" id="af_lon" value="<?= e($editItem['lon'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div>
                            <label>Kota/Kabupaten</label>
                            <input name="municipality" value="<?= e($editItem['municipality'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Provinsi</label>
                            <input name="province" value="<?= e($editItem['province'] ?? '') ?>">
                        </div>
                    </div>
                    <label>Notes</label>
                    <textarea name="notes"><?= e($editItem['notes'] ?? '') ?></textarea>
                    <button type="submit">Save Airport</button>
                    <a class="btn btn-ghost" href="<?= url('/database/index.php?cat=airport') ?>">Batal</a>
                </form>
            </div>
        <?php endif; ?>
        <div class="card">
            <div id="map"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const items = <?= json_encode($items) ?>;
const canEdit = <?= json_encode($canEdit) ?>;
const editMode = <?= json_encode($mode === 'create' || $mode === 'edit') ?>;
const typeColors = <?= json_encode(array_combine(airport_types(), array_map('airport_type_color', airport_types()))) ?>;

const center = items.length ? [items[0].lat, items[0].lon] : [-2.5, 118];
const map = L.map('map').setView(center, items.length ? 6 : 5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
}).addTo(map);

items.forEach(it => {
    const marker = L.circleMarker([it.lat, it.lon], {
        radius: 6, color: '#0b1220', weight: 1.5, fillColor: typeColors[it.type] || '#94a3b8', fillOpacity: 0.9
    }).addTo(map);
    let popup = '<b>' + it.name + '</b>';
    if (it.icao) popup += '<br>ICAO: ' + it.icao;
    if (it.iata) popup += ' · IATA: ' + it.iata;
    popup += '<br>' + it.type;
    marker.bindPopup(popup);
});

if (items.length > 1) {
    map.fitBounds(items.map(it => [it.lat, it.lon]), { padding: [30, 30] });
}

if (editMode && canEdit) {
    map.on('click', e => {
        document.getElementById('af_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('af_lon').value = e.latlng.lng.toFixed(6);
    });
}
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
