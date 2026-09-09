<?php

$decisionFile =
    'database/data/geography/geonames/lagos-hierarchy-decisions.csv';

$reviewFile =
    'storage/app/import-sources/geonames/city-hierarchy-review.csv';

foreach (
    [$decisionFile, $reviewFile]
    as $file
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
                        trim((string) $value) !== ''
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
            $record['geoname_id']
            ?? '';

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

        $rows[$id] = $record;
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
    '2566696',
    '2350523',
    '2350434',
    '2566676',
    '2344082',
    '2343252',
    '2337313',
    '7732748',
    '2566633',
];

$errors = [];

foreach ($expectedIds as $id) {
    if (! isset($decisions[$id])) {
        $errors[] =
            "{$id}: missing decision.";

        continue;
    }

    if (! isset($review[$id])) {
        $errors[] =
            "{$id}: missing hierarchy source row.";

        continue;
    }

    if (
        $review[$id][
            'hierarchy_decision'
        ] !==
        'lagos_metro_review'
    ) {
        $errors[] =
            "{$id}: source row is not Lagos metro review.";
    }

    $decision =
        $decisions[$id];

    if (
        ! in_array(
            $decision[
                'final_decision'
            ],
            ['city', 'area'],
            true
        )
    ) {
        $errors[] =
            "{$id}: invalid final decision.";
    }

    if (
        $decision[
            'canonical_name'
        ] === ''
    ) {
        $errors[] =
            "{$id}: canonical name missing.";
    }

    if (
        $decision[
            'review_status'
        ] !== 'reviewed'
    ) {
        $errors[] =
            "{$id}: not marked reviewed.";
    }

    if (
        $decision[
            'final_decision'
        ] === 'city' &&
        $decision[
            'parent_city'
        ] !== ''
    ) {
        $errors[] =
            "{$id}: city must not have parent_city.";
    }

    if (
        $decision[
            'final_decision'
        ] === 'area' &&
        $decision[
            'parent_city'
        ] === ''
    ) {
        $errors[] =
            "{$id}: area requires parent_city.";
    }
}

$unexpected =
    array_diff(
        array_keys($decisions),
        $expectedIds
    );

foreach ($unexpected as $id) {
    $errors[] =
        "{$id}: unexpected Lagos decision.";
}

/*
 * Ensure Lagos and Ikorodu already exist
 * in the selected hierarchy as parent cities.
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

foreach ($decisions as $id => $row) {
    $parent =
        $row['parent_city'];

    if (
        $parent !== '' &&
        ! in_array(
            $parent,
            $validParents,
            true
        )
    ) {
        $errors[] =
            "{$id}: parent city {$parent} is not selected.";
    }
}

$cityCount = count(
    array_filter(
        $decisions,
        fn ($row) =>
            $row[
                'final_decision'
            ] === 'city'
    )
);

$areaCount = count(
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

echo "LAGOS HIERARCHY DECISIONS"
    . PHP_EOL;

echo "========================================"
    . PHP_EOL;

echo "Decision rows:      "
    . count($decisions)
    . PHP_EOL;

echo "Cities:             {$cityCount}"
    . PHP_EOL;

echo "Areas:              {$areaCount}"
    . PHP_EOL;

echo "Validation errors:  "
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
    count($decisions) === 9 &&
    $cityCount === 2 &&
    $areaCount === 7 &&
    $errors === []
) {
    echo "FINAL STATUS: PASS — Lagos hierarchy review complete"
        . PHP_EOL;

    echo "No database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: FAILED"
    . PHP_EOL;

exit(1);
