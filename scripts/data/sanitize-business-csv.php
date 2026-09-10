<?php
declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Usage: php sanitize-business-csv.php INPUT OUTPUT CONFLICT_REPORT\n");
    exit(64);
}

$inputPath = $argv[1];
$outputPath = $argv[2];
$conflictPath = $argv[3];

if (!is_file($inputPath) || !is_readable($inputPath)) {
    fwrite(STDERR, "Input CSV is missing or unreadable: {$inputPath}\n");
    exit(66);
}

foreach ([dirname($outputPath), dirname($conflictPath)] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Could not create directory: {$dir}\n");
        exit(73);
    }
}

$input = new SplFileObject($inputPath, 'r');
$input->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
$input->setCsvControl(',', '"', '');

$header = $input->fgetcsv();
if ($header === false || $header === [null]) {
    fwrite(STDERR, "Input CSV has no header.\n");
    exit(65);
}

$normalizedHeader = array_map(static function ($value): string {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
    return strtolower(trim($value));
}, $header);

$externalIndex = array_search('external_id', $normalizedHeader, true);
if ($externalIndex === false) {
    fwrite(STDERR, "Input CSV is missing external_id.\n");
    exit(65);
}

$tempPath = $outputPath . '.tmp.' . getmypid();
$out = fopen($tempPath, 'wb');
if ($out === false) {
    fwrite(STDERR, "Could not open temporary output.\n");
    exit(73);
}

fputcsv($out, $header, ',', '"', '');

$seen = [];
$kept = 0;
$inputRows = 0;
$exactDuplicates = 0;
$conflicts = [];
$lineNumber = 1;

while (!$input->eof()) {
    $row = $input->fgetcsv();
    $lineNumber++;

    if ($row === false || $row === [null]) {
        continue;
    }

    $hasContent = false;
    foreach ($row as $cell) {
        if (trim((string) $cell) !== '') {
            $hasContent = true;
            break;
        }
    }
    if (!$hasContent) {
        continue;
    }

    $inputRows++;
    $externalId = trim((string) ($row[$externalIndex] ?? ''));

    if ($externalId === '') {
        fputcsv($out, $row, ',', '"', '');
        $kept++;
        continue;
    }

    $fingerprint = hash('sha256', json_encode(
        $row,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    ));

    if (!isset($seen[$externalId])) {
        $seen[$externalId] = [
            'fingerprint' => $fingerprint,
            'line' => $lineNumber,
            'row' => $row,
        ];
        fputcsv($out, $row, ',', '"', '');
        $kept++;
        continue;
    }

    if (hash_equals($seen[$externalId]['fingerprint'], $fingerprint)) {
        $exactDuplicates++;
        continue;
    }

    $conflicts[] = [
        'external_id' => $externalId,
        'first_line' => $seen[$externalId]['line'],
        'conflicting_line' => $lineNumber,
        'first_row' => $seen[$externalId]['row'],
        'conflicting_row' => $row,
    ];
}

fflush($out);
fclose($out);

$report = [
    'source_file' => realpath($inputPath) ?: $inputPath,
    'generated_at' => gmdate('c'),
    'input_rows' => $inputRows,
    'rows_kept' => $kept,
    'exact_duplicates_removed' => $exactDuplicates,
    'conflicting_duplicate_groups' => count($conflicts),
    'conflicts' => $conflicts,
];

file_put_contents(
    $conflictPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

if ($conflicts !== []) {
    @unlink($tempPath);
    echo "Sanitizer status: CONFLICT\n";
    echo "Input rows: {$inputRows}\n";
    echo "Exact duplicates removable: {$exactDuplicates}\n";
    echo "Conflicting duplicate groups: " . count($conflicts) . "\n";
    echo "Conflict report: {$conflictPath}\n";
    exit(2);
}

if (!rename($tempPath, $outputPath)) {
    @unlink($tempPath);
    fwrite(STDERR, "Could not finalize sanitized CSV.\n");
    exit(73);
}

echo "Sanitizer status: PASS\n";
echo "Input rows: {$inputRows}\n";
echo "Rows kept: {$kept}\n";
echo "Exact duplicates removed: {$exactDuplicates}\n";
echo "Conflicting duplicate groups: 0\n";
echo "Sanitized CSV: {$outputPath}\n";
echo "Audit report: {$conflictPath}\n";
exit(0);
