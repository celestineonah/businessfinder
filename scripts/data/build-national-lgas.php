<?php

require 'vendor/autoload.php';

use Illuminate\Support\Str;

$base = 'https://cvr.inecnigeria.org';

$ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/140 Safari/537.36';

$states = [
    1  => ['name' => 'ABIA',        'code' => 'AB', 'expected' => 17],
    2  => ['name' => 'ADAMAWA',     'code' => 'AD', 'expected' => 21],
    3  => ['name' => 'AKWA IBOM',   'code' => 'AK', 'expected' => 31],
    4  => ['name' => 'ANAMBRA',     'code' => 'AN', 'expected' => 21],
    5  => ['name' => 'BAUCHI',      'code' => 'BA', 'expected' => 20],
    6  => ['name' => 'BAYELSA',     'code' => 'BY', 'expected' => 8],
    7  => ['name' => 'BENUE',       'code' => 'BE', 'expected' => 23],
    8  => ['name' => 'BORNO',       'code' => 'BO', 'expected' => 27],
    9  => ['name' => 'CROSS RIVER', 'code' => 'CR', 'expected' => 18],
    10 => ['name' => 'DELTA',       'code' => 'DE', 'expected' => 25],
    11 => ['name' => 'EBONYI',      'code' => 'EB', 'expected' => 13],
    12 => ['name' => 'EDO',         'code' => 'ED', 'expected' => 18],
    13 => ['name' => 'EKITI',       'code' => 'EK', 'expected' => 16],
    14 => ['name' => 'ENUGU',       'code' => 'EN', 'expected' => 17],
    15 => ['name' => 'FCT',         'code' => 'FC', 'expected' => 6],
    16 => ['name' => 'GOMBE',       'code' => 'GO', 'expected' => 11],
    17 => ['name' => 'IMO',         'code' => 'IM', 'expected' => 27],
    18 => ['name' => 'JIGAWA',      'code' => 'JI', 'expected' => 27],
    19 => ['name' => 'KADUNA',      'code' => 'KD', 'expected' => 23],
    20 => ['name' => 'KANO',        'code' => 'KN', 'expected' => 44],
    21 => ['name' => 'KATSINA',     'code' => 'KT', 'expected' => 34],
    22 => ['name' => 'KEBBI',       'code' => 'KE', 'expected' => 21],
    23 => ['name' => 'KOGI',        'code' => 'KO', 'expected' => 21],
    24 => ['name' => 'KWARA',       'code' => 'KW', 'expected' => 16],
    25 => ['name' => 'LAGOS',       'code' => 'LA', 'expected' => 20],
    26 => ['name' => 'NASARAWA',    'code' => 'NA', 'expected' => 13],
    27 => ['name' => 'NIGER',       'code' => 'NI', 'expected' => 25],
    28 => ['name' => 'OGUN',        'code' => 'OG', 'expected' => 20],
    29 => ['name' => 'ONDO',        'code' => 'ON', 'expected' => 18],
    30 => ['name' => 'OSUN',        'code' => 'OS', 'expected' => 30],
    31 => ['name' => 'OYO',         'code' => 'OY', 'expected' => 33],
    32 => ['name' => 'PLATEAU',     'code' => 'PL', 'expected' => 17],
    33 => ['name' => 'RIVERS',      'code' => 'RI', 'expected' => 23],
    34 => ['name' => 'SOKOTO',      'code' => 'SO', 'expected' => 23],
    35 => ['name' => 'TARABA',      'code' => 'TA', 'expected' => 16],
    36 => ['name' => 'YOBE',        'code' => 'YO', 'expected' => 17],
    37 => ['name' => 'ZAMFARA',     'code' => 'ZA', 'expected' => 14],
];

$runId = gmdate('Ymd-His');

$root = 'storage/app/import-sources/inec-cvr';
$runDir = "{$root}/national-{$runId}";
$htmlDir = "{$runDir}/html";

mkdir($htmlDir, 0775, true);

file_put_contents(
    "{$root}/latest-run.txt",
    $runDir . PHP_EOL
);

function fetchWithRetry(
    string $url,
    string $ua,
    int $attempts = 5
): string {
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
            ],
        ]);

        $html = curl_exec($ch);

        $status = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $error = curl_error($ch);

        curl_close($ch);

        if (
            $html !== false &&
            $status >= 200 &&
            $status < 300
        ) {
            return $html;
        }

        fwrite(
            STDERR,
            sprintf(
                "Attempt %d/%d failed: HTTP %d %s\n",
                $attempt,
                $attempts,
                $status,
                $error
            )
        );

        sleep($attempt * 2);
    }

    throw new RuntimeException(
        "Could not download {$url}"
    );
}

