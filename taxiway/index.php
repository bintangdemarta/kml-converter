<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/taxiway.php';
auth_boot();

$u = current_user();
$canEdit = is_manager(); // guard tampilan tombol saja - handler POST tetap cek ulang require_role()
$pdo = db();

// Daftar ICAO yang sudah punya data
$icaoList = $pdo->query('SELECT DISTINCT icao FROM taxi_nodes ORDER BY icao')->fetchAll(PDO::FETCH_COLUMN);

$activeIcao = strtoupper(trim($_GET['icao'] ?? ''));
if ($activeIcao === '') {
    $activeIcao = $icaoList[0] ?? '';
}
if ($activeIcao !== '' && !preg_match('/^[A-Z]{4}$/', $activeIcao)) {
    $activeIcao = $icaoList[0] ?? '';
}

$nodes = [];
$edges = [];
if ($activeIcao !== '') {
    $stmt = $pdo->prepare('SELECT * FROM taxi_nodes WHERE icao = :icao ORDER BY type, name');
    $stmt->execute([':icao' => $activeIcao]);
    $nodes = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT e.*, a.name AS from_name, b.name AS to_name
         FROM taxi_edges e
         JOIN taxi_nodes a ON a.id = e.from_node_id
         JOIN taxi_nodes b ON b.id = e.to_node_id
         WHERE e.icao = :icao'
    );
    $stmt->execute([':icao' => $activeIcao]);
    $edges = $stmt->fetchAll();
}

// Jumlah edge per node (utk sidebar & confirm-delete)
$edgeCountByNode = [];
foreach ($edges as $e) {
    $edgeCountByNode[(int)$e['from_node_id']] = ($edgeCountByNode[(int)$e['from_node_id']] ?? 0) + 1;
    $edgeCountByNode[(int)$e['to_node_id']]   = ($edgeCountByNode[(int)$e['to_node_id']]   ?? 0) + 1;
}

// Routing (publik, tidak perlu login)
$fromId = (int)($_GET['from'] ?? 0);
$toId   = (int)($_GET['to'] ?? 0);
$routeResult = null;
$routeError  = null;
if ($fromId > 0 && $toId > 0 && $activeIcao !== '') {
    $routeResult = find_taxi_route($pdo, $activeIcao, $fromId, $toId);
    if ($routeResult === null) {
        $routeError = 'Tidak ada jalur yang menghubungkan kedua titik ini.';
    }
}

$page_title = 'Ground Ops — Taxiway Routing';
require __DIR__ . '/../partials/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
    .wrap { max-width: 1400px; }
    .go-layout { display: flex; gap: 20px; flex-wrap: wrap; }
    .go-sidebar { flex: 0 0 340px; }
    .go-sidebar .card { margin-bottom: 16px; }
    .go-main { flex: 1; min-width: 340px; }
    #map { height: 560px; border-radius: 10px; margin-top: 12px; }
    .legend { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; margin-top: 10px; }
    .legend span { display: inline-flex; align-items: center; gap: 5px; color: var(--muted); }
    .legend i { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .mode-toggle { display: flex; gap: 8px; margin-bottom: 10px; }
    .mode-toggle button { margin: 0; padding: 8px 14px; font-size: 13px; }
    .mode-toggle button.active { background: var(--accent); }
    .item-list { max-height: 260px; overflow-y: auto; }
    .item-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px; gap: 8px; }
    .item-row .meta { color: var(--muted); font-size: 12px; }
    .item-row form { margin: 0; }
    .item-row button { margin: 0; padding: 4px 8px; font-size: 12px; }
    #statusMsg { font-size: 13px; color: var(--accent); min-height: 18px; margin-top: 8px; }
    .route-steps { font-size: 13px; }
    .route-steps li { margin-bottom: 4px; }
</style>

<p class="muted" style="margin-top:-8px;">
    Graph taxiway terkurasi manager/admin (berbeda dari kategori "Taxiway" bebas-komunitas di
    <a href="<?= url('/map/index.php') ?>">Map Dashboard</a>). Semua orang bisa lihat & cari rute; hanya manager/admin yang bisa edit graph.
</p>

