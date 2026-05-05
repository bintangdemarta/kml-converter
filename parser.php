<?php

function detectFormat($content) {
    if (strpos($content, '<kml') !== false) return 'kml';
    if (strpos($content, '<gpx') !== false) return 'gpx';
    if (strpos($content, '{"type":') !== false) return 'geojson';
    return 'unknown';
}

function parseKML($content) {
    libxml_use_internal_errors(true);

    $xml = simplexml_load_string($content);
    if (!$xml) return [];

    $result = [];

    // 🔥 1. Ambil semua <coordinates>
    $coords = $xml->xpath('//*[local-name()="coordinates"]');

    foreach ($coords as $block) {
        $points = preg_split('/\s+/', trim((string)$block));

        foreach ($points as $p) {
            $parts = explode(',', $p);

            if (count($parts) >= 2) {
                $lon = trim($parts[0]);
                $lat = trim($parts[1]);

                if (is_numeric($lat) && is_numeric($lon)) {
                    $result[] = "$lat,$lon";
                }
            }
        }
    }

    // 🔥 2. Handle gx:coord (Google Earth Track)
    $gxCoords = $xml->xpath('//*[local-name()="coord"]');

    foreach ($gxCoords as $c) {
        $parts = preg_split('/\s+/', trim((string)$c));

        if (count($parts) >= 2) {
            $lon = $parts[0];
            $lat = $parts[1];

            if (is_numeric($lat) && is_numeric($lon)) {
                $result[] = "$lat,$lon";
            }
        }
    }

    return array_unique($result);
}

function parseGPX($content) {
    $xml = simplexml_load_string($content);
    if (!$xml) return [];

    $points = $xml->xpath('//trkpt');
    $result = [];

    foreach ($points as $pt) {
        $lat = (string)$pt['lat'];
        $lon = (string)$pt['lon'];
        $result[] = "$lat,$lon";
    }

    return $result;
}

function parseGeoJSON($content) {
    $data = json_decode($content, true);
    $result = [];

    if (!isset($data['features'])) return [];

    foreach ($data['features'] as $feature) {
        $coords = $feature['geometry']['coordinates'];

        foreach ($coords as $pt) {
            $result[] = "{$pt[1]},{$pt[0]}";
        }
    }

    return $result;
}

function extractCoords($coordBlocks) {
    $result = [];

    foreach ($coordBlocks as $block) {
        $points = preg_split('/\s+/', trim((string)$block));

        foreach ($points as $p) {
            $parts = explode(',', $p);
            if (count($parts) >= 2) {
                $lat = trim($parts[1]);
                $lon = trim($parts[0]);

                if (is_numeric($lat) && is_numeric($lon)) {
                    $result[] = "$lat,$lon";
                }
            }
        }
    }

    return $result;
}

function generateHolding($lat, $lon, $radius = 5) {
    $points = [];

    for ($i = 0; $i < 360; $i += 20) {
        $rad = deg2rad($i);

        $newLat = $lat + ($radius * cos($rad)) / 111;
        $newLon = $lon + ($radius * sin($rad)) / (111 * cos(deg2rad($lat)));

        $points[] = "$newLat,$newLon";
    }

    return $points;
}