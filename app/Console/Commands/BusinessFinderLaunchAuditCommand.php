<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BusinessFinderLaunchAuditCommand extends Command
{
    protected $signature =
        'businessfinder:launch-audit {--json=storage/app/reports/launch-readiness.json}';

    protected $description =
        'Audit BusinessFinder production launch readiness without fabricating or mutating business data.';

    public function handle(): int
    {
        $checks = [];

        $add = function (
            string $name,
            bool $passed,
            string $severity,
            mixed $value,
            string $expected
        ) use (&$checks): void {
            $checks[] = [
                'name' => $name,
                'status' => $passed
                    ? 'PASS'
                    : ($severity === 'critical' ? 'FAIL' : 'WARN'),
                'severity' => $severity,
                'value' => $value,
                'expected' => $expected,
            ];
        };

        $add(
            'Application environment',
            app()->isProduction(),
            'critical',
            app()->environment(),
            'production'
        );

        $add(
            'Debug mode',
            config('app.debug') === false,
            'critical',
            config('app.debug') ? 'true' : 'false',
            'false'
        );

        $appUrl = (string) config('app.url');
        $add(
            'HTTPS application URL',
            str_starts_with(strtolower($appUrl), 'https://'),
            'critical',
            $appUrl,
            'https://...'
        );

        $appKey = (string) config('app.key');
        $add(
            'Application key',
            trim($appKey) !== '',
            'critical',
            trim($appKey) !== '' ? 'present' : 'missing',
            'present'
        );

        $add(
            'Production database',
            config('database.default') !== 'sqlite',
            'critical',
            (string) config('database.default'),
            'non-sqlite production connection'
        );

        $add(
            'SMTP mailer',
            config('mail.default') === 'smtp',
            'critical',
            (string) config('mail.default'),
            'smtp'
        );

        $fromAddress = strtolower(
            trim((string) config('mail.from.address'))
        );

        $add(
            'Official mail sender',
            $fromAddress === 'support@businessfinder.com.ng',
            'critical',
            $fromAddress,
            'support@businessfinder.com.ng'
        );

        $requiredTables = [
            'businesses',
            'business_locations',
            'business_sources',
            'business_claims',
            'business_verifications',
            'business_reviews',
            'business_enquiries',
            'business_publication_requests',
            'states',
            'local_government_areas',
            'categories',
        ];

        foreach ($requiredTables as $table) {
            $add(
                "Database table: {$table}",
                Schema::hasTable($table),
                'critical',
                Schema::hasTable($table) ? 'present' : 'missing',
                'present'
            );
        }

        $adminCount = DB::table('users')
            ->where('is_admin', true)
            ->count();

        $verifiedAdminCount = DB::table('users')
            ->where('is_admin', true)
            ->whereNotNull('email_verified_at')
            ->count();

        $officialAdminCount = DB::table('users')
            ->where('is_admin', true)
            ->whereRaw('LOWER(email) = ?', [
                'support@businessfinder.com.ng',
            ])
            ->whereNotNull('email_verified_at')
            ->count();

        $add(
            'Administrator exists',
            $adminCount >= 1,
            'critical',
            $adminCount,
            'at least 1'
        );

        $add(
            'Administrator email verified',
            $verifiedAdminCount >= 1,
            'critical',
            $verifiedAdminCount,
            'at least 1'
        );

        $add(
            'Official administrator identity',
            $officialAdminCount >= 1,
            'critical',
            $officialAdminCount,
            'support@businessfinder.com.ng verified admin'
        );

        $stateCount = DB::table('states')
            ->where('is_active', true)
            ->count();

        $lgaCount = DB::table('local_government_areas')
            ->where('is_active', true)
            ->count();

        $add(
            'Nigeria state/FCT geography',
            $stateCount === 37,
            'critical',
            $stateCount,
            '37'
        );

        $add(
            'Nigeria LGA/Area Council geography',
            $lgaCount === 774,
            'critical',
            $lgaCount,
            '774'
        );

        $publicBusinessQuery = DB::table('businesses as b')
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at');

        $publicBusinesses = (clone $publicBusinessQuery)->count();

        $add(
            'Published business inventory',
            $publicBusinesses > 0,
            'critical',
            $publicBusinesses,
            'greater than 0'
        );

        $invalidVerified = DB::table('businesses as b')
            ->where('b.verification_status', 'verified')
            ->whereNull('b.deleted_at')
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('business_verifications as bv')
                    ->whereColumn('bv.business_id', 'b.id')
                    ->where('bv.status', 'verified')
                    ->where(function ($expiry) {
                        $expiry
                            ->whereNull('bv.expires_at')
                            ->orWhere('bv.expires_at', '>', now());
                    });
            })
            ->count();

        $add(
            'Verified badge evidence integrity',
            $invalidVerified === 0,
            'critical',
            $invalidVerified,
            '0 verified businesses without valid evidence'
        );

        $selfApprovedReviews = DB::table('business_reviews as br')
            ->join('businesses as b', 'b.id', '=', 'br.business_id')
            ->where('br.status', 'approved')
            ->whereNotNull('b.owner_user_id')
            ->whereColumn('br.user_id', 'b.owner_user_id')
            ->count();

        $add(
            'Owner self-review integrity',
            $selfApprovedReviews === 0,
            'critical',
            $selfApprovedReviews,
            '0 approved owner self-reviews'
        );

        $duplicatePendingPublication = DB::query()
            ->fromSub(
                DB::table('business_publication_requests')
                    ->select('business_id')
                    ->where('status', 'pending')
                    ->groupBy('business_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_pending'
            )
            ->count();

        $add(
            'Publication queue concurrency',
            $duplicatePendingPublication === 0,
            'critical',
            $duplicatePendingPublication,
            '0 businesses with duplicate pending requests'
        );

        $missingPrimaryLocation = (clone $publicBusinessQuery)
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('business_locations as bl')
                    ->whereColumn('bl.business_id', 'b.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->count();

        $add(
            'Published listing primary-location coverage',
            $missingPrimaryLocation === 0,
            'warning',
            $missingPrimaryLocation,
            '0'
        );

        $missingActiveSource = (clone $publicBusinessQuery)
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('business_sources as bs')
                    ->whereColumn('bs.business_id', 'b.id')
                    ->where('bs.is_active', true);
            })
            ->count();

        $add(
            'Published listing provenance coverage',
            $missingActiveSource === 0,
            'warning',
            $missingActiveSource,
            '0'
        );

        foreach ([
            'home',
            'search',
            'business.show',
            'directory.locations',
            'directory.sitemap',
            'dashboard.businesses.index',
            'admin.listings.index',
            'admin.engagement.index',
        ] as $routeName) {
            $add(
                "Route registered: {$routeName}",
                Route::has($routeName),
                'critical',
                Route::has($routeName) ? 'registered' : 'missing',
                'registered'
            );
        }

        $indexableStates = DB::table('states as s')
            ->join('business_locations as bl', function ($join) {
                $join->on('bl.state_id', '=', 's.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->where('s.is_active', true)
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->selectRaw(
                's.id, COUNT(DISTINCT b.id) as business_count'
            )
            ->groupBy('s.id')
            ->havingRaw('COUNT(DISTINCT b.id) >= 10')
            ->get()
            ->count();

        $checks[] = [
            'name' => 'Indexable state pages',
            'status' => 'INFO',
            'severity' => 'information',
            'value' => $indexableStates,
            'expected' => 'inventory-driven; threshold is 10',
        ];

        $failedCritical = collect($checks)
            ->where('status', 'FAIL')
            ->count();

        $warnings = collect($checks)
            ->where('status', 'WARN')
            ->count();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'application' => 'BusinessFinder Nigeria',
            'status' => $failedCritical === 0 ? 'PASS' : 'FAIL',
            'critical_failures' => $failedCritical,
            'warnings' => $warnings,
            'metrics' => [
                'public_businesses' => $publicBusinesses,
                'states_fct' => $stateCount,
                'lgas_area_councils' => $lgaCount,
                'indexable_state_pages' => $indexableStates,
            ],
            'checks' => $checks,
        ];

        $this->newLine();
        $this->info('BusinessFinder Nigeria — Production Launch Audit');
        $this->table(
            ['Check', 'Status', 'Value', 'Expected'],
            collect($checks)
                ->map(fn ($check) => [
                    $check['name'],
                    $check['status'],
                    is_scalar($check['value'])
                        ? (string) $check['value']
                        : json_encode($check['value']),
                    $check['expected'],
                ])
                ->all()
        );

        $jsonPath = base_path(
            ltrim((string) $this->option('json'), '/')
        );

        File::ensureDirectoryExists(dirname($jsonPath));

        File::put(
            $jsonPath,
            json_encode(
                $report,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ) . PHP_EOL
        );

        $this->line("Report: {$jsonPath}");
        $this->line(
            "Critical failures: {$failedCritical}; warnings: {$warnings}"
        );

        if ($failedCritical > 0) {
            $this->error('LAUNCH AUDIT FAIL');
            return self::FAILURE;
        }

        $this->info('LAUNCH AUDIT PASS');

        return self::SUCCESS;
    }
}
