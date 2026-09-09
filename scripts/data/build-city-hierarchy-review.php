<?php

$root =
    'storage/app/import-sources/geonames';

$candidateFile =
    "{$root}/city-candidates.csv";

$resolvedFile =
    "{$root}/city-candidates-resolved.csv";

$exceptionFile =
    'database/data/geography/geonames/city-exception-decisions.csv';

$boundaryCorrectionFile =
    'database/data/geography/geonames/hierarchy-boundary-corrections.csv';

$outputFile =
    "{$root}/city-hierarchy-review.csv";

foreach (
    [
        $candidateFile,
        $resolvedFile,
        $exceptionFile,
        $boundaryCorrectionFile,
    ] as $file
) {
    if (! is_readable($file)) {
        throw new RuntimeException(
            "Missing required file: {$file}"
        );
    }
}

function compactName(
    string $value
): string {
    $value = mb_strtolower(
        trim($value),
        'UTF-8'
    );

    return preg_replace(
        '/[^a-z0-9]+/u',
        '',
        $value
    );
}

function readCsvById(
    string $file,
    string $idColumn
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

    if (! is_array($header)) {
        throw new RuntimeException(
            "Could not read header: {$file}"
        );
    }

    $positions = array_flip(
        array_map(
            'trim',
            $header
        )
    );

    if (! isset($positions[$idColumn])) {
        throw new RuntimeException(
            "Missing {$idColumn}: {$file}"
        );
    }

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

        $id =
            $record[$idColumn]
            ?? '';

        if ($id === '') {
            continue;
        }

        if (isset($rows[$id])) {
            throw new RuntimeException(
                "Duplicate {$idColumn} {$id} in {$file}"
            );
        }

        $rows[$id] = $record;
    }

    return $rows;
}

$candidates = readCsvById(
    $candidateFile,
    'geoname_id'
);

$resolved = readCsvById(
    $resolvedFile,
    'geoname_id'
);

$exceptions = readCsvById(
    $exceptionFile,
    'geoname_id'
);

$boundaryCorrections = readCsvById(
    $boundaryCorrectionFile,
    'geoname_id'
);

if (count($boundaryCorrections) !== 3) {
    throw new RuntimeException(
        'Expected exactly 3 reviewed boundary corrections.'
    );
}

if (count($candidates) !== 269) {
    throw new RuntimeException(
        'Expected exactly 269 GeoNames candidates.'
    );
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
        'source_state',
        'canonical_state_code',
        'canonical_state_name',
        'canonical_lga_name',
        'feature_code',
        'population',
        'latitude',
        'longitude',
        'is_state_capital',
        'suggested_is_major',
        'exception_action',
        'hierarchy_decision',
        'decision_reason',
    ]
);

$counts = [
    'city_candidate' => 0,
    'manual_review' => 0,
    'lagos_metro_review' => 0,
    'metro_city' => 0,
    'reject' => 0,
];

$manualRows = [];
$lagosRows = [];

