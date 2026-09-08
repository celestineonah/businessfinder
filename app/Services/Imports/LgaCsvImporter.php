<?php

namespace App\Services\Imports;

use App\Models\ImportBatch;
use App\Models\ImportFailure;
use App\Models\ImportRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SplFileObject;
use Throwable;

class LgaCsvImporter
{
    public function run(
        string $file,
        string $sourceName,
        ?string $sourceUrl = null,
        bool $apply = false,
        int $expectedRows = 774,
    ): array {
        $path = realpath($file);

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException(
                "CSV file does not exist or is not readable: {$file}"
            );
        }

        $sourceName = trim($sourceName);

        if ($sourceName === '') {
            throw new InvalidArgumentException(
                'A non-empty source name is required.'
            );
        }

        if ($expectedRows < 0) {
            throw new InvalidArgumentException(
                'Expected row count cannot be negative.'
            );
        }

        $sourceHash = hash_file('sha256', $path);

        $states = DB::table('states')
            ->select(['id', 'code', 'name', 'is_fct'])
            ->get()
            ->keyBy(fn ($state) => strtoupper($state->code));

        [$rows, $failures, $inputRows] = $this->parse(
            $path,
            $states,
        );

        if ($expectedRows > 0 && $inputRows !== $expectedRows) {
            $failures[] = [
                'row_number' => null,
                'external_key' => null,
                'severity' => 'error',
                'code' => 'unexpected_row_count',
                'message' => sprintf(
                    'Expected %d data rows but found %d.',
                    $expectedRows,
                    $inputRows,
                ),
                'payload' => [
                    'expected' => $expectedRows,
                    'actual' => $inputRows,
                ],
            ];
        }

        if ($expectedRows === 774) {
            $presentCodes = array_values(array_unique(
                array_column($rows, 'state_code')
            ));

            foreach ($states->keys() as $stateCode) {
                if (! in_array($stateCode, $presentCodes, true)) {
                    $failures[] = [
                        'row_number' => null,
                        'external_key' => $stateCode,
                        'severity' => 'error',
                        'code' => 'missing_state',
                        'message' =>
                            "No valid LGA rows were found for state code {$stateCode}.",
                        'payload' => [
                            'state_code' => $stateCode,
                        ],
                    ];
                }
            }
        }

