<?php

$base =
    'https://services3.arcgis.com/BU6Aadhn6tbBEdyk/ArcGIS/rest/services/'
    . 'GRID3_Nigeria_Local_Government_Area_Boundaries_with_Names/'
    . 'FeatureServer/11/query';

$outputRoot =
    'storage/app/import-sources/grid3-boundaries';

$points = [
    ['2350249', 'Amaigbo', 'Abia State', 5.78917, 7.83829],
    ['2322552', 'Takum', 'Benue State', 7.26667, 9.98333],
    ['2327233', 'Oke Ila', 'Ekiti State', 7.95000, 4.98333],
    ['2332459', 'Lagos', 'Lagos', 6.45407, 3.39467],
    ['2328090', 'Ode', 'Ondo State', 7.78990, 5.71170],
    ['2327223', 'Oke Mesi', 'Osun State', 7.81667, 4.91667],
    ['2346812', 'Bonny', 'Rivers State', 4.45160, 7.17074],
];

if (! is_dir($outputRoot)) {
    mkdir(
        $outputRoot,
        0775,
        true
    );
}

function queryBoundary(
    string $base,
    float $latitude,
    float $longitude
): array {
    $geometry = json_encode(
        [
            'x' => $longitude,
            'y' => $latitude,
            'spatialReference' => [
                'wkid' => 4326,
            ],
        ],
        JSON_UNESCAPED_SLASHES
    );

    $url =
        $base
        . '?'
        . http_build_query(
            [
                'where' => '1=1',
                'geometry' => $geometry,
                'geometryType' =>
                    'esriGeometryPoint',
                'inSR' => '4326',
                'spatialRel' =>
                    'esriSpatialRelIntersects',
                'outFields' =>
                    'lga_code,lga_name,state_code,state_name',
                'returnGeometry' =>
                    'false',
                'f' => 'json',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    $context = stream_context_create(
        [
            'http' => [
                'timeout' => 60,
                'header' =>
                    "User-Agent: BusinessFinder Nigeria\r\n",
            ],
        ]
    );

    $body = file_get_contents(
        $url,
        false,
        $context
    );

    if ($body === false) {
        throw new RuntimeException(
            'GRID3 request failed.'
        );
    }

    $data = json_decode(
        $body,
        true
    );

    if (! is_array($data)) {
        throw new RuntimeException(
            'GRID3 returned invalid JSON.'
        );
    }

    if (! empty($data['error'])) {
        throw new RuntimeException(
            json_encode(
                $data['error']
            )
        );
    }

    return $data;
}

$reportPath =
    "{$outputRoot}/city-exception-boundary-audit.csv";

$handle = fopen(
    $reportPath,
    'wb'
);

fputcsv(
    $handle,
    [
        'geoname_id',
        'city',
        'geonames_state',
        'latitude',
        'longitude',
        'polygon_count',
        'grid3_state_code',
        'grid3_state_name',
        'grid3_lga_code',
        'grid3_lga_name',
    ]
);

echo "========================================"
    . PHP_EOL;

echo "GRID3 LGA BOUNDARY AUDIT"
    . PHP_EOL;

echo "========================================"
    . PHP_EOL;

foreach ($points as $point) {
    [
        $id,
        $city,
        $sourceState,
        $latitude,
        $longitude,
    ] = $point;

    $data = queryBoundary(
        $base,
        $latitude,
        $longitude
    );

    $features =
        $data['features']
        ?? [];

    $attributes =
        $features[0]['attributes']
        ?? [];

    file_put_contents(
        "{$outputRoot}/{$id}.json",
        json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        )
    );

    echo sprintf(
        "%s | %-10s | %-12s | %s / %s\n",
        $id,
        $city,
        $sourceState,
        $attributes['state_name']
            ?? 'NO MATCH',
        $attributes['lga_name']
            ?? 'NO MATCH'
    );

    fputcsv(
        $handle,
        [
            $id,
            $city,
            $sourceState,
            $latitude,
            $longitude,
            count($features),
            $attributes[
                'state_code'
            ] ?? '',
            $attributes[
                'state_name'
            ] ?? '',
            $attributes[
                'lga_code'
            ] ?? '',
            $attributes[
                'lga_name'
            ] ?? '',
        ]
    );
}

fclose($handle);

echo PHP_EOL;

echo "Audit CSV: {$reportPath}"
    . PHP_EOL;

echo "SHA-256: "
    . hash_file(
        'sha256',
        $reportPath
    )
    . PHP_EOL;

echo "No BusinessFinder database rows were changed."
    . PHP_EOL;
