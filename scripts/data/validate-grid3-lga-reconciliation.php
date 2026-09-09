<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$app = require 'bootstrap/app.php';

$app->make(
    Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$gridFile =
    'storage/app/import-sources/grid3-settlements/state-lga-pairs.json';

$aliasFile =
    'database/data/geography/grid3/grid3-lga-aliases.csv';

$stateCodeMap = [
    'BR' => 'BO',
    'KB' => 'KE',
];

function compactName(string $name): string
{
    $name = mb_strtolower(
        trim($name),
        'UTF-8'
    );

    $name = str_replace(
        '&',
        'and',
        $name
    );

    return preg_replace(
        '/[^a-z0-9]+/u',
        '',
        $name
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

if (! is_readable($gridFile)) {
    throw new RuntimeException(
        "Missing GRID3 source: {$gridFile}"
    );
}

if (! is_readable($aliasFile)) {
    throw new RuntimeException(
        "Missing alias file: {$aliasFile}"
    );
}

/*
 * Load reviewed aliases.
 */
$aliases = [];
$aliasRows = 0;

$csv = new SplFileObject(
    $aliasFile,
    'r'
);

$csv->setFlags(
    SplFileObject::READ_CSV |
    SplFileObject::DROP_NEW_LINE
);

$header = $csv->fgetcsv();

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

    if (count($row) !== 3) {
        throw new RuntimeException(
            'Invalid alias CSV column count.'
        );
    }

    [
        $stateCode,
        $sourceName,
        $canonicalName,
    ] = array_map(
        'trim',
        $row
    );

    $key = aliasKey(
        $stateCode,
        $sourceName
    );

    if (isset($aliases[$key])) {
        throw new RuntimeException(
            "Duplicate alias key: {$key}"
        );
    }

    $aliases[$key] = [
        'state_code' => strtoupper(
            $stateCode
        ),
        'source_name' => $sourceName,
        'canonical_name' =>
            $canonicalName,
    ];

    $aliasRows++;
}

if ($aliasRows !== 48) {
    throw new RuntimeException(
        "Expected 48 reviewed aliases; found {$aliasRows}."
    );
}

/*
 * Load BusinessFinder canonical geography.
 */
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
        'administrative_type',
    ])
    ->get()
    ->groupBy('state_id');

if ($states->count() !== 37) {
    throw new RuntimeException(
        'BusinessFinder must contain exactly 37 jurisdictions.'
    );
}

if (
    $lgas->flatten(1)->count()
    !== 774
) {
    throw new RuntimeException(
        'BusinessFinder must contain exactly 774 LGAs/Area Councils.'
    );
}

/*
 * Validate every alias target exists.
 */
foreach ($aliases as $alias) {
    $state = $states->get(
        $alias['state_code']
    );

    if ($state === null) {
        throw new RuntimeException(
            "Alias references unknown state {$alias['state_code']}."
        );
    }

    $target = $lgas
        ->get(
            $state->id,
            collect()
        )
        ->first(
            fn ($lga) =>
                $lga->name ===
                $alias['canonical_name']
        );

    if ($target === null) {
        throw new RuntimeException(
            sprintf(
                'Alias target not found: %s / %s',
                $alias['state_code'],
                $alias['canonical_name']
            )
        );
    }
}

/*
 * Load GRID3 administrative pairs.
 */
$data = json_decode(
    file_get_contents($gridFile),
    true
);

if (! is_array($data)) {
    throw new RuntimeException(
        'Invalid GRID3 JSON.'
    );
}

if (! empty($data['error'])) {
    throw new RuntimeException(
        json_encode(
            $data['error']
        )
    );
}

$sourcePairs = [];
$exact = 0;
$normalized = 0;
$reviewed = 0;
$unresolved = [];
$resolvedTargets = [];
$usedAliases = [];