        $summary = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'file' => $path,
            'source_hash' => $sourceHash,
            'rows_total' => $inputRows,
            'rows_valid' => count($rows),
            'failures' => $failures,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'batch_uuid' => null,
        ];

        // Dry-run means absolutely no import audit or geography DB writes.
        if (! $apply) {
            return $summary;
        }

        $batch = ImportBatch::create([
            'uuid' => Str::uuid()->toString(),
            'import_type' => 'geography',
            'source_name' => $sourceName,
            'source_url' => $sourceUrl ?: null,
            'original_filename' => basename($path),
            'source_hash' => $sourceHash,
            'status' => 'running',
            'rows_total' => $inputRows,
            'metadata' => [
                'entity_type' => 'lga',
                'expected_rows' => $expectedRows,
                'importer' => 'businessfinder:import-lgas',
            ],
            'started_at' => now(),
        ]);

        $summary['batch_uuid'] = $batch->uuid;

        if ($failures !== []) {
            foreach ($failures as $failure) {
                ImportFailure::create([
                    'import_batch_id' => $batch->id,
                    ...$failure,
                ]);
            }

            $failedRows = collect($failures)
                ->pluck('row_number')
                ->filter()
                ->unique()
                ->count();

            if ($failedRows === 0) {
                $failedRows = 1;
            }

            $batch->update([
                'status' => 'failed',
                'rows_failed' => $failedRows,
                'completed_at' => now(),
            ]);

            return $summary;
        }

        try {
            $counts = DB::transaction(function () use ($rows, $batch) {
                $inserted = 0;
                $updated = 0;
                $skipped = 0;

                foreach ($rows as $row) {
                    $existing = DB::table('local_government_areas')
                        ->where('state_id', $row['state_id'])
                        ->where('slug', $row['slug'])
                        ->first();

                    if ($existing === null) {
                        $entityId = DB::table('local_government_areas')
                            ->insertGetId([
                                'state_id' => $row['state_id'],
                                'name' => $row['name'],
                                'slug' => $row['slug'],
                                'administrative_type' =>
                                    $row['administrative_type'],
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                        $action = 'inserted';
                        $inserted++;
                    } else {
                        $entityId = $existing->id;

                        $changes = [];

                        if ($existing->name !== $row['name']) {
                            $changes['name'] = $row['name'];
                        }

                        if (
                            $existing->administrative_type !==
                            $row['administrative_type']
                        ) {
                            $changes['administrative_type'] =
                                $row['administrative_type'];
                        }

                        if (! (bool) $existing->is_active) {
                            $changes['is_active'] = true;
                        }

                        if ($changes !== []) {
                            $changes['updated_at'] = now();

                            DB::table('local_government_areas')
                                ->where('id', $entityId)
                                ->update($changes);

                            $action = 'updated';
                            $updated++;
                        } else {
                            $action = 'skipped';
                            $skipped++;
                        }
                    }

                    $fingerprintPayload = [
                        'state_code' => $row['state_code'],
                        'name' => $row['name'],
                        'slug' => $row['slug'],
                        'administrative_type' =>
                            $row['administrative_type'],
                        'external_id' => $row['external_id'],
                    ];

                    ImportRecord::create([
                        'import_batch_id' => $batch->id,
                        'row_number' => $row['row_number'],
                        'entity_type' => 'lga',
                        'entity_id' => $entityId,
                        'external_key' => $row['external_key'],
                        'action' => $action,
                        'fingerprint' => hash(
                            'sha256',
                            json_encode(
                                $fingerprintPayload,
                                JSON_UNESCAPED_SLASHES |
                                JSON_UNESCAPED_UNICODE
                            )
                        ),
                        'payload' => $fingerprintPayload,
                    ]);
                }

                return compact('inserted', 'updated', 'skipped');
            });

            $batch->update([
                'status' => 'completed',
                'rows_succeeded' =>
                    $counts['inserted'] + $counts['updated'],
                'rows_skipped' => $counts['skipped'],
                'completed_at' => now(),
            ]);

            return array_merge($summary, $counts);
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            ImportFailure::create([
                'import_batch_id' => $batch->id,
                'severity' => 'error',
                'code' => 'import_exception',
                'message' => $exception->getMessage(),
                'payload' => [
                    'exception' => $exception::class,
                ],
            ]);

            throw $exception;
        }
    }

    private function parse(
        string $path,
        $states,
    ): array {
        $csv = new SplFileObject($path, 'r');
        $csv->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::DROP_NEW_LINE
        );

        $header = $csv->fgetcsv();

        if ($header === false || $header === [null]) {
            return [
                [],
                [[
                    'row_number' => null,
                    'external_key' => null,
                    'severity' => 'error',
                    'code' => 'missing_header',
                    'message' => 'CSV header row is missing.',
                    'payload' => null,
                ]],
                0,
            ];
        }

        $header = array_map(function ($value) {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            return strtolower(trim($value));
        }, $header);

        $required = [
            'state_code',
            'lga_name',
            'administrative_type',
        ];

        $missing = array_values(array_diff($required, $header));

        if ($missing !== []) {
            return [
                [],
                [[
                    'row_number' => 1,
                    'external_key' => null,
                    'severity' => 'error',
                    'code' => 'missing_columns',
                    'message' =>
                        'Missing required CSV columns: ' .
                        implode(', ', $missing),
                    'payload' => [
                        'header' => $header,
                    ],
                ]],
                0,
            ];
        }

        $indexes = array_flip($header);

        $rows = [];
        $failures = [];
        $seen = [];
        $inputRows = 0;
        $lineNumber = 1;

        while (! $csv->eof()) {
            $record = $csv->fgetcsv();
            $lineNumber++;

            if ($record === false || $record === [null]) {
                continue;
            }

            $hasContent = collect($record)->contains(
                fn ($value) => trim((string) $value) !== ''
            );

            if (! $hasContent) {
                continue;
            }

            $inputRows++;

            $value = static function (
                string $column
            ) use ($record, $indexes): string {
                if (! array_key_exists($column, $indexes)) {
                    return '';
                }

                return trim(
                    (string) ($record[$indexes[$column]] ?? '')
                );
            };

            $stateCode = strtoupper($value('state_code'));

            $name = preg_replace(
                '/\s+/u',
                ' ',
                $value('lga_name')
            );

            $rawType = strtolower($value('administrative_type'));

            $normalizedType = preg_replace(
                '/[\s-]+/',
                '_',
                $rawType
            );

            $administrativeType = match ($normalizedType) {
                'lga',
                'local_government_area' => 'lga',

                'area_council',
                'area_councils' => 'area_council',

                default => null,
            };

            $externalId = $value('external_id') ?: null;

            $payload = [
                'state_code' => $stateCode,
                'lga_name' => $name,
                'administrative_type' => $rawType,
                'external_id' => $externalId,
            ];

            if (! $states->has($stateCode)) {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $stateCode ?: null,
                    'severity' => 'error',
                    'code' => 'unknown_state',
                    'message' =>
                        "Unknown state code '{$stateCode}'.",
                    'payload' => $payload,
                ];

                continue;
            }

            if ($name === '') {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $stateCode,
                    'severity' => 'error',
                    'code' => 'missing_lga_name',
                    'message' => 'LGA/Area Council name is required.',
                    'payload' => $payload,
                ];

                continue;
            }

            if ($administrativeType === null) {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $stateCode . '|' . $name,
                    'severity' => 'error',
                    'code' => 'invalid_administrative_type',
                    'message' =>
                        "Invalid administrative type '{$rawType}'.",
                    'payload' => $payload,
                ];

                continue;
            }

            $state = $states->get($stateCode);

            if (
                (bool) $state->is_fct &&
                $administrativeType !== 'area_council'
            ) {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $stateCode . '|' . $name,
                    'severity' => 'error',
                    'code' => 'fct_requires_area_council',
                    'message' =>
                        'FCT geography rows must use area_council.',
                    'payload' => $payload,
                ];

                continue;
            }

            if (
                ! (bool) $state->is_fct &&
                $administrativeType !== 'lga'
            ) {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $stateCode . '|' . $name,
                    'severity' => 'error',
                    'code' => 'state_requires_lga',
                    'message' =>
                        'Non-FCT geography rows must use lga.',
                    'payload' => $payload,
                ];

                continue;
            }

            $slug = Str::slug($name);

            if ($slug === '') {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $stateCode,
                    'severity' => 'error',
                    'code' => 'invalid_slug',
                    'message' =>
                        'The LGA name could not produce a valid slug.',
                    'payload' => $payload,
                ];

                continue;
            }

            $externalKey = $stateCode . '|' . $slug;

            if (isset($seen[$externalKey])) {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => $externalKey,
                    'severity' => 'error',
                    'code' => 'duplicate_lga',
                    'message' =>
                        "Duplicate geography row '{$externalKey}'.",
                    'payload' => [
                        ...$payload,
                        'first_row' => $seen[$externalKey],
                    ],
                ];

                continue;
            }

            $seen[$externalKey] = $lineNumber;

            $rows[] = [
                'row_number' => $lineNumber,
                'state_id' => $state->id,
                'state_code' => $stateCode,
                'name' => $name,
                'slug' => $slug,
                'administrative_type' => $administrativeType,
                'external_id' => $externalId,
                'external_key' => $externalKey,
            ];
        }

        return [$rows, $failures, $inputRows];
    }
}
