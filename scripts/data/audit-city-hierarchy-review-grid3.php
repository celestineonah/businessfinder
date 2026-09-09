<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$app = require 'bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$reviewFile =
    'storage/app/import-sources/geonames/city-hierarchy-review.csv';

$aliasFile =
    'database/data/geography/grid3/grid3-lga-aliases.csv';

$outputRoot =
    'storage/app/import-sources/grid3-boundaries/hierarchy-review';

$outputFile =
    "{$outputRoot}/hierarchy-boundary-audit.csv";

$endpoint =
    'https://services3.arcgis.com/BU6Aadhn6tbBEdyk/ArcGIS/rest/services/'
    . 'GRID3_Nigeria_Local_Government_Area_Boundaries_with_Names/'
    . 'FeatureServer/11/query';

if (! is_readable($reviewFile)) {
    throw new RuntimeException(
        "Missing review file: {$reviewFile}"
    );
}

if (! is_readable($aliasFile)) {
    throw new RuntimeException(
        "Missing alias file: {$aliasFile}"
    );
}

if (! is_dir($outputRoot)) {
    mkdir(
        $outputRoot,
        0775,
        true
    );
}

function compactName(string $value): string
{
    $value = mb_strtolower(
        trim($value),
        'UTF-8'
    );

    $value = str_replace(
        '&',
        'and',
        $value
    );

    return preg_replace(
        '/[^a-z0-9]+/u',
        '',
        $value
    );
}

function aliasKey(
    string $stateCode,
    string $name
): string {
    return strtoupper(
        trim($stateCode)
    )
        . '|'
        . mb_strtolower(
            trim($name),
            'UTF-8'
        );
}

function queryBoundary(
    string $endpoint,
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
        $endpoint
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

    $lastError = '';

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,
                CURLOPT_FOLLOWLOCATION =>
                    true,
                CURLOPT_CONNECTTIMEOUT =>
                    20,
                CURLOPT_TIMEOUT =>
                    60,
                CURLOPT_USERAGENT =>
                    'BusinessFinder Nigeria hierarchy audit',
            ]
        );

        $body = curl_exec($ch);

        $status = curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE
        );

        $error = curl_error($ch);

        curl_close($ch);

        if (
            $body !== false &&
            $status >= 200 &&
            $status < 300
        ) {
            $data = json_decode(
                $body,
                true
            );

            if (
                is_array($data) &&
                empty($data['error'])
            ) {
                return $data;
            }
        }

        $lastError =
            "HTTP={$status} {$error}";

        usleep(
            300000 * $attempt
        );
    }

    throw new RuntimeException(
        "GRID3 query failed: {$lastError}"
    );
}

/*
 * Reviewed GRID3 LGA aliases.
 */
$aliases = [];

$aliasCsv = new SplFileObject(
    $aliasFile,
    'r'
);

$aliasCsv->setFlags(
    SplFileObject::READ_CSV |
    SplFileObject::DROP_NEW_LINE
);

$aliasCsv->fgetcsv();

while (! $aliasCsv->eof()) {
    $row = $aliasCsv->fgetcsv();

    if (
        $row === false ||
        $row === [null] ||
        count($row) < 3
    ) {
        continue;
    }

    [
        $stateCode,
        $sourceName,
        $canonicalName,
    ] = array_map(
        'trim',
        $row
    );

    if (
        $stateCode === '' ||
        $sourceName === '' ||
        $canonicalName === ''
    ) {
        continue;
    }

    $aliases[
        aliasKey(
            $stateCode,
            $sourceName
        )
    ] = $canonicalName;
}

$stateCodeMap = [
    'BR' => 'BO',
    'KB' => 'KE',
];

$states = DB::table('states')
    ->select([
        'id',
        'code',
        'name',
    ])
    ->get()
    ->keyBy(
        fn ($state) =>
            strtoupper($state->code)
    );

$lgas = DB::table(
    'local_government_areas'
)
    ->select([
        'id',
        'state_id',
        'name',
        'slug',
    ])
    ->get()
    ->groupBy('state_id');