function parseBrowsePage(string $html): array
{
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    if (! $dom->loadHTML($html)) {
        throw new RuntimeException(
            'Could not parse INEC browse page.'
        );
    }

    $xpath = new DOMXPath($dom);

    $rows = [];

    foreach ($xpath->query('//table//tr[td]') as $tr) {
        $cells = $xpath->query('./td', $tr);

        if ($cells->length < 3) {
            continue;
        }

        $state = cleanText(
            $cells->item(1)->textContent
        );

        $lga = cleanText(
            $cells->item(2)->textContent
        );

        $centreId = null;

        foreach ($xpath->query('.//a[@href]', $tr) as $link) {
            $href = $link->getAttribute('href');

            if (
                preg_match(
                    '~/locator/viewCentre/(\d+)~',
                    $href,
                    $matches
                )
            ) {
                $centreId = (int) $matches[1];
                break;
            }
        }

        $rows[] = [
            'state' => $state,
            'lga' => $lga,
            'centre_id' => $centreId,
        ];
    }

    $pages = 1;

    if (
        preg_match(
            '/Page\s+\d+\s+of\s+(\d+)/i',
            $dom->textContent,
            $matches
        )
    ) {
        $pages = max(
            1,
            (int) $matches[1]
        );
    }

    return [$rows, $pages];
}

function cleanText(?string $value): string
{
    return trim(
        preg_replace(
            '/\s+/u',
            ' ',
            (string) $value
        )
    );
}

function normalizedLgaName(string $raw): string
{
    $name = cleanText($raw);

    // Typographical normalization only.
    $name = preg_replace(
        '/\s*\/\s*/u',
        '/',
        $name
    );

    $name = preg_replace(
        '/\s*-\s*/u',
        '-',
        $name
    );

    return mb_convert_case(
        $name,
        MB_CASE_TITLE,
        'UTF-8'
    );
}

function parseCentrePage(
    string $html,
    string $expectedState
): ?array {
    $text = preg_replace(
        '/<[^>]+>/',
        ' ',
        $html
    );

    $text = html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $text = cleanText($text);

    if (
        ! preg_match(
            '/State\s*:?\s*'
            . preg_quote($expectedState, '/')
            . '\b/i',
            $text
        )
    ) {
        return null;
    }

    $lga = null;

    if (
        preg_match(
            '/State\s*:?\s*'
            . preg_quote($expectedState, '/')
            . '\s*\|\s*Local Government\s*:?\s*'
            . '(.*?)\s+Address\b/iu',
            $text,
            $matches
        )
    ) {
        $candidate = cleanText(
            $matches[1]
        );

        if (
            $candidate !== '' &&
            $candidate !== '-'
        ) {
            $lga = $candidate;
        }
    }

    if ($lga === null) {
        if (
            preg_match(
                '/Name\s*:?\s*INEC\s+(.+?)\s+LGA\s+OFFICE'
                . '\s+State\s*:?\s*'
                . preg_quote($expectedState, '/')
                . '\b/iu',
                $text,
                $matches
            )
        ) {
            $lga = cleanText(
                $matches[1]
            );
        }
    }

    if ($lga === null) {
        return null;
    }

    return [
        'state' => $expectedState,
        'lga' => $lga,
    ];
}

function fetchEkiti(
    string $base,
    string $ua,
    string $htmlDir
): array {
    $rows = [];

    /*
     * INEC centre IDs:
     * 1254 = Ekiti headquarters
     * 1255–1270 = 16 Ekiti LGA offices.
     */
    foreach (range(1255, 1270) as $centreId) {
        $url = "{$base}/locator/viewCentre/{$centreId}";

        echo "    Ekiti centre {$centreId}" . PHP_EOL;

        $html = fetchWithRetry(
            $url,
            $ua
        );

        file_put_contents(
            "{$htmlDir}/ekiti-centre-{$centreId}.html",
            $html
        );

        $parsed = parseCentrePage(
            $html,
            'EKITI'
        );

        if ($parsed === null) {
            throw new RuntimeException(
                "Could not parse Ekiti centre {$centreId}"
            );
        }

        $rows[] = [
            ...$parsed,
            'centre_id' => $centreId,
            'source_url' => $url,
        ];

        usleep(300000);
    }

    return $rows;
}