<?php foreach (take_flashes() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="go-layout">
    <div class="go-sidebar">
        <div class="card">
            <h3 style="margin-top:0;">Airport</h3>
            <form method="get">
                <?php if ($canEdit): ?>
                    <label>ICAO <span class="muted">(ketik baru untuk mulai bandara lain)</span></label>
                    <input name="icao" value="<?= e($activeIcao) ?>" list="icaoOptions" maxlength="4" style="text-transform:uppercase" onchange="this.form.submit()">
                    <datalist id="icaoOptions">
                        <?php foreach ($icaoList as $ic): ?>
                            <option value="<?= e($ic) ?>">
                        <?php endforeach; ?>
                    </datalist>
                <?php else: ?>
                    <label>ICAO</label>
                    <select name="icao" onchange="this.form.submit()">
                        <?php if (!$icaoList): ?>
                            <option value="">Belum ada data</option>
                        <?php endif; ?>
                        <?php foreach ($icaoList as $ic): ?>
                            <option value="<?= e($ic) ?>" <?= $activeIcao === $ic ? 'selected' : '' ?>><?= e($ic) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>

            <div class="legend">
                <?php foreach (taxi_node_types() as $t): ?>
                    <span><i style="background:<?= e(taxi_type_color($t)) ?>"></i><?= e(taxi_node_type_label($t)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Find Route</h3>
            <?php if (!$nodes): ?>
                <p class="muted">Belum ada node untuk ICAO ini.</p>
            <?php else: ?>
                <form method="get">
                    <input type="hidden" name="icao" value="<?= e($activeIcao) ?>">
                    <label>Dari</label>
                    <select name="from">
                        <option value="">— pilih titik awal —</option>
                        <?php foreach ($nodes as $n): ?>
                            <option value="<?= (int)$n['id'] ?>" <?= $fromId === (int)$n['id'] ? 'selected' : '' ?>>
                                <?= e($n['name']) ?> (<?= e(taxi_node_type_label($n['type'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Ke</label>
                    <select name="to">
                        <option value="">— pilih titik tujuan —</option>
                        <?php foreach ($nodes as $n): ?>
                            <option value="<?= (int)$n['id'] ?>" <?= $toId === (int)$n['id'] ? 'selected' : '' ?>>
                                <?= e($n['name']) ?> (<?= e(taxi_node_type_label($n['type'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Find Route</button>
                </form>

                <?php if ($routeError): ?>
                    <div class="flash error" style="margin-top:12px;"><?= e($routeError) ?></div>
                <?php elseif ($routeResult): ?>
                    <div style="margin-top:14px;">
                        <b><?= number_format($routeResult['distance_m'], 1) ?> m</b> — <?= count($routeResult['path']) ?> titik
                        <ol class="route-steps">
                            <?php foreach ($routeResult['path'] as $i => $n): ?>
                                <li>
                                    <?= e($n['name']) ?>
                                    <?php if (isset($routeResult['edges'][$i])): ?>
                                        <span class="muted">→ via <?= e($routeResult['edges'][$i]['taxiway_name'] ?: 'taxiway') ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Nodes (<?= count($nodes) ?>)</h3>
            <div class="item-list">
                <?php foreach ($nodes as $n): ?>
                    <div class="item-row">
                        <div>
                            <b><?= e($n['name']) ?></b><?= $n['ref_code'] ? ' <span class="muted">(' . e($n['ref_code']) . ')</span>' : '' ?>
                            <div class="meta"><?= e(taxi_node_type_label($n['type'])) ?> · <?= $edgeCountByNode[(int)$n['id']] ?? 0 ?> edge</div>
                        </div>
                        <?php if ($canEdit): ?>
                            <form method="post" action="<?= url('/taxiway/node-delete.php') ?>"
                                  onsubmit="return confirm('Hapus node &quot;<?= e(addslashes($n['name'])) ?>&quot; beserta <?= $edgeCountByNode[(int)$n['id']] ?? 0 ?> edge yang terhubung?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                <button type="submit" class="btn-danger">Del</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$nodes): ?><p class="muted">Belum ada node.</p><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Edges (<?= count($edges) ?>)</h3>
            <div class="item-list">
                <?php foreach ($edges as $e): ?>
                    <div class="item-row">
                        <div>
                            <b><?= e($e['from_name']) ?> <?= $e['bidirectional'] ? '↔' : '→' ?> <?= e($e['to_name']) ?></b>
                            <div class="meta"><?= $e['taxiway_name'] ? e($e['taxiway_name']) . ' · ' : '' ?><?= number_format((float)$e['distance_m'], 1) ?> m</div>
                        </div>
                        <?php if ($canEdit): ?>
                            <form method="post" action="<?= url('/taxiway/edge-delete.php') ?>" onsubmit="return confirm('Hapus edge ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="btn-danger">Del</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$edges): ?><p class="muted">Belum ada edge.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="go-main">
        <div class="card">
            <?php if ($canEdit): ?>
                <div class="mode-toggle">
                    <button type="button" id="modeBrowse" class="active" onclick="setMode('browse')">Browse</button>
                    <button type="button" id="modeAddNode" onclick="setMode('addNode')">+ Add Node</button>
                    <button type="button" id="modeAddEdge" onclick="setMode('addEdge')">+ Add Edge</button>
                </div>
                <div id="statusMsg"></div>
            <?php endif; ?>
            <div id="map"></div>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>
<div class="card" id="nodeFormCard" style="display:none;max-width:480px;">
    <h3 style="margin-top:0;" id="nodeFormTitle">Add Node</h3>
    <form method="post" action="<?= url('/taxiway/node-save.php') ?>" id="nodeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="nf_id" value="0">
        <input type="hidden" name="icao" id="nf_icao" value="<?= e($activeIcao) ?>">
        <input type="hidden" name="lat" id="nf_lat" value="">
        <input type="hidden" name="lon" id="nf_lon" value="">
        <div class="row">
            <div>
                <label>Nama</label>
                <input name="name" id="nf_name" required>
            </div>
            <div>
                <label>Ref Code <span class="muted">(opsional)</span></label>
                <input name="ref_code" id="nf_ref_code">
            </div>
        </div>
        <label>Tipe</label>
        <select name="type" id="nf_type">
            <?php foreach (taxi_node_types() as $t): ?>
                <option value="<?= e($t) ?>"><?= e(taxi_node_type_label($t)) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Notes <span class="muted">(opsional)</span></label>
        <textarea name="notes" id="nf_notes"></textarea>
        <button type="submit">Save Node</button>
        <button type="button" class="btn-ghost" onclick="closeNodeForm()">Batal</button>
    </form>
</div>

<div class="card" id="edgeFormCard" style="display:none;max-width:480px;">
    <h3 style="margin-top:0;">Add Edge</h3>
    <p class="muted" id="edgeFormLabel"></p>
    <form method="post" action="<?= url('/taxiway/edge-save.php') ?>" id="edgeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="from_node_id" id="ef_from">
        <input type="hidden" name="to_node_id" id="ef_to">
        <label>Nama Taxiway <span class="muted">(opsional)</span></label>
        <input name="taxiway_name" id="ef_taxiway_name">
        <label><input type="checkbox" name="bidirectional" id="ef_bidirectional" checked style="width:auto;display:inline-block;"> Bidirectional (bisa 2 arah)</label>
        <button type="submit">Save Edge</button>
        <button type="button" class="btn-ghost" onclick="closeEdgeForm()">Batal</button>
    </form>
</div>
<?php endif; ?>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const nodes = <?= json_encode($nodes) ?>;
const edges = <?= json_encode($edges) ?>;
const canEdit = <?= json_encode($canEdit) ?>;
const routeResult = <?= json_encode($routeResult) ?>;
const typeColors = <?= json_encode(taxi_type_colors()) ?>;

let uiMode = 'browse';
let edgeFirstNodeId = null;
let edgeFirstMarker = null;
const nodeMarkers = {};

const center = nodes.length ? [nodes[0].lat, nodes[0].lon] : [-6.2, 106.8];
const map = L.map('map').setView(center, nodes.length ? 15 : 4);

L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri', maxZoom: 20
}).addTo(map);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 })
    .addTo(map); // OSM di atas — resolusi lebih tinggi utk ground ops presisi taxiway

function statusMsg(text) {
    const el = document.getElementById('statusMsg');
    if (el) el.textContent = text || '';
}

function styleForType(type) {
    return { radius: 8, color: '#0b1220', weight: 2, fillColor: typeColors[type] || '#94a3b8', fillOpacity: 0.9 };
}

function setMode(mode) {
    uiMode = mode;
    resetEdgeSelection();
    closeNodeForm();
    closeEdgeForm();
    ['modeBrowse', 'modeAddNode', 'modeAddEdge'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.classList.remove('active');
    });
    const map_ = { browse: 'modeBrowse', addNode: 'modeAddNode', addEdge: 'modeAddEdge' };
    const activeBtn = document.getElementById(map_[mode]);
    if (activeBtn) activeBtn.classList.add('active');
    statusMsg(mode === 'addNode' ? 'Klik peta untuk menambah node baru.' : mode === 'addEdge' ? 'Klik node pertama, lalu node kedua untuk membuat edge.' : '');
}

function resetEdgeSelection() {
    if (edgeFirstMarker && edgeFirstNodeId !== null) {
        const n = nodes.find(n => n.id === edgeFirstNodeId);
        if (n) edgeFirstMarker.setStyle(styleForType(n.type));
    }
    edgeFirstNodeId = null;
    edgeFirstMarker = null;
}

function openNodeForm(node, latlng) {
    const card = document.getElementById('nodeFormCard');
    if (!card) return;
    document.getElementById('nodeFormTitle').textContent = node ? 'Edit Node' : 'Add Node';
    document.getElementById('nf_id').value = node ? node.id : 0;
    document.getElementById('nf_name').value = node ? node.name : '';
    document.getElementById('nf_ref_code').value = node ? (node.ref_code || '') : '';
    document.getElementById('nf_type').value = node ? node.type : 'other';
    document.getElementById('nf_notes').value = node ? (node.notes || '') : '';
    document.getElementById('nf_lat').value = node ? node.lat : latlng.lat;
    document.getElementById('nf_lon').value = node ? node.lon : latlng.lng;
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function closeNodeForm() {
    const card = document.getElementById('nodeFormCard');
    if (card) card.style.display = 'none';
}

function openEdgeForm(fromId, toId) {
    const card = document.getElementById('edgeFormCard');
    if (!card) return;
    const a = nodes.find(n => n.id === fromId), b = nodes.find(n => n.id === toId);
    document.getElementById('edgeFormLabel').textContent = (a ? a.name : fromId) + ' → ' + (b ? b.name : toId);
    document.getElementById('ef_from').value = fromId;
    document.getElementById('ef_to').value = toId;
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function closeEdgeForm() {
    const card = document.getElementById('edgeFormCard');
    if (card) card.style.display = 'none';
}

function handleEdgeNodeClick(node, marker) {
    if (edgeFirstNodeId === null) {
        edgeFirstNodeId = node.id;
        edgeFirstMarker = marker;
        marker.setStyle({ color: '#facc15', weight: 3 });
        statusMsg('Titik A: ' + node.name + '. Klik titik B, atau klik titik A lagi untuk batal.');
    } else if (node.id === edgeFirstNodeId) {
        resetEdgeSelection();
        statusMsg('Pemilihan dibatalkan.');
    } else {
        openEdgeForm(edgeFirstNodeId, node.id);
        resetEdgeSelection();
    }
}

function popupHtmlFor(n) {
    let html = '<b>' + n.name + '</b><br>' + (n.ref_code ? n.ref_code + ' · ' : '') + n.type;
    if (n.notes) html += '<br>' + n.notes;
    return html;
}

// --- render edges (garis dulu, supaya node di atasnya) ---
edges.forEach(e => {
    const a = nodes.find(n => n.id === e.from_node_id);
    const b = nodes.find(n => n.id === e.to_node_id);
    if (!a || !b) return;
    L.polyline([[a.lat, a.lon], [b.lat, b.lon]], {
        color: '#38bdf8', weight: 3, opacity: 0.7, interactive: false,
        dashArray: e.bidirectional ? null : '6,6'
    }).addTo(map);
});

// --- render nodes ---
nodes.forEach(n => {
    const marker = L.circleMarker([n.lat, n.lon], styleForType(n.type)).addTo(map);
    nodeMarkers[n.id] = marker;

    marker.on('click', e => {
        L.DomEvent.stopPropagation(e);
        if (uiMode === 'addEdge' && canEdit) {
            handleEdgeNodeClick(n, marker);
        } else if (canEdit && uiMode === 'browse') {
            openNodeForm(n, null);
        } else {
            marker.bindPopup(popupHtmlFor(n)).openPopup();
        }
    });
});

// --- klik area kosong: hanya berarti kalau mode addNode ---
map.on('click', e => {
    if (uiMode === 'addNode' && canEdit) {
        openNodeForm(null, e.latlng);
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && edgeFirstNodeId !== null) {
        resetEdgeSelection();
        statusMsg('Pemilihan dibatalkan.');
    }
});

// --- highlight hasil routing ---
if (routeResult && routeResult.path && routeResult.path.length > 1) {
    const latlngs = routeResult.path.map(n => [n.lat, n.lon]);
    L.polyline(latlngs, { color: '#22c55e', weight: 5, opacity: 0.9, interactive: false }).addTo(map);
    map.fitBounds(latlngs, { padding: [30, 30] });
} else if (nodes.length) {
    map.fitBounds(nodes.map(n => [n.lat, n.lon]), { padding: [30, 30] });
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