function canonicalBoundaryLga(
    string $rawStateCode,
    string $rawLgaName,
    array $stateCodeMap,
    array $aliases,
    $states,
    $lgas
): array {
    $stateCode =
        $stateCodeMap[
            strtoupper(
                trim($rawStateCode)
            )
        ]
        ?? strtoupper(
            trim($rawStateCode)
        );

    $state =
        $states->get(
            $stateCode
        );

    if ($state === null) {
        return [
            $stateCode,
            null,
            'unknown_state',
        ];
    }

    $stateLgas =
        $lgas->get(
            $state->id,
            collect()
        );

    $exact =
        $stateLgas->firstWhere(
            'slug',
            Str::slug(
                $rawLgaName
            )
        );

    if ($exact !== null) {
        return [
            $stateCode,
            $exact->name,
            'exact',
        ];
    }

    $compact =
        compactName(
            $rawLgaName
        );

    $normalized =
        $stateLgas
            ->filter(
                fn ($lga) =>
                    compactName(
                        $lga->name
                    ) === $compact
            )
            ->values();

    if ($normalized->count() === 1) {
        return [
            $stateCode,
            $normalized->first()->name,
            'normalized',
        ];
    }

    $alias =
        $aliases[
            aliasKey(
                $stateCode,
                $rawLgaName
            )
        ] ?? null;

    if ($alias !== null) {
        $target =
            $stateLgas->first(
                fn ($lga) =>
                    $lga->name === $alias
            );

        if ($target !== null) {
            return [
                $stateCode,
                $target->name,
                'reviewed_alias',
            ];
        }
    }

    return [
        $stateCode,
        null,
        'unresolved',
    ];
}

$csv = new SplFileObject(
    $reviewFile,
    'r'
);

$csv->setFlags(
    SplFileObject::READ_CSV |
    SplFileObject::DROP_NEW_LINE
);

$header = $csv->fgetcsv();

$pos = array_flip(
    array_map(
        'trim',
        $header
    )
);

$required = [
    'geoname_id',
    'place_name',
    'canonical_state_code',
    'canonical_lga_name',
    'latitude',
    'longitude',
    'hierarchy_decision',
];

foreach ($required as $column) {
    if (! isset($pos[$column])) {
        throw new RuntimeException(
            "Missing column: {$column}"
        );
    }
}

$output = fopen(
    $outputFile,
    'wb'
);

fputcsv(
    $output,
    [
        'geoname_id',
        'place_name',
        'hierarchy_decision',
        'expected_state_code',
        'expected_lga_name',
        'boundary_state_code',
        'boundary_state_name',
        'boundary_lga_code',
        'boundary_grid3_lga_name',
        'boundary_canonical_lga_name',
        'resolution_method',
        'polygon_count',
        'overall_match',
    ]
);

$total = 0;
$matches = 0;
$mismatches = 0;
$noPolygon = 0;
$multiple = 0;
$canonicalErrors = 0;
$review = [];