$allRows = [];
$stateCounts = [];
$sourceFiles = [];
$errors = [];

foreach ($states as $stateId => $state) {
    echo PHP_EOL;
    echo sprintf(
        "%02d %-20s ",
        $stateId,
        $state['name']
    );

    try {
        if ($stateId === 13) {
            echo "CENTRE FALLBACK" . PHP_EOL;

            $rows = fetchEkiti(
                $base,
                $ua,
                $htmlDir
            );
        } else {
            echo "BROWSE" . PHP_EOL;

            $url = "{$base}/locator/browse/{$stateId}/";

            $html = fetchWithRetry(
                $url,
                $ua
            );

            $pagePath = sprintf(
                '%s/state-%02d-page-1.html',
                $htmlDir,
                $stateId
            );

            file_put_contents(
                $pagePath,
                $html
            );

            $sourceFiles[] = $pagePath;

            [$firstRows, $pages] =
                parseBrowsePage($html);

            $rows = [];

            foreach ($firstRows as $row) {
                if (
                    mb_strtoupper($row['state']) !==
                    $state['name']
                ) {
                    continue;
                }

                if (
                    $row['lga'] === '' ||
                    $row['lga'] === '-'
                ) {
                    continue;
                }

                $row['source_url'] = $url;
                $rows[] = $row;
            }

            for (
                $page = 2;
                $page <= $pages;
                $page++
            ) {
                $pageUrl =
                    "{$base}/locator/browse/state_id:"
                    . $stateId
                    . "/page:"
                    . $page;

                $pageHtml = fetchWithRetry(
                    $pageUrl,
                    $ua
                );

                $pagePath = sprintf(
                    '%s/state-%02d-page-%d.html',
                    $htmlDir,
                    $stateId,
                    $page
                );

                file_put_contents(
                    $pagePath,
                    $pageHtml
                );

                $sourceFiles[] = $pagePath;

                [$pageRows] =
                    parseBrowsePage($pageHtml);

                foreach ($pageRows as $row) {
                    if (
                        mb_strtoupper($row['state']) !==
                        $state['name']
                    ) {
                        continue;
                    }

                    if (
                        $row['lga'] === '' ||
                        $row['lga'] === '-'
                    ) {
                        continue;
                    }

                    $row['source_url'] = $pageUrl;
                    $rows[] = $row;
                }

                usleep(300000);
            }
        }

        $unique = [];

        foreach ($rows as $row) {
            $rawName = cleanText(
                $row['lga']
            );

            $normalizedName =
                normalizedLgaName($rawName);

            /*
             * INEC CVR labels AMAC simply as "MUNICIPAL".
             * Use the official public administrative name while
             * retaining the untouched INEC value in raw_lga_name.
             */
            if (
                $stateId === 15 &&
                mb_strtoupper($rawName) === 'MUNICIPAL'
            ) {
                $normalizedName =
                    'Abuja Municipal Area Council';
            }

            $key = Str::slug(
                $normalizedName
            );

            if ($key === '') {
                throw new RuntimeException(
                    "Invalid slug for {$rawName}"
                );
            }

            if (isset($unique[$key])) {
                $existingName =
                    $unique[$key]['lga_name'];

                /*
                 * INEC may publish more than one CVR centre for the
                 * same administrative LGA. Collapse only when the
                 * normalized LGA names are actually identical.
                 *
                 * A slug collision between different names remains
                 * a hard validation failure.
                 */
                if (
                    mb_strtoupper($existingName) !==
                    mb_strtoupper($normalizedName)
                ) {
                    throw new RuntimeException(
                        "LGA slug collision in {$state['name']}: "
                        . "{$existingName} vs {$normalizedName}"
                    );
                }

                $duplicateCentreId =
                    $row['centre_id'] ?? null;

                echo "    NOTE: extra INEC centre collapsed: "
                    . $state['name']
                    . " / "
                    . $normalizedName
                    . " / centre "
                    . ($duplicateCentreId ?? 'unknown')
                    . PHP_EOL;

                continue;
            }

            $centreId =
                $row['centre_id'] ?? null;

            $unique[$key] = [
                'inec_state_id' => $stateId,
                'state_name' => $state['name'],
                'state_code' => $state['code'],
                'raw_lga_name' => $rawName,
                'lga_name' => $normalizedName,
                'administrative_type' =>
                    $stateId === 15
                        ? 'area_council'
                        : 'lga',
                'external_id' =>
                    $centreId !== null
                        ? 'INEC-CVR:' . $centreId
                        : 'INEC-CVR:'
                            . $stateId
                            . ':'
                            . $key,
                'source_url' =>
                    $row['source_url'] ?? null,
            ];
        }

        $count = count($unique);

        $stateCounts[$state['code']] = $count;

        echo sprintf(
            "    %d/%d",
            $count,
            $state['expected']
        );

        if ($count !== $state['expected']) {
            echo " FAIL" . PHP_EOL;

            $errors[] = sprintf(
                '%s expected %d but found %d',
                $state['name'],
                $state['expected'],
                $count
            );

            continue;
        }

        echo " PASS" . PHP_EOL;

        foreach ($unique as $row) {
            $allRows[] = $row;
        }
    } catch (Throwable $exception) {
        echo "    ERROR: "
            . $exception->getMessage()
            . PHP_EOL;

        $errors[] =
            $state['name']
            . ': '
            . $exception->getMessage();
    }

    usleep(300000);
}

