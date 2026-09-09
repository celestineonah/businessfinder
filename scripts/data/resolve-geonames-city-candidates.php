<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$app = require 'bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$candidateFile =
    'storage/app/import-sources/geonames/city-candidates.csv';

$gridFile =
    'storage/app/import-sources/grid3-settlements/state-lga-pairs.json';

$aliasFile =
    'database/data/geography/grid3/grid3-lga-aliases.csv';

$outputFile =
    'storage/app/import-sources/geonames/city-candidates-resolved.csv';

if (! is_readable($candidateFile)) {
    throw new RuntimeException(
        "Missing candidate file: {$candidateFile}"
    );
}

if (! is_readable($gridFile)) {
    throw new RuntimeException(
        "Missing GRID3 LGA file: {$gridFile}"
    );
}

if (! is_readable($aliasFile)) {
    throw new RuntimeException(
        "Missing GRID3 alias file: {$aliasFile}"
    );
}

function compactName(string $value): string
{
    $value = mb_strtolower(
        trim($value),
        'UTF-8'
    );

    $value = preg_replace(
        '/\s+state$/u',
        '',
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
    return strtoupper(trim($stateCode))
        . '|'
        . mb_strtolower(
            trim($name),
            'UTF-8'
        );
}

/*
 * Load canonical BusinessFinder states.
 */
$states = DB::table('states')
    ->select([
        'id',
        'code',
        'name',
    ])
    ->get();

$statesByNormalizedName = [];

foreach ($states as $state) {
    $statesByNormalizedName[
        compactName($state->name)
    ] = $state;
}

/*
 * Some public source naming differs from our canonical naming.
 */
$stateNameAliases = [
    compactName('FCT') => 'FC',
    compactName('Federal Capital Territory') => 'FC',
];

/*
 * Load canonical LGAs.
 */
$lgas = DB::table(
    'local_government_areas'
)
    ->select([
        'id',
        'state_id',
        'name',
        'slug',
        'administrative_type',
    ])
    ->get();

$lgasByStateAndName = [];

foreach ($lgas as $lga) {
    $lgasByStateAndName[
        $lga->state_id
    ][
        mb_strtolower(
            $lga->name,
            'UTF-8'
        )
    ] = $lga;
}

/*
 * Load explicit GRID3 aliases.
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

/*
 * Load GRID3 LGA code crosswalk.
 */
$grid = json_decode(
    file_get_contents($gridFile),
    true
);

if (! is_array($grid)) {
    throw new RuntimeException(
        'Invalid GRID3 LGA JSON.'
    );
}

$gridStateCodeMap = [
    'BR' => 'BO',
    'KB' => 'KE',
];

$gridByLgaCode = [];

foreach (
    $grid['features'] ?? []
    as $feature
) {
    $a = $feature['attributes'] ?? [];

    $gridStateCode = strtoupper(
        trim(
            (string) (
                $a['statecode']
                ?? ''
            )
        )
    );

    $stateCode =
        $gridStateCodeMap[$gridStateCode]
        ?? $gridStateCode;

    $lgaCode = trim(
        (string) (
            $a['lgacode']
                ?? ''
        )
    );

    $lgaName = trim(
        (string) (
            $a['lganame']
                ?? ''
        )
    );

    if (
        $lgaCode === '' ||
        $lgaName === ''
    ) {
        continue;
    }

    if (isset($gridByLgaCode[$lgaCode])) {
        $existing =
            $gridByLgaCode[$lgaCode];

        if (
            $existing['state_code']
                !== $stateCode ||
            $existing['lga_name']
                !== $lgaName
        ) {
            throw new RuntimeException(
                "GRID3 LGA code {$lgaCode} is not globally unique."
            );
        }

        continue;
    }

    $gridByLgaCode[$lgaCode] = [
        'state_code' =>
            $stateCode,
        'lga_name' =>
            $lgaName,
    ];
}

/*
 * Read GeoNames candidates.
 */
$csv = new SplFileObject(
    $candidateFile,
    'r'
);

$csv->setFlags(
    SplFileObject::READ_CSV |
    SplFileObject::DROP_NEW_LINE
);

$header = $csv->fgetcsv();

$positions = array_flip(
    array_map(
        'trim',
        $header
    )
);

$required = [
    'geoname_id',
    'name',
    'state_name',
    'admin2_code',
    'feature_code',
    'population',
    'latitude',
    'longitude',
    'is_state_capital',
    'suggested_is_major',
    'selection_reason',
];

foreach ($required as $column) {
    if (! isset($positions[$column])) {
        throw new RuntimeException(
            "Missing candidate column {$column}."
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
        'city_name',
        'state_code',
        'state_name',
        'geonames_admin2_code',
        'grid3_lga_name',
        'canonical_lga_name',
        'resolution_method',
        'feature_code',
        'population',
        'latitude',
        'longitude',
        'is_state_capital',
        'suggested_is_major',
        'selection_reason',
    ]
);

$total = 0;
$resolved = 0;
$blankAdmin2 = [];
$unknownAdmin2 = [];
$stateMismatches = [];
$canonicalFailures = [];

while (! $csv->eof()) {
    $row = $csv->fgetcsv();

    if (
        $row === false ||
        $row === [null]
    ) {
        continue;
    }

    if (
        count(
            array_filter(
                $row,
                fn ($value) =>
                    trim((string) $value) !== ''
            )
        ) === 0
    ) {
        continue;
    }

    $total++;

    $get = fn (string $column) =>
        trim(
            (string) $row[
                $positions[$column]
            ]
        );

    $geonameId =
        $get('geoname_id');

    $cityName =
        $get('name');

    $geonamesStateName =
        $get('state_name');

    $admin2 =
        $get('admin2_code');

    if ($admin2 === '') {
        $blankAdmin2[] = [
            'geoname_id' =>
                $geonameId,
            'city_name' =>
                $cityName,
            'state_name' =>
                $geonamesStateName,
        ];

        continue;
    }

    $gridLga =
        $gridByLgaCode[$admin2]
        ?? null;

    if ($gridLga === null) {
        $unknownAdmin2[] = [
            $geonameId,
            $cityName,
            $geonamesStateName,
            $admin2,
        ];

        continue;
    }

    /*
     * Resolve GeoNames state deterministically.
     */
    $normalizedState =
        compactName(
            $geonamesStateName
        );

    $bfState = null;

    if (
        isset(
            $stateNameAliases[
                $normalizedState
            ]
        )
    ) {
        $targetCode =
            $stateNameAliases[
                $normalizedState
            ];

        $bfState = $states->first(
            fn ($state) =>
                strtoupper(
                    $state->code
                ) === $targetCode
        );
    } else {
        $bfState =
            $statesByNormalizedName[
                $normalizedState
            ] ?? null;
    }

    if ($bfState === null) {
        $stateMismatches[] = [
            $geonameId,
            $cityName,
            $geonamesStateName,
            'state_not_found',
        ];

        continue;
    }

    if (
        strtoupper(
            $bfState->code
        ) !==
        $gridLga['state_code']
    ) {
        $stateMismatches[] = [
            $geonameId,
            $cityName,
            $geonamesStateName,
            $admin2,
            $bfState->code,
            $gridLga[
                'state_code'
            ],
            $gridLga[
                'lga_name'
            ],
        ];

        continue;
    }

    /*
     * Resolve GRID3 source name to canonical
     * BusinessFinder LGA using exactly the same
     * three-gate strategy already proven:
     *
     * 1. exact slug
     * 2. punctuation normalization
     * 3. reviewed alias
     */
    $stateLgas = $lgas->where(
        'state_id',
        $bfState->id
    );

    $gridName =
        $gridLga['lga_name'];

    $exact = $stateLgas->first(
        fn ($lga) =>
            $lga->slug ===
            Str::slug($gridName)
    );

    if ($exact !== null) {
        $canonical =
            $exact;

        $method =
            'admin2+exact';
    } else {
        $compactGrid =
            compactName($gridName);

        $normalizedMatches =
            $stateLgas->filter(
                fn ($lga) =>
                    compactName(
                        $lga->name
                    ) ===
                    $compactGrid
            );

        if (
            $normalizedMatches->count()
                === 1
        ) {
            $canonical =
                $normalizedMatches
                    ->first();

            $method =
                'admin2+normalized';
        } else {
            $alias =
                $aliases[
                    aliasKey(
                        $bfState->code,
                        $gridName
                    )
                ] ?? null;

            if ($alias === null) {
                $canonicalFailures[] = [
                    $geonameId,
                    $cityName,
                    $bfState->code,
                    $admin2,
                    $gridName,
                    'no_alias',
                ];

                continue;
            }

            $canonical =
                $stateLgas->first(
                    fn ($lga) =>
                        $lga->name ===
                        $alias
                );

            if ($canonical === null) {
                $canonicalFailures[] = [
                    $geonameId,
                    $cityName,
                    $bfState->code,
                    $admin2,
                    $gridName,
                    'alias_target_missing',
                ];

                continue;
            }

            $method =
                'admin2+reviewed_alias';
        }
    }

    fputcsv(
        $output,
        [
            $geonameId,
            $cityName,
            strtoupper(
                $bfState->code
            ),
            $bfState->name,
            $admin2,
            $gridName,
            $canonical->name,
            $method,
            $get('feature_code'),
            $get('population'),
            $get('latitude'),
            $get('longitude'),
            $get(
                'is_state_capital'
            ),
            $get(
                'suggested_is_major'
            ),
            $get(
                'selection_reason'
            ),
        ]
    );

    $resolved++;
}

fclose($output);

echo "========================================" . PHP_EOL;
echo "GEONAMES CITY → LGA RESOLUTION" . PHP_EOL;
echo "========================================" . PHP_EOL;

echo "Candidates:             {$total}"
    . PHP_EOL;

echo "Resolved by admin2:     {$resolved}"
    . PHP_EOL;

echo "Blank admin2:           "
    . count($blankAdmin2)
    . PHP_EOL;

echo "Unknown admin2:         "
    . count($unknownAdmin2)
    . PHP_EOL;

echo "State mismatches:       "
    . count($stateMismatches)
    . PHP_EOL;

echo "Canonical failures:     "
    . count($canonicalFailures)
    . PHP_EOL;

echo PHP_EOL;

if ($blankAdmin2 !== []) {
    echo "===== BLANK ADMIN2 ====="
        . PHP_EOL;

    foreach ($blankAdmin2 as $row) {
        echo sprintf(
            "%s | %-25s | %s\n",
            $row['geoname_id'],
            $row['city_name'],
            $row['state_name']
        );
    }
}

if ($unknownAdmin2 !== []) {
    echo PHP_EOL;
    echo "===== UNKNOWN ADMIN2 ====="
        . PHP_EOL;

    foreach ($unknownAdmin2 as $row) {
        echo implode(
            ' | ',
            $row
        ) . PHP_EOL;
    }
}

if ($stateMismatches !== []) {
    echo PHP_EOL;
    echo "===== STATE MISMATCHES ====="
        . PHP_EOL;

    foreach ($stateMismatches as $row) {
        echo implode(
            ' | ',
            $row
        ) . PHP_EOL;
    }
}

if ($canonicalFailures !== []) {
    echo PHP_EOL;
    echo "===== CANONICAL FAILURES ====="
        . PHP_EOL;

    foreach ($canonicalFailures as $row) {
        echo implode(
            ' | ',
            $row
        ) . PHP_EOL;
    }
}

echo PHP_EOL;

echo "Resolved CSV: {$outputFile}"
    . PHP_EOL;

echo "CSV SHA-256: "
    . hash_file(
        'sha256',
        $outputFile
    )
    . PHP_EOL;

echo PHP_EOL;

if (
    $total === 269 &&
    $resolved === 262 &&
    count($blankAdmin2) === 7 &&
    $unknownAdmin2 === [] &&
    $stateMismatches === [] &&
    $canonicalFailures === []
) {
    echo "FINAL STATUS: PASS — 262/269 resolved deterministically; 7 require review"
        . PHP_EOL;

    echo "No database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: REVIEW REQUIRED"
    . PHP_EOL;

exit(1);