while (! $csv->eof()) {
    $row = $csv->fgetcsv();

    if (
        $row === false ||
        $row === [null]
    ) {
        continue;
    }

    $decision =
        trim(
            (string) (
                $row[
                    $pos[
                        'hierarchy_decision'
                    ]
                ] ?? ''
            )
        );

    if (
        ! in_array(
            $decision,
            [
                'manual_review',
                'lagos_metro_review',
            ],
            true
        )
    ) {
        continue;
    }

    $total++;

    $id =
        trim(
            (string) $row[
                $pos['geoname_id']
            ]
        );

    $place =
        trim(
            (string) $row[
                $pos['place_name']
            ]
        );

    $expectedState =
        strtoupper(
            trim(
                (string) $row[
                    $pos[
                        'canonical_state_code'
                    ]
                ]
            )
        );

    $expectedLga =
        trim(
            (string) $row[
                $pos[
                    'canonical_lga_name'
                ]
            ]
        );

    $latitude =
        (float) $row[
            $pos['latitude']
        ];

    $longitude =
        (float) $row[
            $pos['longitude']
        ];

    $response =
        queryBoundary(
            $endpoint,
            $latitude,
            $longitude
        );

    $features =
        $response['features']
        ?? [];

    if ($features === []) {
        $noPolygon++;
    }

    if (count($features) > 1) {
        $multiple++;
    }

    $best = null;
    $rowMatched = false;

    foreach ($features as $feature) {
        $attributes =
            $feature['attributes']
            ?? [];

        [
            $boundaryState,
            $boundaryLga,
            $method,
        ] = canonicalBoundaryLga(
            (string) (
                $attributes[
                    'state_code'
                ] ?? ''
            ),
            (string) (
                $attributes[
                    'lga_name'
                ] ?? ''
            ),
            $stateCodeMap,
            $aliases,
            $states,
            $lgas
        );

        if ($boundaryLga === null) {
            $canonicalErrors++;
        }

        $overall =
            $boundaryState ===
                $expectedState &&
            $boundaryLga ===
                $expectedLga;

        $candidate = [
            'attributes' =>
                $attributes,
            'state' =>
                $boundaryState,
            'lga' =>
                $boundaryLga,
            'method' =>
                $method,
            'overall' =>
                $overall,
        ];

        if (
            $best === null ||
            $overall
        ) {
            $best = $candidate;
        }

        if ($overall) {
            $rowMatched = true;
            break;
        }
    }

    if ($best === null) {
        $best = [
            'attributes' => [],
            'state' => '',
            'lga' => null,
            'method' => 'no_polygon',
            'overall' => false,
        ];
    }

    if ($rowMatched) {
        $matches++;
    } else {
        $mismatches++;

        $review[] = [
            $id,
            $place,
            $expectedState,
            $expectedLga,
            $best['state'],
            $best['lga'] ?? '',
        ];
    }

    $attributes =
        $best['attributes'];

    fputcsv(
        $output,
        [
            $id,
            $place,
            $decision,
            $expectedState,
            $expectedLga,
            $best['state'],
            $attributes[
                'state_name'
            ] ?? '',
            $attributes[
                'lga_code'
            ] ?? '',
            $attributes[
                'lga_name'
            ] ?? '',
            $best['lga'] ?? '',
            $best['method'],
            count($features),
            $rowMatched ? 1 : 0,
        ]
    );

    echo sprintf(
        "%3d/67 | %-24s | expected=%s/%-22s | boundary=%s/%-22s | %s\n",
        $total,
        $place,
        $expectedState,
        $expectedLga,
        $best['state'],
        $best['lga'] ?? '-',
        $rowMatched
            ? 'MATCH'
            : 'REVIEW'
    );
}

fclose($output);

echo PHP_EOL;
echo "========================================"
    . PHP_EOL;

echo "HIERARCHY BOUNDARY SUMMARY"
    . PHP_EOL;

echo "========================================"
    . PHP_EOL;

echo "Review rows:             {$total}"
    . PHP_EOL;

echo "Boundary matches:        {$matches}"
    . PHP_EOL;

echo "Boundary mismatches:     {$mismatches}"
    . PHP_EOL;

echo "No polygon:              {$noPolygon}"
    . PHP_EOL;

echo "Multiple polygons:       {$multiple}"
    . PHP_EOL;

echo "Canonicalization errors: {$canonicalErrors}"
    . PHP_EOL;

if ($review !== []) {
    echo PHP_EOL;
    echo "===== REMAINING MISMATCHES ====="
        . PHP_EOL;

    foreach ($review as $item) {
        echo implode(
            ' | ',
            $item
        ) . PHP_EOL;
    }
}

echo PHP_EOL;

echo "Audit CSV: {$outputFile}"
    . PHP_EOL;

echo "SHA-256: "
    . hash_file(
        'sha256',
        $outputFile
    )
    . PHP_EOL;

echo PHP_EOL;

if (
    $total === 67 &&
    $matches === 67 &&
    $mismatches === 0 &&
    $noPolygon === 0 &&
    $canonicalErrors === 0
) {
    echo "FINAL STATUS: PASS — 67/67 hierarchy locations agree with GRID3 boundaries"
        . PHP_EOL;

    echo "No BusinessFinder database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: REVIEW REQUIRED"
    . PHP_EOL;

exit(1);
