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

class CityCsvImporter
{
    public function run(
        string $file,
        string $sourceName,
        ?string $sourceUrl = null,
        bool $apply = false,
        int $expectedRows = 0,
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
            ->select(['id', 'code', 'name'])
            ->get()
            ->keyBy(
                fn ($state) => strtoupper($state->code)
            );

        $lgaMaps = DB::table('local_government_areas')
            ->select([
                'id',
                'state_id',
                'name',
                'slug',
                'administrative_type',
            ])
            ->get()
            ->groupBy('state_id')
            ->map(
                fn ($items) => $items->keyBy('slug')
            );

        [$rows, $failures, $inputRows] = $this->parse(
            $path,
            $states,
            $lgaMaps,
        );

        if (
            $expectedRows > 0 &&
            $inputRows !== $expectedRows
        ) {
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

        /*
         * Dry-run means absolutely no writes:
         * - no cities
         * - no pivot rows
         * - no ImportBatch
         * - no ImportRecord
         * - no ImportFailure
         */
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
                'entity_type' => 'city',
                'expected_rows' => $expectedRows,
                'importer' => 'businessfinder:import-cities',
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
            $counts = DB::transaction(
                function () use ($rows, $batch) {
                    $inserted = 0;
                    $updated = 0;
                    $skipped = 0;

                    foreach ($rows as $row) {
                        $existing = DB::table('cities')
                            ->where(
                                'state_id',
                                $row['state_id']
                            )
                            ->where(
                                'slug',
                                $row['slug']
                            )
                            ->first();

                        if ($existing === null) {
                            $entityId = DB::table('cities')
                                ->insertGetId([
                                    'state_id' =>
                                        $row['state_id'],
                                    'name' =>
                                        $row['name'],
                                    'slug' =>
                                        $row['slug'],
                                    'is_state_capital' =>
                                        $row['is_state_capital'],
                                    'is_major' =>
                                        $row['is_major'],
                                    'is_active' => true,
                                    'latitude' =>
                                        $row['latitude'],
                                    'longitude' =>
                                        $row['longitude'],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            foreach (
                                $row['lga_ids']
                                as $lgaId
                            ) {
                                DB::table(
                                    'city_local_government_area'
                                )->insert([
                                    'city_id' => $entityId,
                                    'local_government_area_id' =>
                                        $lgaId,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }

                            $action = 'inserted';
                            $inserted++;
                        } else {
                            $entityId = $existing->id;
                            $changes = [];

                            if (
                                $existing->name !==
                                $row['name']
                            ) {
                                $changes['name'] =
                                    $row['name'];
                            }

                            if (
                                (bool) $existing->is_state_capital !==
                                $row['is_state_capital']
                            ) {
                                $changes['is_state_capital'] =
                                    $row['is_state_capital'];
                            }

                            if (
                                (bool) $existing->is_major !==
                                $row['is_major']
                            ) {
                                $changes['is_major'] =
                                    $row['is_major'];
                            }

                            if (! (bool) $existing->is_active) {
                                $changes['is_active'] = true;
                            }

                            $existingLat =
                                $existing->latitude === null
                                    ? null
                                    : (string) $existing->latitude;

                            $existingLng =
                                $existing->longitude === null
                                    ? null
                                    : (string) $existing->longitude;

                            if (
                                $existingLat !==
                                $row['latitude']
                            ) {
                                $changes['latitude'] =
                                    $row['latitude'];
                            }

                            if (
                                $existingLng !==
                                $row['longitude']
                            ) {
                                $changes['longitude'] =
                                    $row['longitude'];
                            }

                            $currentLgaIds = DB::table(
                                'city_local_government_area'
                            )
                                ->where(
                                    'city_id',
                                    $entityId
                                )
                                ->pluck(
                                    'local_government_area_id'
                                )
                                ->map(
                                    fn ($id) => (int) $id
                                )
                                ->all();

                            sort($currentLgaIds);

                            $desiredLgaIds =
                                $row['lga_ids'];

                            sort($desiredLgaIds);

                            $pivotChanged =
                                $currentLgaIds !==
                                $desiredLgaIds;

                            if ($changes !== []) {
                                $changes['updated_at'] =
                                    now();

                                DB::table('cities')
                                    ->where(
                                        'id',
                                        $entityId
                                    )
                                    ->update($changes);
                            }

                            if ($pivotChanged) {
                                DB::table(
                                    'city_local_government_area'
                                )
                                    ->where(
                                        'city_id',
                                        $entityId
                                    )
                                    ->delete();

                                foreach (
                                    $desiredLgaIds
                                    as $lgaId
                                ) {
                                    DB::table(
                                        'city_local_government_area'
                                    )->insert([
                                        'city_id' =>
                                            $entityId,
                                        'local_government_area_id' =>
                                            $lgaId,
                                        'created_at' =>
                                            now(),
                                        'updated_at' =>
                                            now(),
                                    ]);
                                }
                            }

                            if (
                                $changes !== [] ||
                                $pivotChanged
                            ) {
                                $action = 'updated';
                                $updated++;
                            } else {
                                $action = 'skipped';
                                $skipped++;
                            }
                        }

                        $fingerprintPayload = [
                            'state_code' =>
                                $row['state_code'],
                            'name' =>
                                $row['name'],
                            'slug' =>
                                $row['slug'],
                            'lga_names' =>
                                $row['lga_names'],
                            'is_state_capital' =>
                                $row['is_state_capital'],
                            'is_major' =>
                                $row['is_major'],
                            'latitude' =>
                                $row['latitude'],
                            'longitude' =>
                                $row['longitude'],
                            'external_id' =>
                                $row['external_id'],
                        ];

                        ImportRecord::create([
                            'import_batch_id' =>
                                $batch->id,
                            'row_number' =>
                                $row['row_number'],
                            'entity_type' => 'city',
                            'entity_id' => $entityId,
                            'external_key' =>
                                $row['external_key'],
                            'action' => $action,
                            'fingerprint' => hash(
                                'sha256',
                                json_encode(
                                    $fingerprintPayload,
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_UNESCAPED_UNICODE
                                )
                            ),
                            'payload' =>
                                $fingerprintPayload,
                        ]);
                    }

                    return compact(
                        'inserted',
                        'updated',
                        'skipped'
                    );
                }
            );

            $batch->update([
                'status' => 'completed',
                'rows_succeeded' =>
                    $counts['inserted'] +
                    $counts['updated'],
                'rows_skipped' =>
                    $counts['skipped'],
                'completed_at' => now(),
            ]);

            return array_merge(
                $summary,
                $counts
            );
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
                    'exception' =>
                        $exception::class,
                ],
            ]);

            throw $exception;
        }
    }

    private function parse(
        string $path,
        $states,
        $lgaMaps,
    ): array {
        $csv = new SplFileObject(
            $path,
            'r'
        );

        $csv->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::DROP_NEW_LINE
        );

        $header = $csv->fgetcsv();

        if (
            $header === false ||
            $header === [null]
        ) {
            return [
                [],
                [[
                    'row_number' => null,
                    'external_key' => null,
                    'severity' => 'error',
                    'code' => 'missing_header',
                    'message' =>
                        'CSV header row is missing.',
                    'payload' => null,
                ]],
                0,
            ];
        }

        $header = array_map(
            function ($value) {
                $value = (string) $value;

                $value = preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    $value
                );

                return strtolower(
                    trim($value)
                );
            },
            $header
        );

