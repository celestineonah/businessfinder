<?php

$candidateFile =
    'storage/app/import-sources/geonames/city-candidates.csv';

$unresolved = [
    '2350249',
    '2322552',
    '2327233',
    '2332459',
    '2328090',
    '2327223',
    '2346812',
];

$wanted = array_fill_keys(
    $unresolved,
    true
);

$csv = new SplFileObject(
    $candidateFile,
    'r'
);

$csv->setFlags(
    SplFileObject::READ_CSV |
    SplFileObject::DROP_NEW_LINE
);

$header = $csv->fgetcsv();
$positions = array_flip($header);

foreach ($csv as $row) {
    if (
        ! is_array($row) ||
        $row === [null]
    ) {
        continue;
    }

    $id =
        trim(
            (string) (
                $row[
                    $positions[
                        'geoname_id'
                    ]
                ] ?? ''
            )
        );

    if (! isset($wanted[$id])) {
        continue;
    }

    echo implode(
        ' | ',
        [
            $id,
            $row[
                $positions['name']
            ],
            $row[
                $positions[
                    'state_name'
                ]
            ],
            $row[
                $positions[
                    'latitude'
                ]
            ],
            $row[
                $positions[
                    'longitude'
                ]
            ],
            $row[
                $positions[
                    'population'
                ]
            ],
        ]
    ) . PHP_EOL;
}
