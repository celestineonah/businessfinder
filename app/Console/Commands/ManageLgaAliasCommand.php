<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageLgaAliasCommand extends Command
{
    protected $signature = 'businessfinder:lga-alias
        {state_code : State/FCT code, e.g. BA}
        {external_name : Exact OSM boundary name}
        {canonical_lga : Canonical BusinessFinder LGA name or slug}
        {--source=OpenStreetMap}
        {--evidence= : Evidence URL supporting the mapping}
        {--confidence=1.0 : Confidence from 0 to 1}
        {--approve : Approve the alias for automatic matching}
        {--notes= : Optional review notes}';

    protected $description = 'Create or update an evidence-backed external LGA alias without guessing geography.';

    public function handle(): int
    {
        $stateCode = strtoupper(trim((string) $this->argument('state_code')));
        $externalName = trim((string) $this->argument('external_name'));
        $canonical = trim((string) $this->argument('canonical_lga'));
        $source = trim((string) $this->option('source')) ?: 'OpenStreetMap';
        $evidence = trim((string) $this->option('evidence'));
        $confidence = (float) $this->option('confidence');
        $approved = (bool) $this->option('approve');

        if ($confidence < 0 || $confidence > 1) {
            $this->error('Confidence must be between 0 and 1.');
            return self::FAILURE;
        }
        if ($approved && $evidence === '') {
            $this->error('Approved aliases require --evidence=URL.');
            return self::FAILURE;
        }

        $state = DB::table('states')->where('code', $stateCode)->where('is_active', true)->first();
        if ($state === null) {
            $this->error("Unknown or inactive state code: {$stateCode}");
            return self::FAILURE;
        }

        $lga = DB::table('local_government_areas')
            ->where('state_id', $state->id)
            ->where('is_active', true)
            ->where(function ($q) use ($canonical) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($canonical)])
                    ->orWhere('slug', Str::slug($canonical));
            })
            ->first();

        if ($lga === null) {
            $this->error("Canonical LGA not found in {$stateCode}: {$canonical}");
            return self::FAILURE;
        }

        $normalized = $this->normalizeAdminName($externalName);
        if ($normalized === '') {
            $this->error('External name normalizes to an empty value.');
            return self::FAILURE;
        }

        $now = now();
        DB::table('geography_lga_aliases')->updateOrInsert(
            [
                'source_name' => $source,
                'state_id' => $state->id,
                'normalized_external_name' => $normalized,
            ],
            [
                'local_government_area_id' => $lga->id,
                'external_name' => $externalName,
                'status' => $approved ? 'approved' : 'pending',
                'evidence_url' => $evidence !== '' ? $evidence : null,
                'confidence_score' => $confidence,
                'notes' => trim((string) $this->option('notes')) ?: null,
                'reviewed_at' => $approved ? $now : null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $this->table(['Field', 'Value'], [
            ['State/FCT', $state->name . ' (' . $stateCode . ')'],
            ['Canonical LGA', $lga->name],
            ['External name', $externalName],
            ['Normalized', $normalized],
            ['Source', $source],
            ['Status', $approved ? 'approved' : 'pending'],
            ['Confidence', number_format($confidence, 4)],
            ['Evidence', $evidence !== '' ? $evidence : '—'],
        ]);

        if ($approved) {
            $this->info('Approved alias will be available to future OSM state fetch processes. Re-run an unresolved state only when appropriate.');
        } else {
            $this->warn('Alias saved as pending and will NOT affect automatic matching.');
        }

        return self::SUCCESS;
    }

    private function normalizeAdminName(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/\b(local government area|local govt area|local government|area council|municipal area council|lga)\b/i', ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }
}
