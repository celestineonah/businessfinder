<?php

$root =
    'storage/app/import-sources/geonames';

$source =
    "{$root}/extracted/NG.txt";

$admin1Source =
    "{$root}/admin1CodesASCII.txt";

$output =
    "{$root}/city-candidates.csv";

if (! is_readable($source)) {
    throw new RuntimeException(
        "GeoNames Nigeria file is not readable: {$source}"
    );
}

if (! is_readable($admin1Source)) {
    throw new RuntimeException(
        "GeoNames admin1 file is not readable: {$admin1Source}"
    );
}

/*
 * BusinessFinder V1 selection rule:
 *
 * - national/state capitals always qualify
 * - other populated places require population >= 15,000
 * - only city/town-oriented feature codes are admitted
 *
 * No database writes occur in this script.
 */
$allowedCodes = [
    'PPLC',
    'PPLA',
    'PPLA2',
    'PPL',
];

$capitalCodes = [
    'PPLC',
    'PPLA',
];

$minimumPopulation = 15000;
$majorPopulation = 250000;

/*
 * Load GeoNames first-order administrative names.
 */
$admin1 = [];

$file = new SplFileObject(
    $admin1Source,
    'r'
);

while (! $file->eof()) {
    $line = trim(
        (string) $file->fgets()
    );

    if ($line === '') {
        continue;
    }

    $cols = explode(
        "\t",
        $line
    );

    if (count($cols) < 4) {
        continue;
    }

    $code = trim($cols[0]);

    if (
        ! str_starts_with(
            $code,
            'NG.'
        )
    ) {
        continue;
    }

    $admin1[$code] = [
        'name' => trim($cols[1]),
        'ascii_name' => trim($cols[2]),
        'geoname_id' => trim($cols[3]),
    ];
}

/*
 * Scan Nigeria GeoNames dump.
 */
$input = new SplFileObject(
    $source,
    'r'
);

$candidates = [];

$stats = [
    'source_rows' => 0,
    'populated_rows' => 0,
    'eligible_feature_codes' => 0,
    'selected' => 0,
    'selected_capitals' => 0,
    'selected_population' => 0,
    'major' => 0,
    'missing_state' => 0,
    'missing_admin2' => 0,
];

$featureCounts = [];
$stateCounts = [];
$duplicateKeys = [];

while (! $input->eof()) {
    $line = trim(
        (string) $input->fgets()
    );

    if ($line === '') {
        continue;
    }

    $cols = explode(
        "\t",
        $line
    );

    if (count($cols) < 19) {
        continue;
    }

    $stats['source_rows']++;

    $geonameId = trim($cols[0]);
    $name = trim($cols[1]);
    $asciiName = trim($cols[2]);

    $latitude = trim($cols[4]);
    $longitude = trim($cols[5]);

    $featureClass = trim($cols[6]);
    $featureCode = trim($cols[7]);

    $countryCode = trim($cols[8]);

    $admin1Code = trim($cols[10]);
    $admin2Code = trim($cols[11]);

    $population =
        (int) trim($cols[14]);

    if (
        $countryCode !== 'NG' ||
        $featureClass !== 'P'
    ) {
        continue;
    }

    $stats['populated_rows']++;

    if (
        ! in_array(
            $featureCode,
            $allowedCodes,
            true
        )
    ) {
        continue;
    }

    $stats['eligible_feature_codes']++;

    $isStateCapital = in_array(
        $featureCode,
        $capitalCodes,
        true
    );

    if (
        ! $isStateCapital &&
        $population < $minimumPopulation
    ) {
        continue;
    }

    $admin1Key =
        'NG.' . $admin1Code;

    $state =
        $admin1[$admin1Key]
        ?? null;

    if ($state === null) {
        $stats['missing_state']++;
    }

    if ($admin2Code === '') {
        $stats['missing_admin2']++;
    }

    $isMajor =
        $isStateCapital ||
        $population >= $majorPopulation;

    $selectionReason =
        $isStateCapital
            ? 'administrative_capital'
            : 'population_ge_15000';

    if ($isStateCapital) {
        $stats['selected_capitals']++;
    } else {
        $stats['selected_population']++;
    }

    if ($isMajor) {
        $stats['major']++;
    }

    $stateName =
        $state['name']
        ?? '';

    $duplicateKey =
        mb_strtolower(
            $stateName . '|' . $name,
            'UTF-8'
        );

    $duplicateKeys[
        $duplicateKey
    ][] = $geonameId;

    $stateCounts[
        $stateName !== ''
            ? $stateName
            : '(unknown)'
    ] =
        ($stateCounts[
            $stateName !== ''
                ? $stateName
                : '(unknown)'
        ] ?? 0)
        + 1;

    $featureCounts[
        $featureCode
    ] =
        ($featureCounts[
            $featureCode
        ] ?? 0)
        + 1;

    $candidates[] = [
        'geoname_id' =>
            $geonameId,
        'name' =>
            $name,
        'ascii_name' =>
            $asciiName,
        'state_geonames_code' =>
            $admin1Code,
        'state_name' =>
            $stateName,
        'admin2_code' =>
            $admin2Code,
        'feature_code' =>
            $featureCode,
        'population' =>
            $population,
        'latitude' =>
            $latitude,
        'longitude' =>
            $longitude,
        'is_state_capital' =>
            $isStateCapital ? 1 : 0,
        'suggested_is_major' =>
            $isMajor ? 1 : 0,
        'selection_reason' =>
            $selectionReason,
    ];

    $stats['selected']++;
}