foreach (
    $candidates as $id => $candidate
) {
    $featureCode =
        $candidate[
            'feature_code'
        ];

    $placeName =
        $candidate['name'];

    $sourceState =
        $candidate['state_name'];

    $exception =
        $exceptions[$id]
        ?? null;

    $resolvedRow =
        $resolved[$id]
        ?? null;

    $stateCode =
        $resolvedRow[
            'state_code'
        ] ?? '';

    $stateName =
        $resolvedRow[
            'state_name'
        ] ?? '';

    $canonicalLga =
        $resolvedRow[
            'canonical_lga_name'
        ] ?? '';

    $boundaryCorrection =
        $boundaryCorrections[$id]
        ?? null;

    if ($boundaryCorrection !== null) {
        $stateCode =
            $boundaryCorrection[
                'canonical_state_code'
            ];

        $canonicalLga =
            $boundaryCorrection[
                'canonical_lga_name'
            ];
    }

    $exceptionAction = '';

    /*
     * Apply explicit reviewed exceptions first.
     */
    if ($exception !== null) {
        $exceptionAction =
            $exception['action'];

        if (
            $exceptionAction ===
            'reject'
        ) {
            $decision = 'reject';

            $reason =
                'reviewed_source_conflict';

            $stateCode = '';
            $stateName = '';
            $canonicalLga = '';
        } elseif (
            $exceptionAction ===
            'metro_review'
        ) {
            /*
             * Lagos itself is retained as the
             * metro-level city candidate, but no
             * single-LGA relationship is invented.
             */
            $decision =
                'metro_city';

            $reason =
                'reviewed_multi_lga_metro';

            $stateCode =
                $exception[
                    'canonical_state_code'
                ];

            $stateName =
                'Lagos';

            $canonicalLga = '';
        } else {
            /*
             * accept / correct:
             * use the reviewed canonical geography.
             */
            $stateCode =
                $exception[
                    'canonical_state_code'
                ];

            $canonicalLga =
                $exception[
                    'canonical_lga_name'
                ];

            /*
             * State name is not critical to the
             * classification here. Keep source-style
             * display where resolved row is absent.
             */
            if ($stateName === '') {
                $stateName =
                    $stateCode;
            }

            /*
             * Continue below so PPL exceptions still
             * receive hierarchy review.
             */
            $decision = null;
            $reason = null;
        }
    } else {
        $decision = null;
        $reason = null;
    }

    /*
     * Strong administrative-seat candidates.
     */
    if ($decision === null) {
        if (
            in_array(
                $featureCode,
                [
                    'PPLC',
                    'PPLA',
                    'PPLA2',
                ],
                true
            )
        ) {
            $decision =
                'city_candidate';

            $reason =
                'geonames_administrative_seat';
        } elseif (
            $featureCode === 'PPL'
        ) {
            /*
             * Lagos PPL rows require dedicated metro
             * hierarchy work. Do not auto-promote or
             * auto-demote them.
             */
            if ($stateCode === 'LA') {
                $decision =
                    'lagos_metro_review';

                $reason =
                    'lagos_ppl_requires_metro_review';
            } elseif (
                $canonicalLga !== '' &&
                compactName(
                    $placeName
                ) ===
                compactName(
                    $canonicalLga
                )
            ) {
                /*
                 * Conservative deterministic signal:
                 * populated place name equals its
                 * canonical LGA name.
                 */
                $decision =
                    'city_candidate';

                $reason =
                    'ppl_name_matches_canonical_lga';
            } else {
                $decision =
                    'manual_review';

                $reason =
                    'generic_ppl_hierarchy_ambiguous';
            }
        } else {
            throw new RuntimeException(
                "Unexpected feature code {$featureCode} for {$id}"
            );
        }
    }

    if (! isset($counts[$decision])) {
        throw new RuntimeException(
            "Unexpected decision {$decision}"
        );
    }

    $counts[$decision]++;

    $outputRow = [
        $id,
        $placeName,
        $sourceState,
        $stateCode,
        $stateName,
        $canonicalLga,
        $featureCode,
        $candidate['population'],
        $candidate['latitude'],
        $candidate['longitude'],
        $candidate[
            'is_state_capital'
        ],
        $candidate[
            'suggested_is_major'
        ],
        $exceptionAction,
        $decision,
        $reason,
    ];

    fputcsv(
        $output,
        $outputRow
    );

    if (
        $decision ===
        'manual_review'
    ) {
        $manualRows[] =
            $outputRow;
    }

    if (
        $decision ===
        'lagos_metro_review'
    ) {
        $lagosRows[] =
            $outputRow;
    }
}

fclose($output);

$decisionTotal =
    array_sum($counts);

echo "========================================"
    . PHP_EOL;

echo "BUSINESSFINDER CITY HIERARCHY REVIEW"
    . PHP_EOL;

echo "========================================"
    . PHP_EOL;

echo "Source candidates:       "
    . count($candidates)
    . PHP_EOL;

echo "Classified rows:         "
    . $decisionTotal
    . PHP_EOL;

echo "City candidates:         "
    . $counts['city_candidate']
    . PHP_EOL;

echo "Metro city:              "
    . $counts['metro_city']
    . PHP_EOL;

echo "Lagos metro review:      "
    . $counts['lagos_metro_review']
    . PHP_EOL;

echo "Manual review:           "
    . $counts['manual_review']
    . PHP_EOL;

echo "Rejected:                "
    . $counts['reject']
    . PHP_EOL;

echo PHP_EOL;

echo "===== LAGOS PPL REVIEW ====="
    . PHP_EOL;

foreach ($lagosRows as $row) {
    echo sprintf(
        "%-22s | pop=%-8s | LGA=%s | %s\n",
        $row[1],
        $row[7],
        $row[5] ?: '-',
        $row[0]
    );
}

echo PHP_EOL;

echo "===== NON-LAGOS MANUAL REVIEW ====="
    . PHP_EOL;

usort(
    $manualRows,
    function ($a, $b) {
        $state =
            strcmp(
                $a[3],
                $b[3]
            );

        if ($state !== 0) {
            return $state;
        }

        return strcmp(
            $a[1],
            $b[1]
        );
    }
);

foreach ($manualRows as $row) {
    echo sprintf(
        "%s | %-24s | %-26s | pop=%-8s | LGA=%s | %s\n",
        $row[3] ?: '-',
        $row[1],
        $row[2],
        $row[7],
        $row[5] ?: '-',
        $row[0]
    );
}

echo PHP_EOL;

echo "Review CSV: {$outputFile}"
    . PHP_EOL;

echo "SHA-256: "
    . hash_file(
        'sha256',
        $outputFile
    )
    . PHP_EOL;

echo PHP_EOL;

$passed =
    count($candidates) === 269 &&
    $decisionTotal === 269 &&
    $counts['metro_city'] === 1 &&
    $counts['reject'] === 1;

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
