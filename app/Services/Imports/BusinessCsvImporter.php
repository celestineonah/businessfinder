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

class BusinessCsvImporter
{
    public function run(
        string $file,
        string $sourceName,
        ?string $sourceUrl = null,
        bool $apply = false,
        float $minConfidence = 0.65,
    ): array {
        $path = realpath($file);

        if (
            $path === false
            || ! is_file($path)
            || ! is_readable($path)
        ) {
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

        if (
            $minConfidence < 0
            || $minConfidence > 1
        ) {
            throw new InvalidArgumentException(
                'Minimum confidence must be between 0 and 1.'
            );
        }

        $sourceHash = hash_file(
            'sha256',
            $path
        );

        [$rows, $failures, $inputRows] =
            $this->parse(
                $path,
                $minConfidence,
            );

        $summary = [
            'mode' =>
                $apply
                    ? 'apply'
                    : 'dry-run',

            'file' =>
                $path,

            'source_hash' =>
                $sourceHash,

            'rows_total' =>
                $inputRows,

            'rows_valid' =>
                count($rows),

            'failures' =>
                $failures,

            'inserted' =>
                0,

            'updated' =>
                0,

            'skipped' =>
                0,

            'batch_uuid' =>
                null,
        ];

        /*
         * Dry-run performs no writes.
         */
        if (! $apply) {
            return $summary;
        }

        $batch = ImportBatch::create([
            'uuid' =>
                Str::uuid()->toString(),

            'import_type' =>
                'businesses',

            'source_name' =>
                $sourceName,

            'source_url' =>
                $sourceUrl ?: null,

            'original_filename' =>
                basename($path),

            'source_hash' =>
                $sourceHash,

            'status' =>
                'running',

            'rows_total' =>
                $inputRows,

            'metadata' => [
                'entity_type' =>
                    'business',

                'min_confidence' =>
                    $minConfidence,

                'importer' =>
                    'businessfinder:import-businesses',
            ],

            'started_at' =>
                now(),
        ]);

        $summary['batch_uuid'] =
            $batch->uuid;

        /*
         * Conservative rule:
         * any malformed row blocks the whole apply.
         */
        if ($failures !== []) {
            foreach (
                $failures
                as $failure
            ) {
                ImportFailure::create([
                    'import_batch_id' =>
                        $batch->id,

                    ...$failure,
                ]);
            }

            $batch->update([
                'status' =>
                    'failed',

                'rows_failed' =>
                    max(
                        1,
                        collect($failures)
                            ->pluck('row_number')
                            ->filter()
                            ->unique()
                            ->count()
                    ),

                'completed_at' =>
                    now(),
            ]);

            return $summary;
        }

        try {
            $counts =
                DB::transaction(
                    function () use (
                        $rows,
                        $batch,
                        $sourceName,
                        $sourceUrl
                    ) {
                        $inserted = 0;
                        $updated = 0;
                        $skipped = 0;

                        foreach (
                            $rows
                            as $row
                        ) {
                            /*
                             * Exact external-source identity
                             * is our strongest idempotency key.
                             */
                            $existingSource =
                                DB::table(
                                    'business_sources'
                                )
                                    ->where(
                                        'source_name',
                                        $sourceName
                                    )
                                    ->where(
                                        'external_id',
                                        $row['external_id']
                                    )
                                    ->where(
                                        'is_active',
                                        true
                                    )
                                    ->first();

                            if (
                                $existingSource
                                !== null
                            ) {
                                DB::table(
                                    'business_sources'
                                )
                                    ->where(
                                        'id',
                                        $existingSource->id
                                    )
                                    ->update([
                                        'source_url' =>
                                            $row[
                                                'source_url'
                                            ]
                                            ?: $sourceUrl,

                                        'confidence_score' =>
                                            $row[
                                                'confidence_score'
                                            ],

                                        'last_checked_at' =>
                                            now(),

                                        'updated_at' =>
                                            now(),
                                    ]);

                                DB::table(
                                    'businesses'
                                )
                                    ->where(
                                        'id',
                                        $existingSource
                                            ->business_id
                                    )
                                    ->update([
                                        'last_checked_at' =>
                                            now(),

                                        'updated_at' =>
                                            now(),
                                    ]);

                                $this->record(
                                    $batch->id,
                                    $row,
                                    $existingSource
                                        ->business_id,
                                    'skipped'
                                );

                                $skipped++;

                                continue;
                            }

                            /*
                             * Cross-source dedupe.
                             */
                            $existingBusiness =
                                DB::table(
                                    'businesses'
                                )
                                    ->where(
                                        'dedupe_key',
                                        $row[
                                            'dedupe_key'
                                        ]
                                    )
                                    ->whereNull(
                                        'deleted_at'
                                    )
                                    ->first();

                            if (
                                $existingBusiness
                                !== null
                            ) {
                                $locationId =
                                    DB::table(
                                        'business_locations'
                                    )
                                        ->where(
                                            'business_id',
                                            $existingBusiness
                                                ->id
                                        )
                                        ->where(
                                            'is_primary',
                                            true
                                        )
                                        ->where(
                                            'is_active',
                                            true
                                        )
                                        ->whereNull(
                                            'deleted_at'
                                        )
                                        ->value(
                                            'id'
                                        );

                                $this->createSource(
                                    businessId:
                                        $existingBusiness
                                            ->id,

                                    businessLocationId:
                                        $locationId,

                                    row:
                                        $row,

                                    sourceName:
                                        $sourceName,

                                    sourceUrl:
                                        $sourceUrl,

                                    batchUuid:
                                        $batch->uuid,
                                );

                                DB::table(
                                    'businesses'
                                )
                                    ->where(
                                        'id',
                                        $existingBusiness
                                            ->id
                                    )
                                    ->update([
                                        'last_checked_at' =>
                                            now(),

                                        'updated_at' =>
                                            now(),
                                    ]);

                                $this->record(
                                    $batch->id,
                                    $row,
                                    $existingBusiness
                                        ->id,
                                    'updated'
                                );

                                $updated++;

                                continue;
                            }

                            /*
                             * Brand-new imported listing.
                             */
                            $slug =
                                $this
                                    ->uniqueSlug(
                                        $row[
                                            'business_name'
                                        ],
                                        $row[
                                            'lga_name'
                                        ]
                                    );

                            $businessId =
                                DB::table(
                                    'businesses'
                                )
                                    ->insertGetId([
                                        'owner_user_id' =>
                                            null,

                                        'name' =>
                                            $row[
                                                'business_name'
                                            ],

                                        'normalized_name' =>
                                            $row[
                                                'normalized_name'
                                            ],

                                        'slug' =>
                                            $slug,

                                        'legal_name' =>
                                            $row[
                                                'legal_name'
                                            ],

                                        'cac_number' =>
                                            $row[
                                                'cac_number'
                                            ],

                                        'primary_phone' =>
                                            $row[
                                                'primary_phone'
                                            ],

                                        'whatsapp_phone' =>
                                            $row[
                                                'whatsapp_phone'
                                            ],

                                        'primary_email' =>
                                            $row[
                                                'primary_email'
                                            ],

                                        'website_url' =>
                                            $row[
                                                'website_url'
                                            ],

                                        'short_description' =>
                                            null,

                                        'description' =>
                                            null,

                                        'listing_status' =>
                                            'listed',

                                        'claim_status' =>
                                            'unclaimed',

                                        'verification_status' =>
                                            'unverified',

                                        'dedupe_key' =>
                                            $row[
                                                'dedupe_key'
                                            ],

                                        'claimed_at' =>
                                            null,

                                        'verified_at' =>
                                            null,

                                        /*
                                         * Imported listing is
                                         * publicly discoverable,
                                         * but NOT verified.
                                         */
                                        'published_at' =>
                                            now(),

                                        'last_checked_at' =>
                                            now(),

                                        'is_active' =>
                                            true,

                                        'created_at' =>
                                            now(),

                                        'updated_at' =>
                                            now(),
                                    ]);

                            $locationId =
                                DB::table(
                                    'business_locations'
                                )
                                    ->insertGetId([
                                        'business_id' =>
                                            $businessId,

                                        'state_id' =>
                                            $row[
                                                'state_id'
                                            ],

                                        'city_id' =>
                                            null,

                                        'local_government_area_id' =>
                                            $row[
                                                'lga_id'
                                            ],

                                        'area_id' =>
                                            null,

                                        'name' =>
                                            $row[
                                                'lga_name'
                                            ],

                                        'slug' =>
                                            'primary',

                                        'address_line_1' =>
                                            $row[
                                                'address_line_1'
                                            ],

                                        'address_line_2' =>
                                            $row[
                                                'address_line_2'
                                            ],

                                        'landmark' =>
                                            $row[
                                                'landmark'
                                            ],

                                        'postal_code' =>
                                            $row[
                                                'postal_code'
                                            ],

                                        'phone' =>
                                            $row[
                                                'primary_phone'
                                            ],

                                        'whatsapp_phone' =>
                                            $row[
                                                'whatsapp_phone'
                                            ],

                                        'email' =>
                                            $row[
                                                'primary_email'
                                            ],

                                        'latitude' =>
                                            $row[
                                                'latitude'
                                            ],

                                        'longitude' =>
                                            $row[
                                                'longitude'
                                            ],

                                        'opening_hours' =>
                                            null,

                                        'is_primary' =>
                                            true,

                                        'service_area_only' =>
                                            false,

                                        'is_active' =>
                                            true,

                                        'last_checked_at' =>
                                            now(),

                                        'created_at' =>
                                            now(),

                                        'updated_at' =>
                                            now(),
                                    ]);

                            $this->createSource(
                                businessId:
                                    $businessId,

                                businessLocationId:
                                    $locationId,

                                row:
                                    $row,

                                sourceName:
                                    $sourceName,

                                sourceUrl:
                                    $sourceUrl,

                                batchUuid:
                                    $batch->uuid,
                            );

                            $this->record(
                                $batch->id,
                                $row,
                                $businessId,
                                'inserted'
                            );

                            $inserted++;
                        }

                        return compact(
                            'inserted',
                            'updated',
                            'skipped'
                        );
                    }
                );

            $batch->update([
                'status' =>
                    'completed',

                'rows_succeeded' =>
                    $counts[
                        'inserted'
                    ]
                    + $counts[
                        'updated'
                    ],

                'rows_skipped' =>
                    $counts[
                        'skipped'
                    ],

                'completed_at' =>
                    now(),
            ]);

            return array_merge(
                $summary,
                $counts
            );

        } catch (Throwable $exception) {
            $batch->update([
                'status' =>
                    'failed',

                'completed_at' =>
                    now(),
            ]);

            ImportFailure::create([
                'import_batch_id' =>
                    $batch->id,

                'severity' =>
                    'error',

                'code' =>
                    'import_exception',

                'message' =>
                    $exception
                        ->getMessage(),

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
        float $minConfidence,
    ): array {
        $csv =
            new SplFileObject(
                $path,
                'r'
            );

        $csv->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::DROP_NEW_LINE
        );

        $header =
            $csv->fgetcsv();

        if (
            $header === false
            || $header === [null]
        ) {
            return [
                [],
                [[
                    'row_number' =>
                        null,

                    'external_key' =>
                        null,

                    'severity' =>
                        'error',

                    'code' =>
                        'missing_header',

                    'message' =>
                        'CSV header row is missing.',

                    'payload' =>
                        null,
                ]],
                0,
            ];
        }

        $header =
            array_map(
                static function (
                    $value
                ) {
                    $value =
                        (string) $value;

                    $value =
                        preg_replace(
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
            'external_id',
            'business_name',
            'state_code',
            'lga_name',
            'confidence_score',
        ];

        $missing =
            array_values(
                array_diff(
                    $required,
                    $header
                )
            );

        if ($missing !== []) {
            return [
                [],
                [[
                    'row_number' =>
                        1,

                    'external_key' =>
                        null,

                    'severity' =>
                        'error',

                    'code' =>
                        'missing_columns',

                    'message' =>
                        'Missing required CSV columns: '
                        . implode(
                            ', ',
                            $missing
                        ),

                    'payload' => [
                        'header' =>
                            $header,
                    ],
                ]],
                0,
            ];
        }

        $indexes =
            array_flip(
                $header
            );

        $rows = [];
        $failures = [];
        $seenExternalIds = [];
        $inputRows = 0;
        $lineNumber = 1;

        while (! $csv->eof()) {
            $record =
                $csv->fgetcsv();

            $lineNumber++;

            if (
                $record === false
                || $record === [null]
            ) {
                continue;
            }

            $hasContent =
                collect($record)
                    ->contains(
                        fn ($value) =>
                            trim(
                                (string) $value
                            ) !== ''
                    );

            if (! $hasContent) {
                continue;
            }

            $inputRows++;

            $value =
                static function (
                    string $column
                ) use (
                    $record,
                    $indexes
                ): string {
                    if (
                        ! array_key_exists(
                            $column,
                            $indexes
                        )
                    ) {
                        return '';
                    }

                    return trim(
                        (string) (
                            $record[
                                $indexes[
                                    $column
                                ]
                            ]
                            ?? ''
                        )
                    );
                };

            $externalId =
                $value(
                    'external_id'
                );

            $name =
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $value(
                        'business_name'
                    )
                );

            $stateCode =
                strtoupper(
                    $value(
                        'state_code'
                    )
                );

            $lgaName =
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $value(
                        'lga_name'
                    )
                );

            $confidenceRaw =
                $value(
                    'confidence_score'
                );

            $payload = [
                'external_id' =>
                    $externalId,

                'business_name' =>
                    $name,

                'state_code' =>
                    $stateCode,

                'lga_name' =>
                    $lgaName,

                'confidence_score' =>
                    $confidenceRaw,
            ];

            if (
                $externalId === ''
                || $name === ''
                || $stateCode === ''
                || $lgaName === ''
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'missing_required_value',
                        'A required business field is empty.',
                        $payload
                    );

                continue;
            }

            if (
                isset(
                    $seenExternalIds[
                        $externalId
                    ]
                )
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'duplicate_external_id',
                        'The external ID occurs more than once in this file.',
                        $payload
                    );

                continue;
            }

            $seenExternalIds[
                $externalId
            ] = true;

            if (
                mb_strlen($name) > 180
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'business_name_too_long',
                        'Business name exceeds 180 characters.',
                        $payload
                    );

                continue;
            }

            if (
                ! is_numeric(
                    $confidenceRaw
                )
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'invalid_confidence',
                        'Confidence score must be numeric.',
                        $payload
                    );

                continue;
            }

            $confidence =
                (float)
                    $confidenceRaw;

            if (
                $confidence < 0
                || $confidence > 1
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'invalid_confidence_range',
                        'Confidence score must be between 0 and 1.',
                        $payload
                    );

                continue;
            }