usort(
    $candidates,
    function ($a, $b) {
        $state =
            strcasecmp(
                $a['state_name'],
                $b['state_name']
            );

        if ($state !== 0) {
            return $state;
        }

        return strcasecmp(
            $a['name'],
            $b['name']
        );
    }
);

ksort($stateCounts);
ksort($featureCounts);

$handle = fopen(
    $output,
    'wb'
);

fputcsv(
    $handle,
    [
        'geoname_id',
        'name',
        'ascii_name',
        'state_geonames_code',
        'state_name',
        'admin2_code',
        'feature_code',
        'population',
        'latitude',
        'longitude',
        'is_state_capital',
        'suggested_is_major',
        'selection_reason',
    ]
);

foreach ($candidates as $row) {
    fputcsv(
        $handle,
        array_values($row)
    );
}

fclose($handle);

$duplicates = array_filter(
    $duplicateKeys,
    fn ($ids) =>
        count($ids) > 1
);

echo "========================================" . PHP_EOL;
echo "GEONAMES CITY CANDIDATES" . PHP_EOL;
echo "========================================" . PHP_EOL;

echo "Source rows:             "
    . $stats['source_rows']
    . PHP_EOL;

echo "Populated rows:          "
    . $stats['populated_rows']
    . PHP_EOL;

echo "Eligible place rows:     "
    . $stats['eligible_feature_codes']
    . PHP_EOL;

echo "Selected candidates:     "
    . $stats['selected']
    . PHP_EOL;

echo "Administrative capitals: "
    . $stats['selected_capitals']
    . PHP_EOL;

echo "Population-selected:     "
    . $stats['selected_population']
    . PHP_EOL;

echo "Suggested major:         "
    . $stats['major']
    . PHP_EOL;

echo "States represented:      "
    . count($stateCounts)
    . PHP_EOL;

echo "Missing state mapping:   "
    . $stats['missing_state']
    . PHP_EOL;

echo "Blank admin2 codes:      "
    . $stats['missing_admin2']
    . PHP_EOL;

echo "Duplicate state/name:    "
    . count($duplicates)
    . PHP_EOL;

echo PHP_EOL;
echo "===== SELECTED FEATURE CODES ====="
    . PHP_EOL;

foreach ($featureCounts as $code => $count) {
    echo sprintf(
        "%-8s %d\n",
        $code,
        $count
    );
}

echo PHP_EOL;
echo "===== STATE COVERAGE ====="
    . PHP_EOL;

foreach ($stateCounts as $state => $count) {
    echo sprintf(
        "%-25s %d\n",
        $state,
        $count
    );
}

if ($duplicates !== []) {
    echo PHP_EOL;
    echo "===== DUPLICATE STATE/NAME ====="
        . PHP_EOL;

    foreach ($duplicates as $key => $ids) {
        echo $key
            . ' | '
            . implode(',', $ids)
            . PHP_EOL;
    }
}

echo PHP_EOL;
echo "Candidate CSV: {$output}"
    . PHP_EOL;

echo "CSV SHA-256: "
    . hash_file(
        'sha256',
        $output
    )
    . PHP_EOL;

echo PHP_EOL;
echo "No database rows were changed."
    . PHP_EOL;
