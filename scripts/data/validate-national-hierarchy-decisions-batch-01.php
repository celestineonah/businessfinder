<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require 'bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$decisionFile =
    'database/data/geography/geonames/'
    . 'national-hierarchy-decisions-batch-01.csv';

$reviewFile =
    'storage/app/import-sources/geonames/'
    . 'city-hierarchy-review.csv';

foreach (
    [
        $decisionFile,
        $reviewFile,
    ] as $file
) {
    if (! is_readable($file)) {
        throw new RuntimeException(
            "Missing required file: {$file}"
        );
    }
}

function readCsvById(
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

    $header =
        $csv->fgetcsv();

    if (! is_array($header)) {
        throw new RuntimeException(
            "Could not read header: {$file}"
        );
    }

    $rows = [];

    while (! $csv->eof()) {
        $row =
            $csv->fgetcsv();

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

        $id =
            $record[
                'geoname_id'
            ] ?? '';

        if ($id === '') {
            throw new RuntimeException(
                "Missing geoname_id in {$file}"
            );
        }

        if (isset($rows[$id])) {
            throw new RuntimeException(
                "Duplicate geoname_id {$id}"
            );
        }

        $rows[$id] =
            $record;
    }

    return $rows;
}

$decisions =
    readCsvById(
        $decisionFile
    );

$review =
    readCsvById(
        $reviewFile
    );

$expectedIds = [
    '2317765',
    '2328811',
    '2319668',
    '2330028',
    '2325733',
];

$errors = [];

if (count($decisions) !== 5) {
    $errors[] =
        'Expected exactly 5 decision rows.';
}

foreach ($expectedIds as $id) {
    if (! isset($decisions[$id])) {
        $errors[] =
            "{$id}: decision missing.";

        continue;
    }

    if (! isset($review[$id])) {
        $errors[] =
            "{$id}: source hierarchy row missing.";

        continue;
    }

    if (
        $review[$id][
            'hierarchy_decision'
        ] !==
        'manual_review'
    ) {
        $errors[] =
            "{$id}: source row is not manual_review.";
    }
}

$unexpected =
    array_diff(
        array_keys($decisions),
        $expectedIds
    );

foreach ($unexpected as $id) {
    $errors[] =
        "{$id}: unexpected decision row.";
}

/*
 * Load canonical geography.
 */
$states =
    DB::table('states')
        ->select([
            'id',
            'code',
            'name',
        ])
        ->get()
        ->keyBy(
            fn ($state) =>
                strtoupper(
                    $state->code
                )
        );

$lgas =
    DB::table(
        'local_government_areas'
    )
        ->select([
            'id',
            'state_id',
            'name',
        ])
        ->get()
        ->groupBy('state_id');

/*
 * Existing selected cities that may act
 * as parent cities for area decisions.
 */
$validParents = [];

foreach ($review as $row) {
    if (
        in_array(
            $row[
                'hierarchy_decision'
            ],
            [
                'city_candidate',
                'metro_city',
            ],
            true
        )
    ) {
        $validParents[] =
            $row['place_name'];
    }
}

foreach ($decisions as $row) {
    if (
        $row[
            'final_decision'
        ] === 'city'
    ) {
        $validParents[] =
            $row[
                'canonical_name'
            ];
    }
}

$validParents =
    array_values(
        array_unique(
            $validParents
        )
    );

$allowedDecisions = [
    'city',
    'area',
];

$allowedOverrides = [
    'none',
    'grid3_geography',
];

