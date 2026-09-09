<?php

namespace App\Console\Commands;

use App\Services\Imports\BusinessCsvImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportBusinessesCommand extends Command
{
    protected $signature =
        'businessfinder:import-businesses
        {file : CSV file to import}
        {--source=OpenStreetMap : Source name}
        {--source-url= : Source or licence URL}
        {--min-confidence=0.65 : Minimum confidence 0-1}
        {--apply : Persist the validated import}';

    protected $description =
        'Dry-run or apply an audited BusinessFinder business CSV import.';

    public function handle(
        BusinessCsvImporter $importer
    ): int {
        try {
            $summary =
                $importer->run(
                    file:
                        $this->argument(
                            'file'
                        ),

                    sourceName:
                        (string)
                            $this->option(
                                'source'
                            ),

                    sourceUrl:
                        $this->option(
                            'source-url'
                        )
                        ?: null,

                    apply:
                        (bool)
                            $this->option(
                                'apply'
                            ),

                    minConfidence:
                        (float)
                            $this->option(
                                'min-confidence'
                            ),
                );

            $this->table(
                [
                    'Metric',
                    'Value',
                ],
                [
                    [
                        'Mode',
                        $summary[
                            'mode'
                        ],
                    ],
                    [
                        'Rows total',
                        $summary[
                            'rows_total'
                        ],
                    ],
                    [
                        'Rows valid',
                        $summary[
                            'rows_valid'
                        ],
                    ],
                    [
                        'Inserted',
                        $summary[
                            'inserted'
                        ],
                    ],
                    [
                        'Updated',
                        $summary[
                            'updated'
                        ],
                    ],
                    [
                        'Skipped',
                        $summary[
                            'skipped'
                        ],
                    ],
                    [
                        'Failures',
                        count(
                            $summary[
                                'failures'
                            ]
                        ),
                    ],
                    [
                        'Batch UUID',
                        $summary[
                            'batch_uuid'
                        ]
                        ?? '—',
                    ],
                ]
            );

            if (
                $summary[
                    'failures'
                ] !== []
            ) {
                $this->newLine();

                $this->error(
                    'Import validation failed.'
                );

                foreach (
                    array_slice(
                        $summary[
                            'failures'
                        ],
                        0,
                        20
                    )
                    as $failure
                ) {
                    $this->line(
                        sprintf(
                            'Row %s [%s] %s',
                            $failure[
                                'row_number'
                            ]
                            ?? '—',
                            $failure[
                                'code'
                            ],
                            $failure[
                                'message'
                            ],
                        )
                    );
                }

                return self::FAILURE;
            }

            $this->info(
                $summary[
                    'mode'
                ] === 'apply'
                    ? 'Business import completed.'
                    : 'Dry-run passed. No database changes were made.'
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            $this->error(
                $exception
                    ->getMessage()
            );

            return self::FAILURE;
        }
    }
}
