<?php

use App\Services\Imports\LgaCsvImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'businessfinder:import-lgas
        {file : Path to the LGA CSV file}
        {--source-name= : Human-readable source name}
        {--source-url= : Original source URL}
        {--expected=774 : Expected data-row count}
        {--apply : Write validated rows to the database}',
    function () {
        $sourceName = trim(
            (string) $this->option('source-name')
        );

        if ($sourceName === '') {
            $this->error('--source-name is required.');

            return 1;
        }

        $expected = filter_var(
            $this->option('expected'),
            FILTER_VALIDATE_INT
        );

        if ($expected === false || $expected < 0) {
            $this->error('--expected must be zero or a positive integer.');

            return 1;
        }

        try {
            $result = app(LgaCsvImporter::class)->run(
                file: (string) $this->argument('file'),
                sourceName: $sourceName,
                sourceUrl: $this->option('source-url')
                    ? (string) $this->option('source-url')
                    : null,
                apply: (bool) $this->option('apply'),
                expectedRows: $expected,
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $result['mode']],
                ['Rows total', $result['rows_total']],
                ['Rows valid', $result['rows_valid']],
                ['Failures', count($result['failures'])],
                ['Inserted', $result['inserted']],
                ['Updated', $result['updated']],
                ['Skipped', $result['skipped']],
                ['SHA-256', $result['source_hash']],
                [
                    'Batch UUID',
                    $result['batch_uuid'] ?? '-',
                ],
            ]
        );

        if ($result['failures'] !== []) {
            $this->newLine();
            $this->error('Import validation failed.');

            $this->table(
                ['Row', 'Code', 'Message'],
                collect($result['failures'])
                    ->take(20)
                    ->map(fn ($failure) => [
                        $failure['row_number'] ?? '-',
                        $failure['code'] ?? '-',
                        $failure['message'],
                    ])
                    ->all()
            );

            if (count($result['failures']) > 20) {
                $this->warn(
                    'Only the first 20 failures are displayed.'
                );
            }

            return 1;
        }

        if (! $this->option('apply')) {
            $this->info(
                'Dry run passed. No database rows were changed.'
            );
        } else {
            $this->info('Import completed successfully.');
        }

        return 0;
    }
)->purpose(
    'Validate and import Nigeria LGAs and FCT Area Councils from CSV'
);


Artisan::command(
    'businessfinder:import-cities
        {file : Path to the city CSV file}
        {--source-name= : Human-readable source name}
        {--source-url= : Original source URL}
        {--expected=0 : Expected data-row count; zero disables the count check}
        {--apply : Write validated rows to the database}',
    function () {
        $sourceName = trim(
            (string) $this->option('source-name')
        );

        if ($sourceName === '') {
            $this->error(
                '--source-name is required.'
            );

            return 1;
        }

        $expected = filter_var(
            $this->option('expected'),
            FILTER_VALIDATE_INT
        );

        if (
            $expected === false ||
            $expected < 0
        ) {
            $this->error(
                '--expected must be zero or a positive integer.'
            );

            return 1;
        }

        try {
            $result = app(
                \App\Services\Imports\CityCsvImporter::class
            )->run(
                file:
                    (string) $this->argument('file'),
                sourceName:
                    $sourceName,
                sourceUrl:
                    $this->option('source-url')
                        ? (string) $this->option(
                            'source-url'
                        )
                        : null,
                apply:
                    (bool) $this->option('apply'),
                expectedRows:
                    $expected,
            );
        } catch (\Throwable $exception) {
            $this->error(
                $exception->getMessage()
            );

            return 1;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                [
                    'Mode',
                    $result['mode'],
                ],
                [
                    'Rows total',
                    $result['rows_total'],
                ],
                [
                    'Rows valid',
                    $result['rows_valid'],
                ],
                [
                    'Failures',
                    count(
                        $result['failures']
                    ),
                ],
                [
                    'Inserted',
                    $result['inserted'],
                ],
                [
                    'Updated',
                    $result['updated'],
                ],
                [
                    'Skipped',
                    $result['skipped'],
                ],
                [
                    'SHA-256',
                    $result['source_hash'],
                ],
                [
                    'Batch UUID',
                    $result['batch_uuid']
                        ?? '-',
                ],
            ]
        );

        if (
            $result['failures'] !== []
        ) {
            $this->newLine();

            $this->error(
                'Import validation failed.'
            );

            $this->table(
                [
                    'Row',
                    'Code',
                    'Message',
                ],
                collect(
                    $result['failures']
                )
                    ->take(20)
                    ->map(
                        fn ($failure) => [
                            $failure[
                                'row_number'
                            ] ?? '-',
                            $failure[
                                'code'
                            ] ?? '-',
                            $failure[
                                'message'
                            ],
                        ]
                    )
                    ->all()
            );

            if (
                count(
                    $result['failures']
                ) > 20
            ) {
                $this->warn(
                    'Only the first 20 failures are displayed.'
                );
            }

            return 1;
        }

        if (
            ! $this->option('apply')
        ) {
            $this->info(
                'Dry run passed. No database rows were changed.'
            );
        } else {
            $this->info(
                'Import completed successfully.'
            );
        }

        return 0;
    }
)->purpose(
    'Validate and import Nigerian cities and their LGA relationships from CSV'
);