foreach ($decisions as $id => $row) {
    if (
        ! in_array(
            $row[
                'final_decision'
            ],
            $allowedDecisions,
            true
        )
    ) {
        $errors[] =
            "{$id}: invalid final_decision.";
    }

    if (
        ! in_array(
            $row[
                'source_override'
            ],
            $allowedOverrides,
            true
        )
    ) {
        $errors[] =
            "{$id}: invalid source_override.";
    }

    if (
        $row[
            'canonical_name'
        ] === ''
    ) {
        $errors[] =
            "{$id}: canonical_name missing.";
    }

    if (
        $row[
            'review_status'
        ] !== 'reviewed'
    ) {
        $errors[] =
            "{$id}: review_status must be reviewed.";
    }

    $stateCode =
        strtoupper(
            $row[
                'state_code'
            ]
        );

    $state =
        $states->get(
            $stateCode
        );

    if ($state === null) {
        $errors[] =
            "{$id}: unknown state {$stateCode}.";

        continue;
    }

    $lgaNames =
        array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        '|',
                        $row[
                            'lga_names'
                        ]
                    )
                )
            )
        );

    if ($lgaNames === []) {
        $errors[] =
            "{$id}: no canonical LGA supplied.";
    }

    $stateLgas =
        $lgas->get(
            $state->id,
            collect()
        );

    foreach ($lgaNames as $lgaName) {
        $match =
            $stateLgas->first(
                fn ($lga) =>
                    $lga->name ===
                    $lgaName
            );

        if ($match === null) {
            $errors[] =
                "{$id}: canonical LGA not found: "
                . "{$stateCode} / {$lgaName}.";
        }
    }

    if (
        $row[
            'final_decision'
        ] === 'city'
        &&
        $row[
            'parent_city'
        ] !== ''
    ) {
        $errors[] =
            "{$id}: city must not have parent_city.";
    }

    if (
        $row[
            'final_decision'
        ] === 'area'
    ) {
        if (
            $row[
                'parent_city'
            ] === ''
        ) {
            $errors[] =
                "{$id}: area requires parent_city.";
        } elseif (
            ! in_array(
                $row[
                    'parent_city'
                ],
                $validParents,
                true
            )
        ) {
            $errors[] =
                "{$id}: parent city "
                . $row[
                    'parent_city'
                ]
                . ' is not selected.';
        }
    }

    /*
     * Normal records must preserve the
     * original reviewed GeoNames point.
     */
    if (
        $row[
            'source_override'
        ] === 'none'
        &&
        isset($review[$id])
    ) {
        $sourceLat =
            (float) $review[$id][
                'latitude'
            ];

        $sourceLng =
            (float) $review[$id][
                'longitude'
            ];

        $decisionLat =
            (float) $row[
                'latitude'
            ];

        $decisionLng =
            (float) $row[
                'longitude'
            ];

        if (
            abs(
                $sourceLat -
                $decisionLat
            ) > 0.000001
            ||
            abs(
                $sourceLng -
                $decisionLng
            ) > 0.000001
        ) {
            $errors[] =
                "{$id}: non-override coordinates changed.";
        }

        if (
            $row[
                'replacement_external_id'
            ] !== ''
        ) {
            $errors[] =
                "{$id}: replacement ID supplied without override.";
        }
    }
}

/*
 * Modakeke is the one explicitly reviewed
 * settlement-identity/geography replacement.
 */
$modakeke =
    $decisions['2330028']
    ?? null;

if ($modakeke !== null) {
    if (
        $modakeke[
            'final_decision'
        ] !== 'city'
        ||
        $modakeke[
            'canonical_name'
        ] !== 'Modakeke'
        ||
        $modakeke[
            'state_code'
        ] !== 'OS'
        ||
        $modakeke[
            'lga_names'
        ] !== 'Ife East'
        ||
        $modakeke[
            'source_override'
        ] !== 'grid3_geography'
        ||
        $modakeke[
            'replacement_external_id'
        ] !== '12085650'
        ||
        abs(
            (float) $modakeke[
                'latitude'
            ] -
            7.479317
        ) > 0.000001
        ||
        abs(
            (float) $modakeke[
                'longitude'
            ] -
            4.531302
        ) > 0.000001
    ) {
        $errors[] =
            '2330028: Modakeke reviewed replacement does not match locked evidence.';
    }
}

/*
 * Zaria metropolitan treatment:
 * only the two complete core LGAs are linked.
 */
$zaria =
    $decisions['2317765']
    ?? null;

if (
    $zaria !== null
    &&
    $zaria[
        'lga_names'
    ] !==
    'Zaria|Sabon Gari'
) {
    $errors[] =
        '2317765: Zaria must use core LGA links Zaria|Sabon Gari.';
}

$cityCount =
    count(
        array_filter(
            $decisions,
            fn ($row) =>
                $row[
                    'final_decision'
                ] === 'city'
        )
    );

$areaCount =
    count(
        array_filter(
            $decisions,
            fn ($row) =>
                $row[
                    'final_decision'
                ] === 'area'
        )
    );

echo "========================================"
    . PHP_EOL;

echo "NATIONAL HIERARCHY BATCH 01"
    . PHP_EOL;

echo "========================================"
    . PHP_EOL;

echo "Decision rows:       "
    . count($decisions)
    . PHP_EOL;

echo "Cities:              {$cityCount}"
    . PHP_EOL;

echo "Areas:               {$areaCount}"
    . PHP_EOL;

echo "GRID3 overrides:     "
    . count(
        array_filter(
            $decisions,
            fn ($row) =>
                $row[
                    'source_override'
                ] ===
                'grid3_geography'
        )
    )
    . PHP_EOL;

echo "Validation errors:   "
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
    count($decisions) === 5
    &&
    $cityCount === 4
    &&
    $areaCount === 1
    &&
    $errors === []
) {
    echo "FINAL STATUS: PASS — national hierarchy batch 01 complete"
        . PHP_EOL;

    echo "No database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: FAILED"
    . PHP_EOL;

exit(1);