$total = count($allRows);

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "NATIONAL VALIDATION" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo "Jurisdictions: " . count($stateCounts) . "/37" . PHP_EOL;
echo "LGA total:     {$total}/774" . PHP_EOL;
echo "Errors:        " . count($errors) . PHP_EOL;

if (
    count($stateCounts) !== 37 ||
    $total !== 774 ||
    $errors !== []
) {
    foreach ($errors as $error) {
        echo "ERROR: {$error}" . PHP_EOL;
    }

    echo "FINAL STATUS: FAILED" . PHP_EOL;
    exit(1);
}

usort(
    $allRows,
    fn ($a, $b) =>
        [$a['inec_state_id'], $a['lga_name']]
        <=>
        [$b['inec_state_id'], $b['lga_name']]
);

$importCsv =
    "{$runDir}/nigeria-lgas.csv";

$auditCsv =
    "{$runDir}/nigeria-lgas-audit.csv";

$handle = fopen(
    $importCsv,
    'wb'
);

fputcsv(
    $handle,
    [
        'state_code',
        'lga_name',
        'administrative_type',
        'external_id',
    ]
);

foreach ($allRows as $row) {
    fputcsv(
        $handle,
        [
            $row['state_code'],
            $row['lga_name'],
            $row['administrative_type'],
            $row['external_id'],
        ]
    );
}

fclose($handle);

$handle = fopen(
    $auditCsv,
    'wb'
);

fputcsv(
    $handle,
    [
        'inec_state_id',
        'state_name',
        'state_code',
        'raw_lga_name',
        'normalized_lga_name',
        'administrative_type',
        'external_id',
        'source_url',
    ]
);

foreach ($allRows as $row) {
    fputcsv(
        $handle,
        [
            $row['inec_state_id'],
            $row['state_name'],
            $row['state_code'],
            $row['raw_lga_name'],
            $row['lga_name'],
            $row['administrative_type'],
            $row['external_id'],
            $row['source_url'],
        ]
    );
}

fclose($handle);

foreach (
    glob("{$htmlDir}/*.html") ?: []
    as $file
) {
    $sourceFiles[] = $file;
}

$sourceFiles = array_values(
    array_unique($sourceFiles)
);

sort($sourceFiles);

$manifest = [
    'source' => 'INEC CVR Live Locator',
    'source_base_url' => $base,
    'generated_at_utc' =>
        gmdate(DATE_ATOM),
    'jurisdictions' => 37,
    'records' => 774,
    'state_counts' => $stateCounts,
    'files' => [
        'import_csv' => [
            'path' => basename($importCsv),
            'sha256' => hash_file(
                'sha256',
                $importCsv
            ),
        ],
        'audit_csv' => [
            'path' => basename($auditCsv),
            'sha256' => hash_file(
                'sha256',
                $auditCsv
            ),
        ],
    ],
    'source_snapshots' => array_map(
        fn ($file) => [
            'file' => basename($file),
            'sha256' => hash_file(
                'sha256',
                $file
            ),
        ],
        $sourceFiles
    ),
];

file_put_contents(
    "{$runDir}/manifest.json",
    json_encode(
        $manifest,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) . PHP_EOL
);

echo PHP_EOL;
echo "Import CSV: {$importCsv}" . PHP_EOL;
echo "Audit CSV:  {$auditCsv}" . PHP_EOL;

echo "CSV SHA256: "
    . hash_file(
        'sha256',
        $importCsv
    )
    . PHP_EOL;

echo "FINAL STATUS: PASS — canonical 774-row dataset created"
    . PHP_EOL;
