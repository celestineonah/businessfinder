<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require 'bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$file =
    'database/data/geography/geonames/city-exception-decisions.csv';

if (! is_readable($file)) {
    throw new RuntimeException(
        "Missing exception file: {$file}"
    );
}

$states = DB::table('states')
    ->select(['id', 'code', 'name'])
    ->get()
    ->keyBy(
        fn ($state) =>
            strtoupper($state->code)
    );

$lgas = DB::table('local_government_areas')
    ->select([
        'id',
        'state_id',
        'name',
    ])
    ->get()
    ->groupBy('state_id');

$csv = new SplFileObject(
    $file,
    'r'
);

$csv->setFlags(
    SplFileObject::READ_CSV |
    SplFileObject::DROP_NEW_LINE
);

$header = $csv->fgetcsv();

$expectedHeader = [
    'geoname_id',
    'city_name',
    'source_state',
    'action',
    'canonical_state_code',
    'canonical_lga_name',
    'reason',
];

if ($header !== $expectedHeader) {
    throw new RuntimeException(
        'Unexpected exception CSV header.'
    );
}

$allowedActions = [
    'accept',
    'correct',
    'reject',
    'metro_review',
];

$rows = 0;
$accept = 0;
$correct = 0;
$reject = 0;
$metro = 0;
$errors = [];
$seen = [];

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

    if (count($row) !== 7) {
        $errors[] =
            'Invalid column count.';

        continue;
    }

    [
        $geonameId,
        $city,
        $sourceState,
        $action,
        $stateCode,
        $lgaName,
        $reason,
    ] = array_map(
        'trim',
        $row
    );

    $rows++;

    if (isset($seen[$geonameId])) {
        $errors[] =
            "Duplicate GeoNames ID {$geonameId}.";

        continue;
    }

    $seen[$geonameId] = true;

    if (
        ! in_array(
            $action,
            $allowedActions,
            true
        )
    ) {
        $errors[] =
            "{$geonameId}: invalid action {$action}.";

        continue;
    }

    if ($action === 'reject') {
        $reject++;

        if (
            $stateCode !== '' ||
            $lgaName !== ''
        ) {
            $errors[] =
                "{$geonameId}: rejected row must not have canonical geography.";
        }

        continue;
    }

    if ($action === 'metro_review') {
        $metro++;

        if ($stateCode === '') {
            $errors[] =
                "{$geonameId}: metro review requires canonical state.";
        }

        if ($lgaName !== '') {
            $errors[] =
                "{$geonameId}: metro review must not pretend to have a single canonical LGA.";
        }

        continue;
    }

    if ($action === 'accept') {
        $accept++;
    }

    if ($action === 'correct') {
        $correct++;
    }

    $state = $states->get(
        strtoupper($stateCode)
    );

    if ($state === null) {
        $errors[] =
            "{$geonameId}: unknown canonical state {$stateCode}.";

        continue;
    }

    $lga = $lgas
        ->get(
            $state->id,
            collect()
        )
        ->first(
            fn ($item) =>
                $item->name === $lgaName
        );

    if ($lga === null) {
        $errors[] = sprintf(
            '%s: canonical LGA not found: %s / %s.',
            $geonameId,
            $stateCode,
            $lgaName
        );
    }
}

echo "========================================" . PHP_EOL;
echo "CITY EXCEPTION DECISIONS" . PHP_EOL;
echo "========================================" . PHP_EOL;

echo "Rows:             {$rows}" . PHP_EOL;
echo "Accepted:         {$accept}" . PHP_EOL;
echo "Corrected:        {$correct}" . PHP_EOL;
echo "Rejected:         {$reject}" . PHP_EOL;
echo "Metro review:     {$metro}" . PHP_EOL;
echo "Validation errors:"
    . count($errors)
    . PHP_EOL;

if ($errors !== []) {
    echo PHP_EOL;
    echo "===== ERRORS =====" . PHP_EOL;

    foreach ($errors as $error) {
        echo $error . PHP_EOL;
    }
}

echo PHP_EOL;

$passed =
    $rows === 7 &&
    $accept === 1 &&
    $correct === 4 &&
    $reject === 1 &&
    $metro === 1 &&
    $errors === [];

if ($passed) {
    echo "FINAL STATUS: PASS"
        . PHP_EOL;

    echo "No database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: FAILED"
    . PHP_EOL;

exit(1);