            if (
                $confidence
                < $minConfidence
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'below_confidence_threshold',
                        sprintf(
                            'Confidence %.4f is below minimum %.4f.',
                            $confidence,
                            $minConfidence
                        ),
                        $payload
                    );

                continue;
            }

            $state =
                DB::table(
                    'states'
                )
                    ->where(
                        'code',
                        $stateCode
                    )
                    ->first();

            if ($state === null) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'unknown_state',
                        "Unknown state code: {$stateCode}.",
                        $payload
                    );

                continue;
            }

            $lga =
                DB::table(
                    'local_government_areas'
                )
                    ->where(
                        'state_id',
                        $state->id
                    )
                    ->where(
                        'slug',
                        Str::slug(
                            $lgaName
                        )
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->first();

            if ($lga === null) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'unknown_lga',
                        "Unknown LGA '{$lgaName}' for {$stateCode}.",
                        $payload
                    );

                continue;
            }

            $latitude =
                $value(
                    'latitude'
                );

            $longitude =
                $value(
                    'longitude'
                );

            if (
                ($latitude === '')
                !== ($longitude === '')
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'incomplete_coordinates',
                        'Latitude and longitude must be supplied together.',
                        $payload
                    );

                continue;
            }

            $lat =
                $latitude === ''
                    ? null
                    : (
                        is_numeric(
                            $latitude
                        )
                            ? (float)
                                $latitude
                            : null
                    );

            $lng =
                $longitude === ''
                    ? null
                    : (
                        is_numeric(
                            $longitude
                        )
                            ? (float)
                                $longitude
                            : null
                    );

            if (
                $latitude !== ''
                && (
                    $lat === null
                    || $lng === null
                    || $lat < -90
                    || $lat > 90
                    || $lng < -180
                    || $lng > 180
                )
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'invalid_coordinates',
                        'Coordinates are invalid.',
                        $payload
                    );

                continue;
            }

            $addressLine1 =
                $value(
                    'address_line_1'
                );

            if (
                $lat === null
                && $addressLine1 === ''
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'missing_location_evidence',
                        'A business requires coordinates or a street/address value.',
                        $payload
                    );

                continue;
            }

            $email =
                $value(
                    'primary_email'
                );

            if (
                $email !== ''
                && filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                $failures[] =
                    $this->failure(
                        $lineNumber,
                        $externalId,
                        'invalid_email',
                        'Primary email address is invalid.',
                        $payload
                    );

                continue;
            }

            $normalizedName =
                mb_strtolower(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        trim($name)
                    )
                );

            $locationIdentity =
                $lat !== null
                    ? sprintf(
                        '%.5f|%.5f',
                        $lat,
                        $lng
                    )
                    : mb_strtolower(
                        preg_replace(
                            '/\s+/u',
                            ' ',
                            $addressLine1
                        )
                    );

            $dedupeKey =
                'import-v1:'
                . hash(
                    'sha256',
                    implode(
                        '|',
                        [
                            $normalizedName,
                            $state->id,
                            $lga->id,
                            $locationIdentity,
                        ]
                    )
                );

            $rows[] = [
                'row_number' =>
                    $lineNumber,

                'external_id' =>
                    $externalId,

                'external_key' =>
                    $externalId,

                'business_name' =>
                    $name,

                'normalized_name' =>
                    $normalizedName,

                'legal_name' =>
                    $value(
                        'legal_name'
                    )
                    ?: null,

                'cac_number' =>
                    $value(
                        'cac_number'
                    )
                    ?: null,

                'primary_phone' =>
                    $value(
                        'primary_phone'
                    )
                    ?: null,

                'whatsapp_phone' =>
                    $value(
                        'whatsapp_phone'
                    )
                    ?: null,

                'primary_email' =>
                    $email ?: null,

                'website_url' =>
                    $value(
                        'website_url'
                    )
                    ?: null,

                'address_line_1' =>
                    $addressLine1
                    ?: null,

                'address_line_2' =>
                    $value(
                        'address_line_2'
                    )
                    ?: null,

                'landmark' =>
                    $value(
                        'landmark'
                    )
                    ?: null,

                'postal_code' =>
                    $value(
                        'postal_code'
                    )
                    ?: null,

                'latitude' =>
                    $lat,

                'longitude' =>
                    $lng,

                'state_id' =>
                    $state->id,

                'state_code' =>
                    $stateCode,

                'lga_id' =>
                    $lga->id,

                'lga_name' =>
                    $lga->name,

                'category_hint' =>
                    $value(
                        'category_hint'
                    )
                    ?: null,

                'source_url' =>
                    $value(
                        'source_url'
                    )
                    ?: null,

                'confidence_score' =>
                    $confidence,

                'dedupe_key' =>
                    $dedupeKey,
            ];
        }

        return [
            $rows,
            $failures,
            $inputRows,
        ];
    }

    private function createSource(
        int $businessId,
        ?int $businessLocationId,
        array $row,
        string $sourceName,
        ?string $sourceUrl,
        string $batchUuid,
    ): void {
        DB::table(
            'business_sources'
        )->insert([
            'business_id' =>
                $businessId,

            'business_location_id' =>
                $businessLocationId,

            'source_type' =>
                'import',

            'source_name' =>
                $sourceName,

            'source_url' =>
                $row[
                    'source_url'
                ]
                ?: $sourceUrl,

            'external_id' =>
                $row[
                    'external_id'
                ],

            'confidence_score' =>
                $row[
                    'confidence_score'
                ],

            'metadata' =>
                json_encode(
                    [
                        'category_hint' =>
                            $row[
                                'category_hint'
                            ],

                        'import_batch_uuid' =>
                            $batchUuid,

                        'state_code' =>
                            $row[
                                'state_code'
                            ],

                        'lga_name' =>
                            $row[
                                'lga_name'
                            ],
                    ],
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                ),

            'first_seen_at' =>
                now(),

            'last_checked_at' =>
                now(),

            'is_active' =>
                true,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    private function record(
        int $batchId,
        array $row,
        int $businessId,
        string $action,
    ): void {
        $fingerprintPayload = [
            'external_id' =>
                $row[
                    'external_id'
                ],

            'business_name' =>
                $row[
                    'business_name'
                ],

            'state_code' =>
                $row[
                    'state_code'
                ],

            'lga_name' =>
                $row[
                    'lga_name'
                ],

            'latitude' =>
                $row[
                    'latitude'
                ],

            'longitude' =>
                $row[
                    'longitude'
                ],

            'confidence_score' =>
                $row[
                    'confidence_score'
                ],
        ];

        ImportRecord::create([
            'import_batch_id' =>
                $batchId,

            'row_number' =>
                $row[
                    'row_number'
                ],

            'entity_type' =>
                'business',

            'entity_id' =>
                $businessId,

            'external_key' =>
                $row[
                    'external_key'
                ],

            'action' =>
                $action,

            'fingerprint' =>
                hash(
                    'sha256',
                    json_encode(
                        $fingerprintPayload,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                ),

            'payload' =>
                $fingerprintPayload,
        ]);
    }

    private function uniqueSlug(
        string $businessName,
        string $lgaName,
    ): string {
        $base =
            Str::slug(
                $businessName
                . '-'
                . $lgaName
            );

        if ($base === '') {
            $base =
                'business';
        }

        $slug =
            $base;

        $counter = 2;

        while (
            DB::table(
                'businesses'
            )
                ->where(
                    'slug',
                    $slug
                )
                ->exists()
        ) {
            $slug =
                $base
                . '-'
                . $counter;

            $counter++;
        }

        return $slug;
    }

    private function failure(
        ?int $rowNumber,
        ?string $externalKey,
        string $code,
        string $message,
        ?array $payload,
    ): array {
        return [
            'row_number' =>
                $rowNumber,

            'external_key' =>
                $externalKey,

            'severity' =>
                'error',

            'code' =>
                $code,

            'message' =>
                $message,

            'payload' =>
                $payload,
        ];
    }
}
