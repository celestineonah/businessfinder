<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require 'bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$correctionFile =
    'database/data/geography/geonames/hierarchy-boundary-corrections.csv';

$auditFile =
    'storage/app/import-sources/grid3-boundaries/hierarchy-review/'
    . 'hierarchy-boundary-audit.csv';

$expectedAuditHash =
    '19a343f2756809e00a7636528e7aac78a5538382eab7ca6f9292655487b048d7';

foreach (
    [
        $correctionFile,
        $auditFile,
    ] as $file
) {
    if (! is_readable($file)) {
        throw new RuntimeException(
            "Missing required file: {$file}"
        );
    }
}

$actualHash =
    hash_file(
        'sha256',
        $auditFile
    );

if ($actualHash !== $expectedAuditHash) {
    throw new RuntimeException(
        "Boundary audit hash changed.\n"
        . "Expected: {$expectedAuditHash}\n"
        . "Actual:   {$actualHash}"
    );
}

function readCsv(
    string $file
): array {
    $csv = new SplFileObject(
        $file,
        'r'
    );

    $csv->setFlags(
        SplFileObject::READ_CSV |
        SplFileObject::DROP_NEW_LINE
    );

    $header = $csv->fgetcsv();

    $rows = [];

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
                        trim(
                            (string) $value
                        ) !== ''
                )
            ) === 0
        ) {
            continue;
        }

        $record = [];

        foreach (
            $header as $index => $column
        ) {
            $record[
                trim($column)
            ] =
                trim(
                    (string) (
                        $row[$index]
                        ?? ''
                    )
                );
        }

        $rows[] = $record;
    }

    return $rows;
}

$corrections =
    readCsv(
        $correctionFile
    );

$auditRows =
    readCsv(
        $auditFile
    );

$auditById = [];

foreach ($auditRows as $row) {
    $auditById[
        $row['geoname_id']
    ] = $row;
}

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
    ])
    ->get()
    ->groupBy('state_id');

$errors = [];

foreach ($corrections as $row) {
    $id =
        $row['geoname_id'];

    $audit =
        $auditById[$id]
        ?? null;

    if ($audit === null) {
        $errors[] =
            "{$id}: missing from boundary audit.";

        continue;
    }

    if (
        $audit['overall_match'] !== '0'
    ) {
        $errors[] =
            "{$id}: audit row was not a mismatch.";
    }

    if (
        $audit['boundary_state_code']
        !==
        $row['canonical_state_code']
    ) {
        $errors[] =
            "{$id}: corrected state disagrees with boundary.";
    }

    if (
        $audit[
            'boundary_canonical_lga_name'
        ]
        !==
        $row['canonical_lga_name']
    ) {
        $errors[] =
            "{$id}: corrected LGA disagrees with boundary.";
    }

    if (
        $row['review_status']
        !== 'reviewed'
    ) {
        $errors[] =
            "{$id}: correction is not marked reviewed.";
    }

    $state =
        $states->get(
            strtoupper(
                $row[
                    'canonical_state_code'
                ]
            )
        );

    if ($state === null) {
        $errors[] =
            "{$id}: canonical state does not exist.";

        continue;
    }

    $lga = $lgas
        ->get(
            $state->id,
            collect()
        )
        ->first(
            fn ($item) =>
                $item->name ===
                $row[
                    'canonical_lga_name'
                ]
        );

    if ($lga === null) {
        $errors[] = sprintf(
            '%s: canonical LGA does not exist: %s / %s',
            $id,
            $row[
                'canonical_state_code'
            ],
            $row[
                'canonical_lga_name'
            ]
        );
    }
}

echo "========================================"
    . PHP_EOL;

echo "HIERARCHY BOUNDARY CORRECTIONS"
    . PHP_EOL;

echo "========================================"
    . PHP_EOL;

echo "Correction rows:      "
    . count($corrections)
    . PHP_EOL;

echo "Audit SHA-256:        "
    . $actualHash
    . PHP_EOL;

echo "Validation errors:    "
    . count($errors)
    . PHP_EOL;

if ($errors !== []) {
    echo PHP_EOL;
    echo "===== ERRORS ====="
        . PHP_EOL;

    foreach ($errors as $error) {
        echo $error
            . PHP_EOL;
    }
}

echo PHP_EOL;

if (
    count($corrections) === 3 &&
    $errors === []
) {
    echo "FINAL STATUS: PASS — 3 reviewed boundary corrections validated"
        . PHP_EOL;

    echo "No database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: FAILED"
    . PHP_EOL;

exit(1);
