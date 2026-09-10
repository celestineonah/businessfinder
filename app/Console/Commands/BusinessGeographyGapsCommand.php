<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BusinessGeographyGapsCommand extends Command
{
    protected $signature = 'businessfinder:geography-gaps {--csv=storage/app/reports/business-geography-gaps.csv : Output CSV path}';
    protected $description = 'Report zero-public-business LGAs and the current OSM resolution gaps without fabricating mappings.';

    public function handle(): int
    {
        $publicByLga = DB::table('businesses as b')
            ->join('business_locations as bl', function ($join) {
                $join->on('bl.business_id', '=', 'b.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->whereNotNull('bl.local_government_area_id')
            ->selectRaw('bl.local_government_area_id, COUNT(DISTINCT b.id) as public_count')
            ->groupBy('bl.local_government_area_id');

        $zeroCoverage = DB::table('local_government_areas as lga')
            ->join('states as s', 's.id', '=', 'lga.state_id')
            ->leftJoinSub($publicByLga, 'pc', 'pc.local_government_area_id', '=', 'lga.id')
            ->where('lga.is_active', true)
            ->where('s.is_active', true)
            ->whereRaw('COALESCE(pc.public_count, 0) = 0')
            ->orderBy('s.name')->orderBy('lga.name')
            ->get(['s.code as state_code', 's.name as state_name', 'lga.name as lga_name']);

        $approvedAliases = DB::table('geography_lga_aliases')->where('status', 'approved')->count();
        $pendingAliases = DB::table('geography_lga_aliases')->where('status', 'pending')->count();

        $rows = [];
        foreach ($zeroCoverage as $item) {
            $rows[] = [$item->state_code, $item->state_name, $item->lga_name, 'zero_public_businesses', '', ''];
        }

        $resolutionPath = storage_path('app/imports/businesses/osm/lga-resolution-report.csv');
        $currentResolutionGaps = 0;
        if (is_file($resolutionPath) && ($handle = fopen($resolutionPath, 'r')) !== false) {
            $header = fgetcsv($handle);
            $map = $header ? array_flip($header) : [];
            while (($data = fgetcsv($handle)) !== false) {
                $status = $data[$map['status'] ?? -1] ?? '';
                if (! in_array($status, ['unresolved', 'fetch_error', 'state_boundary_error'], true)) {
                    continue;
                }
                $currentResolutionGaps++;
                $rows[] = [
                    $data[$map['state_code'] ?? -1] ?? '',
                    $data[$map['state_name'] ?? -1] ?? '',
                    $data[$map['lga_name'] ?? -1] ?? '',
                    $status,
                    $data[$map['osm_name'] ?? -1] ?? '',
                    $data[$map['message'] ?? -1] ?? '',
                ];
            }
            fclose($handle);
        }

        $csv = $this->absolutePath((string) $this->option('csv'));
        if (! is_dir(dirname($csv))) {
            mkdir(dirname($csv), 0775, true);
        }
        $out = fopen($csv, 'w');
        fputcsv($out, ['state_code', 'state_name', 'lga_name', 'issue', 'external_name', 'message']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);

        $this->table(['Metric', 'Value'], [
            ['Zero-public-business LGAs', $zeroCoverage->count()],
            ['Current OSM resolution/fetch gaps', $currentResolutionGaps],
            ['Approved LGA aliases', $approvedAliases],
            ['Pending LGA aliases', $pendingAliases],
            ['Report', $this->relativePath($csv)],
        ]);

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        return base_path($path);
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
