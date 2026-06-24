<?php

require 'parser.php';

libxml_use_internal_errors(true);

if (isset($_GET['mode']) && $_GET['mode'] === 'holding') {

    $lat = floatval($_GET['lat']);
    $lon = floatval($_GET['lon']);

    $coords = generateHolding($lat, $lon);

    echo json_encode([
        'route' => implode(" ", $coords),
        'distance_nm' => calculateDistanceNM($coords),
        'waypoints' => count($coords)
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Invalid request");
}

if (!isset($_FILES['file'])) {
    exit("No file uploaded");
}

$file = $_FILES['file'];

$content = file_get_contents($file['tmp_name']);

$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

if (strpos($content, 'encoding="UTF-16"') !== false) {
    $content = mb_convert_encoding(
        $content,
        'UTF-8',
        'UTF-16'
    );
}

$format = detectFormat($content);

switch ($format) {

    case 'kml':
        $coords = parseKML($content);
        break;

    case 'gpx':
        $coords = parseGPX($content);
        break;

    case 'geojson':
        $coords = parseGeoJSON($content);
        break;

    default:
        exit("Unsupported format");
}

if (empty($coords)) {
    exit("No coordinates found");
}

$coords = array_unique($coords);

$route = implode(" ", $coords);

echo json_encode([
    'route' => $route,
    'distance_nm' => calculateDistanceNM($coords),
    'waypoints' => count($coords)
]);