        $required = [
            'state_code',
            'city_name',
            'lga_names',
            'is_state_capital',
            'is_major',
            'latitude',
            'longitude',
        ];

        $missing = array_values(
            array_diff(
                $required,
                $header
            )
        );

        if ($missing !== []) {
            return [
                [],
                [[
                    'row_number' => null,
                    'external_key' => null,
                    'severity' => 'error',
                    'code' => 'missing_columns',
                    'message' =>
                        'Missing required columns: '
                        . implode(', ', $missing),
                    'payload' => [
                        'missing' => $missing,
                    ],
                ]],
                0,
            ];
        }

        $positions = array_flip(
            $header
        );

        $rows = [];
        $failures = [];
        $inputRows = 0;
        $lineNumber = 1;

        $seenCities = [];
        $seenExternalIds = [];
        $capitalsByState = [];

        while (! $csv->eof()) {
            $data = $csv->fgetcsv();
            $lineNumber++;

            if (
                $data === false ||
                $data === [null]
            ) {
                continue;
            }

            $isBlank = count(
                array_filter(
                    $data,
                    fn ($value) =>
                        trim((string) $value) !== ''
                )
            ) === 0;

            if ($isBlank) {
                continue;
            }

            $inputRows++;

            if (
                count($data) !==
                count($header)
            ) {
                $failures[] = [
                    'row_number' => $lineNumber,
                    'external_key' => null,
                    'severity' => 'error',
                    'code' =>
                        'column_count_mismatch',
                    'message' => sprintf(
                        'Expected %d columns but found %d.',
                        count($header),
                        count($data),
                    ),
                    'payload' => [
                        'row' => $data,
                    ],
                ];

                continue;
            }

            $value = function (
                string $column
            ) use (
                $data,
                $positions
            ): string {
                if (
                    ! isset(
                        $positions[$column]
                    )
                ) {
                    return '';
                }

                return trim(
                    (string) $data[
                        $positions[$column]
                    ]
                );
            };

            $stateCode = strtoupper(
                $value('state_code')
            );

            $cityName =
                $value('city_name');

            $rawLgaNames =
                $value('lga_names');

            $externalId =
                $value('external_id');

            $rowFailures = [];

            $state =
                $states->get($stateCode);

            if ($stateCode === '') {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        null,
                        'missing_state_code',
                        'State code is required.',
                        [
                            'state_code' =>
                                $stateCode,
                        ],
                    );
            } elseif ($state === null) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $stateCode,
                        'unknown_state',
                        "Unknown state code {$stateCode}.",
                        [
                            'state_code' =>
                                $stateCode,
                        ],
                    );
            }

            if ($cityName === '') {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        null,
                        'missing_city_name',
                        'City name is required.',
                        [
                            'city_name' =>
                                $cityName,
                        ],
                    );
            }

            $slug = Str::slug(
                $cityName
            );

            if (
                $cityName !== '' &&
                $slug === ''
            ) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        null,
                        'invalid_city_slug',
                        'City name does not produce a valid slug.',
                        [
                            'city_name' =>
                                $cityName,
                        ],
                    );
            }

            $externalKey =
                $externalId !== ''
                    ? $externalId
                    : (
                        $stateCode !== '' &&
                        $slug !== ''
                            ? $stateCode
                                . ':'
                                . $slug
                            : null
                    );

            if (
                $stateCode !== '' &&
                $slug !== ''
            ) {
                $cityKey =
                    $stateCode
                    . ':'
                    . $slug;

                if (
                    isset(
                        $seenCities[$cityKey]
                    )
                ) {
                    $rowFailures[] =
                        $this->failure(
                            $lineNumber,
                            $externalKey,
                            'duplicate_city',
                            "Duplicate city row {$cityKey}.",
                            [
                                'first_row' =>
                                    $seenCities[
                                        $cityKey
                                    ],
                            ],
                        );
                } else {
                    $seenCities[
                        $cityKey
                    ] = $lineNumber;
                }
            }

            if ($externalId !== '') {
                if (
                    isset(
                        $seenExternalIds[
                            $externalId
                        ]
                    )
                ) {
                    $rowFailures[] =
                        $this->failure(
                            $lineNumber,
                            $externalKey,
                            'duplicate_external_id',
                            "Duplicate external ID {$externalId}.",
                            [
                                'first_row' =>
                                    $seenExternalIds[
                                        $externalId
                                    ],
                            ],
                        );
                } else {
                    $seenExternalIds[
                        $externalId
                    ] = $lineNumber;
                }
            }

            [
                $capitalValid,
                $isStateCapital,
            ] = $this->parseBoolean(
                $value(
                    'is_state_capital'
                )
            );

            if (! $capitalValid) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $externalKey,
                        'invalid_state_capital',
                        'is_state_capital must be one of: 1, 0, true, false, yes, no.',
                        [
                            'value' =>
                                $value(
                                    'is_state_capital'
                                ),
                        ],
                    );
            }

            [
                $majorValid,
                $isMajor,
            ] = $this->parseBoolean(
                $value('is_major')
            );

            if (! $majorValid) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $externalKey,
                        'invalid_is_major',
                        'is_major must be one of: 1, 0, true, false, yes, no.',
                        [
                            'value' =>
                                $value('is_major'),
                        ],
                    );
            }

            if (
                $capitalValid &&
                $isStateCapital &&
                $stateCode !== '' &&
                $slug !== ''
            ) {
                if (
                    isset(
                        $capitalsByState[
                            $stateCode
                        ]
                    ) &&
                    $capitalsByState[
                        $stateCode
                    ] !== $slug
                ) {
                    $rowFailures[] =
                        $this->failure(
                            $lineNumber,
                            $externalKey,
                            'multiple_state_capitals',
                            "More than one city is marked as the state capital for {$stateCode}.",
                            [
                                'existing_slug' =>
                                    $capitalsByState[
                                        $stateCode
                                    ],
                                'new_slug' =>
                                    $slug,
                            ],
                        );
                } else {
                    $capitalsByState[
                        $stateCode
                    ] = $slug;
                }
            }

            [
                $latValid,
                $latitude,
            ] = $this->parseCoordinate(
                $value('latitude'),
                -90,
                90,
            );

            if (! $latValid) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $externalKey,
                        'invalid_latitude',
                        'Latitude must be blank or a number between -90 and 90.',
                        [
                            'value' =>
                                $value(
                                    'latitude'
                                ),
                        ],
                    );
            }

            [
                $lngValid,
                $longitude,
            ] = $this->parseCoordinate(
                $value('longitude'),
                -180,
                180,
            );

            if (! $lngValid) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $externalKey,
                        'invalid_longitude',
                        'Longitude must be blank or a number between -180 and 180.',
                        [
                            'value' =>
                                $value(
                                    'longitude'
                                ),
                        ],
                    );
            }

            if (
                $latValid &&
                $lngValid &&
                (
                    ($latitude === null) xor
                    ($longitude === null)
                )
            ) {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $externalKey,
                        'incomplete_coordinates',
                        'Latitude and longitude must either both be supplied or both be blank.',
                        [
                            'latitude' =>
                                $latitude,
                            'longitude' =>
                                $longitude,
                        ],
                    );
            }

            $lgaIds = [];
            $lgaNames = [];

            if ($rawLgaNames === '') {
                $rowFailures[] =
                    $this->failure(
                        $lineNumber,
                        $externalKey,
                        'missing_lga_names',
                        'At least one LGA or Area Council is required.',
                        null,
                    );
            } elseif ($state !== null) {
                $stateLgas =
                    $lgaMaps->get(
                        $state->id,
                        collect()
                    );

                $parts = array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                '|',
                                $rawLgaNames
                            )
                        ),
                        fn ($name) =>
                            $name !== ''
                    )
                );

                if ($parts === []) {
                    $rowFailures[] =
                        $this->failure(
                            $lineNumber,
                            $externalKey,
                            'missing_lga_names',
                            'At least one LGA or Area Council is required.',
                            null,
                        );
                }

                foreach ($parts as $lgaName) {
                    $lgaSlug =
                        Str::slug(
                            $lgaName
                        );

                    $lga =
                        $stateLgas->get(
                            $lgaSlug
                        );

                    if ($lga === null) {
                        $rowFailures[] =
                            $this->failure(
                                $lineNumber,
                                $externalKey,
                                'unknown_lga',
                                sprintf(
                                    'Unknown LGA/Area Council "%s" for state %s.',
                                    $lgaName,
                                    $stateCode,
                                ),
                                [
                                    'state_code' =>
                                        $stateCode,
                                    'lga_name' =>
                                        $lgaName,
                                    'lga_slug' =>
                                        $lgaSlug,
                                ],
                            );

                        continue;
                    }

                    $lgaIds[
                        (int) $lga->id
                    ] = (int) $lga->id;

                    $lgaNames[
                        (int) $lga->id
                    ] = $lga->name;
                }
            }

            if ($rowFailures !== []) {
                array_push(
                    $failures,
                    ...$rowFailures
                );

                continue;
            }

            ksort($lgaIds);
            ksort($lgaNames);

            $rows[] = [
                'row_number' =>
                    $lineNumber,
                'state_id' =>
                    (int) $state->id,
                'state_code' =>
                    $stateCode,
                'name' =>
                    $cityName,
                'slug' =>
                    $slug,
                'lga_ids' =>
                    array_values(
                        $lgaIds
                    ),
                'lga_names' =>
                    array_values(
                        $lgaNames
                    ),
                'is_state_capital' =>
                    $isStateCapital,
                'is_major' =>
                    $isMajor,
                'latitude' =>
                    $latitude,
                'longitude' =>
                    $longitude,
                'external_id' =>
                    $externalId !== ''
                        ? $externalId
                        : null,
                'external_key' =>
                    $externalKey,
            ];
        }

        return [
            $rows,
            $failures,
            $inputRows,
        ];
    }

    private function parseBoolean(
        string $value
    ): array {
        $normalized = strtolower(
            trim($value)
        );

        if (
            in_array(
                $normalized,
                ['1', 'true', 'yes'],
                true
            )
        ) {
            return [true, true];
        }

        if (
            in_array(
                $normalized,
                ['0', 'false', 'no'],
                true
            )
        ) {
            return [true, false];
        }

        return [false, false];
    }

    private function parseCoordinate(
        string $value,
        float $min,
        float $max,
    ): array {
        $value = trim($value);

        if ($value === '') {
            return [true, null];
        }

        if (! is_numeric($value)) {
            return [false, null];
        }

        $number = (float) $value;

        if (
            $number < $min ||
            $number > $max
        ) {
            return [false, null];
        }

        return [
            true,
            number_format(
                $number,
                7,
                '.',
                ''
            ),
        ];
    }

    private function failure(
        ?int $rowNumber,
        ?string $externalKey,
        string $code,
        string $message,
        mixed $payload,
    ): array {
        return [
            'row_number' =>
                $rowNumber,
            'external_key' =>
                $externalKey,
            'severity' => 'error',
            'code' => $code,
            'message' => $message,
            'payload' => $payload,
        ];
    }
}
