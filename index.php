<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Infinite Flight Converter</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

<style>
body {
    font-family: Arial;
    background: #0f172a;
    color: white;
    padding: 40px;
}
.container {
    background: #1e293b;
    padding: 25px;
    border-radius: 12px;
    max-width: 800px;
    margin: auto;
}
textarea {
    width: 100%;
    height: 120px;
    margin-top: 10px;
}
button {
    padding: 10px;
    margin-top: 10px;
    cursor: pointer;
}
#map {
    height: 400px;
    margin-top: 20px;
}
</style>
</head>

<body>

<div class="container">

<h2>KML / GPX / GeoJSON → Infinite Flight</h2>

<input type="file" id="fileInput">
<br>
<button onclick="uploadFile()">Convert</button>

<h3>Output:</h3>
<textarea id="output"></textarea>
<button onclick="copyText()">Copy</button>
<div id="stats" style="
    margin-top:15px;
    padding:15px;
    background:#0f172a;
    border-radius:10px;
    font-size:15px;
    ">
Distance: -
</div>
<hr>

<h3>Generate Holding Pattern</h3>
<input id="lat" placeholder="Latitude">
<input id="lon" placeholder="Longitude">
<button onclick="generateHolding()">Generate</button>

<div id="map"></div>

</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>

let map = L.map('map').setView([-6.2, 106.8], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let polyline;

function drawRoute(coordString) {
    if (polyline) {
        map.removeLayer(polyline);
    }

    const points = coordString.split(' ').map(c => {
        const [lat, lon] = c.split(',');
        return [parseFloat(lat), parseFloat(lon)];
    });

    polyline = L.polyline(points).addTo(map);
    map.fitBounds(points);
}

async function uploadFile() {
    const file = document.getElementById('fileInput').files[0];
    if (!file) return alert("Pilih file");

    const formData = new FormData();
    formData.append('file', file);

    const res = await fetch('convert.php', {
        method: 'POST',
        body: formData
    });

    const data = await res.json();

document.getElementById('output').value = data.route;

document.getElementById('stats').innerHTML =
`
<b>Total Distance:</b> ${data.distance_nm} NM<br>
<b>Waypoints:</b> ${data.waypoints}<br>
<b>XCub @110 KT:</b> ${(data.distance_nm / 110 * 60).toFixed(0)} min<br>
<b>C172 @120 KT:</b> ${(data.distance_nm / 120 * 60).toFixed(0)} min
`;

drawRoute(data.route);
}

function generateHolding() {
    const lat = document.getElementById('lat').value;
    const lon = document.getElementById('lon').value;

    fetch(`convert.php?mode=holding&lat=${lat}&lon=${lon}`)
    .then(res => res.json())
    .then(data => {

        document.getElementById('output').value = data.route;

        document.getElementById('stats').innerHTML =
        `
        <b>Total Distance:</b> ${data.distance_nm} NM<br>
        <b>Waypoints:</b> ${data.waypoints}
        `;

        drawRoute(data.route);
    });
}

function copyText() {
    const text = document.getElementById('output');
    text.select();
    document.execCommand('copy');
    alert("Copied!");
}

</script>

</body>
</html>