foreach (
    $data['features'] ?? []
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
        $stateCodeMap[$gridStateCode]
        ?? $gridStateCode;

    $sourceName = trim(
        (string) (
            $a['lganame']
            ?? ''
        )
    );

    if (
        $stateCode === '' ||
        $sourceName === ''
    ) {
        throw new RuntimeException(
            'Blank GRID3 state/LGA value encountered.'
        );
    }

    $sourceKey =
        $stateCode
        . '|'
        . compactName(
            $sourceName
        );

    if (isset($sourcePairs[$sourceKey])) {
        continue;
    }

    $sourcePairs[$sourceKey] = true;

    $state = $states->get(
        $stateCode
    );

    if ($state === null) {
        $unresolved[] = [
            $stateCode,
            $sourceName,
            'unknown_state',
        ];

        continue;
    }

    $stateLgas = $lgas->get(
        $state->id,
        collect()
    );

    /*
     * Gate 1: exact canonical slug.
     */
    $slugMatch = $stateLgas
        ->firstWhere(
            'slug',
            Str::slug(
                $sourceName
            )
        );

    if ($slugMatch !== null) {
        $match = $slugMatch;
        $method = 'exact';
        $exact++;
    } else {
        /*
         * Gate 2:
         * deterministic punctuation /
         * whitespace normalization only.
         */
        $compact = compactName(
            $sourceName
        );

        $matches = $stateLgas
            ->filter(
                fn ($lga) =>
                    compactName(
                        $lga->name
                    ) === $compact
            )
            ->values();

        if ($matches->count() === 1) {
            $match =
                $matches->first();

            $method =
                'normalized_unique';

            $normalized++;
        } else {
            /*
             * Gate 3:
             * explicit reviewed GRID3 alias.
             */
            $key = aliasKey(
                $stateCode,
                $sourceName
            );

            $alias =
                $aliases[$key]
                ?? null;

            if ($alias === null) {
                $unresolved[] = [
                    $stateCode,
                    $sourceName,
                    'no_reviewed_alias',
                ];

                continue;
            }

            $match = $stateLgas
                ->first(
                    fn ($lga) =>
                        $lga->name ===
                        $alias[
                            'canonical_name'
                        ]
                );

            if ($match === null) {
                $unresolved[] = [
                    $stateCode,
                    $sourceName,
                    'alias_target_missing',
                ];

                continue;
            }

            $method =
                'reviewed_alias';

            $reviewed++;

            $usedAliases[
                $key
            ] = true;
        }
    }

    $targetKey =
        $stateCode
        . '|'
        . $match->id;

    if (
        isset(
            $resolvedTargets[
                $targetKey
            ]
        )
    ) {
        throw new RuntimeException(
            sprintf(
                'More than one GRID3 LGA resolved to %s / %s.',
                $stateCode,
                $match->name
            )
        );
    }

    $resolvedTargets[
        $targetKey
    ] = [
        'source_name' =>
            $sourceName,
        'canonical_name' =>
            $match->name,
        'method' =>
            $method,
    ];
}

$unusedAliases = array_diff_key(
    $aliases,
    $usedAliases
);

echo "========================================" . PHP_EOL;
echo "GRID3 ↔ BUSINESSFINDER VALIDATION" . PHP_EOL;
echo "========================================" . PHP_EOL;

echo 'GRID3 distinct pairs:     '
    . count($sourcePairs)
    . PHP_EOL;

echo "Exact matches:            {$exact}" . PHP_EOL;
echo "Normalized unique:        {$normalized}" . PHP_EOL;
echo "Reviewed aliases:         {$reviewed}" . PHP_EOL;

echo 'Resolved canonical LGAs:  '
    . count($resolvedTargets)
    . PHP_EOL;

echo 'Unresolved:               '
    . count($unresolved)
    . PHP_EOL;

echo 'Alias rows:               '
    . $aliasRows
    . PHP_EOL;

echo 'Unused alias rows:        '
    . count($unusedAliases)
    . PHP_EOL;

echo PHP_EOL;

if ($unresolved !== []) {
    echo "===== UNRESOLVED =====" . PHP_EOL;

    foreach ($unresolved as $row) {
        echo implode(
            ' | ',
            $row
        ) . PHP_EOL;
    }
}

if ($unusedAliases !== []) {
    echo PHP_EOL;
    echo "===== UNUSED ALIASES =====" . PHP_EOL;

    foreach ($unusedAliases as $alias) {
        echo sprintf(
            "%s | %s | %s\n",
            $alias['state_code'],
            $alias['source_name'],
            $alias['canonical_name']
        );
    }
}

$passed =
    count($sourcePairs) === 774 &&
    $exact === 707 &&
    $normalized === 19 &&
    $reviewed === 48 &&
    count($resolvedTargets) === 774 &&
    $unresolved === [] &&
    $unusedAliases === [];

echo PHP_EOL;

if ($passed) {
    echo "FINAL STATUS: PASS — 774/774 reconciled without fuzzy matching"
        . PHP_EOL;

    echo "No database rows were changed."
        . PHP_EOL;

    exit(0);
}

echo "FINAL STATUS: FAILED"
    . PHP_EOL;

exit(1);
