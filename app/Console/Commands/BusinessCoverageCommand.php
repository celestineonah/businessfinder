<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BusinessCoverageCommand extends Command
{
    protected $signature = 'businessfinder:business-coverage {--csv= : Optional CSV output path}';

    protected $description = 'Report real public business coverage across Nigerian states and LGAs.';

    public function handle(): int
    {
        $publicBusiness = static function () {
            return DB::table('businesses as b')
                ->where('b.listing_status', 'listed')
                ->where('b.is_active', true)
                ->whereNotNull('b.published_at')
                ->whereNull('b.deleted_at');
        };

        $statesTotal = DB::table('states')->where('is_active', true)->count();
        $lgasTotal = DB::table('local_government_areas')->where('is_active', true)->count();
        $businessesTotal = $publicBusiness()->count();

        $statesCovered = DB::table('business_locations as bl')
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->where('bl.is_primary', true)
            ->where('bl.is_active', true)
            ->whereNull('bl.deleted_at')
            ->whereNotNull('bl.state_id')
            ->distinct()
            ->count('bl.state_id');

        $lgasCovered = DB::table('business_locations as bl')
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->where('bl.is_primary', true)
            ->where('bl.is_active', true)
            ->whereNull('bl.deleted_at')
            ->whereNotNull('bl.local_government_area_id')
            ->distinct()
            ->count('bl.local_government_area_id');

        $claimCounts = DB::table('businesses as b')
            ->selectRaw("SUM(CASE WHEN b.claim_status = 'unclaimed' THEN 1 ELSE 0 END) AS unclaimed")
            ->selectRaw("SUM(CASE WHEN b.claim_status = 'pending' THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN b.claim_status = 'claimed' THEN 1 ELSE 0 END) AS claimed")
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->first();

        $verified = DB::table('businesses as b')
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->where('b.verification_status', 'verified')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('business_verifications as bv')
                    ->whereColumn('bv.business_id', 'b.id')
                    ->where('bv.status', 'verified')
                    ->where(function ($query) {
                        $query->whereNull('bv.expires_at')
                            ->orWhere('bv.expires_at', '>', now());
                    });
            })
            ->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['States/FCT covered', "{$statesCovered} / {$statesTotal}"],
                ['LGAs covered', "{$lgasCovered} / {$lgasTotal}"],
                ['Public businesses', $businessesTotal],
                ['Unclaimed', (int) ($claimCounts->unclaimed ?? 0)],
                ['Claims pending', (int) ($claimCounts->pending ?? 0)],
                ['Claimed', (int) ($claimCounts->claimed ?? 0)],
                ['Verified with evidence', $verified],
            ]
        );

        $rows = DB::table('local_government_areas as lga')
            ->join('states as s', 's.id', '=', 'lga.state_id')
            ->leftJoin('business_locations as bl', function ($join) {
                $join->on('bl.local_government_area_id', '=', 'lga.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->leftJoin('businesses as b', function ($join) {
                $join->on('b.id', '=', 'bl.business_id')
                    ->where('b.listing_status', 'listed')
                    ->where('b.is_active', true)
                    ->whereNotNull('b.published_at')
                    ->whereNull('b.deleted_at');
            })
            ->where('lga.is_active', true)
            ->select([
                's.code as state_code',
                's.name as state_name',
                'lga.name as lga_name',
            ])
            ->selectRaw('COUNT(b.id) AS public_businesses')
            ->groupBy('s.code', 's.name', 'lga.id', 'lga.name')
            ->orderBy('s.name')
            ->orderBy('lga.name')
            ->get();

        $zero = $rows->where('public_businesses', 0);
        $this->newLine();
        $this->line('LGAs with zero public businesses: ' . $zero->count());

        if ($zero->isNotEmpty()) {
            $this->table(
                ['State', 'LGA'],
                $zero->take(30)->map(fn ($row) => [$row->state_name, $row->lga_name])->all()
            );
            if ($zero->count() > 30) {
                $this->line('Showing first 30 zero-coverage LGAs. Use --csv for the full report.');
            }
        }

        $csvPath = trim((string) $this->option('csv'));
        if ($csvPath !== '') {
            $directory = dirname($csvPath);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Could not create CSV directory: {$directory}");
            }

            $handle = fopen($csvPath, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Could not open CSV for writing: {$csvPath}");
            }

            fputcsv($handle, ['state_code', 'state_name', 'lga_name', 'public_businesses']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->state_code,
                    $row->state_name,
                    $row->lga_name,
                    (int) $row->public_businesses,
                ]);
            }
            fclose($handle);
            $this->info("Coverage CSV written: {$csvPath}");
        }

        return self::SUCCESS;
    }
}
