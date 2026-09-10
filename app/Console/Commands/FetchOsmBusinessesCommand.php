<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FetchOsmBusinessesCommand extends Command
{
    protected $signature = 'businessfinder:fetch-osm-businesses
        {--state=* : State/FCT codes to fetch, e.g. LA FC. Omit for all 37 jurisdictions}
        {--output-dir=storage/app/imports/businesses/osm : Output directory for per-state CSV files}
        {--endpoint=* : Optional Overpass API endpoints. May be repeated}
        {--sleep=1.5 : Delay in seconds between successful LGA requests}
        {--timeout=180 : HTTP/Overpass timeout in seconds}
        {--resume : Resume from the checkpoint and existing CSV files}
        {--limit-lgas=0 : Optional maximum LGAs per state for testing; 0 means all}';

    protected $description = 'Fetch real named Nigerian business/service POIs from OpenStreetMap by State/FCT and LGA into audited-import CSV files.';

    private const CSV_HEADER = [
        'external_id',
        'business_name',
        'state_code',
        'lga_name',
        'confidence_score',
        'source_url',
        'legal_name',
        'cac_number',
        'primary_phone',
        'whatsapp_phone',
        'primary_email',
        'website_url',
        'address_line_1',
        'address_line_2',
        'landmark',
        'postal_code',
        'latitude',
        'longitude',
        'category_hint',
    ];

    private array $endpoints = [];
    private int $timeout = 180;
    private float $sleepSeconds = 1.5;
    private string $outputDir = '';
    private string $checkpointPath = '';
    private array $checkpoint = [];
    private array $resolutionRows = [];

    public function handle(): int
    {
        $this->timeout = max(30, (int) $this->option('timeout'));
        $this->sleepSeconds = max(0.0, (float) $this->option('sleep'));

        $requestedEndpoints = array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            (array) $this->option('endpoint')
        )));

        $this->endpoints = $requestedEndpoints !== []
            ? $requestedEndpoints
            : [
                'https://overpass-api.de/api/interpreter',
                'https://overpass.kumi.systems/api/interpreter',
            ];

        $outputOption = trim((string) $this->option('output-dir'));
        $this->outputDir = $this->absolutePath($outputOption);

        if (! is_dir($this->outputDir) && ! mkdir($this->outputDir, 0775, true) && ! is_dir($this->outputDir)) {
            $this->error("Could not create output directory: {$this->outputDir}");
            return self::FAILURE;
        }

        $this->checkpointPath = $this->outputDir . '/checkpoint.json';
        $this->checkpoint = $this->loadCheckpoint((bool) $this->option('resume'));

        $stateCodes = array_values(array_unique(array_map(
            static fn ($value) => strtoupper(trim((string) $value)),
            (array) $this->option('state')
        )));

        $states = DB::table('states')
            ->select(['id', 'name', 'code'])
            ->where('is_active', true)
            ->when($stateCodes !== [], fn ($query) => $query->whereIn('code', $stateCodes))
            ->orderByRaw("CASE WHEN code = 'FC' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        if ($states->isEmpty()) {
            $this->error('No matching active Nigerian state/FCT records were found.');
            return self::FAILURE;
        }

        if ($stateCodes !== []) {
            $foundCodes = $states->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();
            $unknown = array_values(array_diff($stateCodes, $foundCodes));
            if ($unknown !== []) {
                $this->warn('Unknown/inactive state codes skipped: ' . implode(', ', $unknown));
            }
        }

        $limitLgas = max(0, (int) $this->option('limit-lgas'));
        $resume = (bool) $this->option('resume');
        $totalWritten = 0;
        $totalResolvedLgas = 0;
        $totalUnresolvedLgas = 0;
        $stateFailures = 0;

        $this->info('OpenStreetMap source: https://www.openstreetmap.org/copyright');
        $this->line('Output directory: ' . $this->relativeDisplayPath($this->outputDir));
        $this->line('Mode: ' . ($resume ? 'resume' : 'fresh'));
        $this->newLine();

        foreach ($states as $state) {
            $stateCode = strtoupper((string) $state->code);
            $this->components->info("{$state->name} ({$stateCode})");

            try {
                $boundaries = $this->fetchLgaBoundaries($stateCode, (string) $state->name);
            } catch (Throwable $exception) {
                $stateFailures++;
                $this->error("Could not resolve LGA boundaries for {$stateCode}: {$exception->getMessage()}");
                $this->resolutionRows[] = [
                    'state_code' => $stateCode,
                    'state_name' => $state->name,
                    'lga_name' => '',
                    'status' => 'state_boundary_error',
                    'osm_relation_id' => '',
                    'osm_name' => '',
                    'score' => '',
                    'message' => $exception->getMessage(),
                ];
                continue;
            }

            $lgasQuery = DB::table('local_government_areas')
                ->select(['id', 'name', 'slug'])
                ->where('state_id', $state->id)
                ->where('is_active', true)
                ->orderBy('name');

            if ($limitLgas > 0) {
                $lgasQuery->limit($limitLgas);
            }

            $lgas = $lgasQuery->get();
            $matches = $this->matchLgas($lgas->all(), $boundaries, (int) $state->id);

            $csvPath = $this->outputDir . '/state-' . strtolower($stateCode) . '.csv';
            $knownExternalIds = $this->existingExternalIds($csvPath, $resume);
            $csv = $this->openCsv($csvPath, $resume);

            $stateWritten = 0;
            $stateResolved = 0;
            $stateUnresolved = 0;

            foreach ($lgas as $lga) {
                $checkpointKey = $stateCode . ':' . $lga->id;

                if ($resume && ! empty($this->checkpoint['completed'][$checkpointKey])) {
                    $this->line("  SKIP {$lga->name} — already completed");
                    continue;
                }

                $match = $matches[$lga->id] ?? null;

                if ($match === null) {
                    $stateUnresolved++;
                    $totalUnresolvedLgas++;
                    $this->warn("  UNRESOLVED {$lga->name}");
                    $this->resolutionRows[] = [
                        'state_code' => $stateCode,
                        'state_name' => $state->name,
                        'lga_name' => $lga->name,
                        'status' => 'unresolved',
                        'osm_relation_id' => '',
                        'osm_name' => '',
                        'score' => '',
                        'message' => 'No sufficiently reliable OSM admin_level=6 relation match.',
                    ];
                    continue;
                }

                $stateResolved++;
                $totalResolvedLgas++;
                $relationId = (int) $match['id'];

                try {
                    $elements = $this->fetchBusinessElements($relationId);
                } catch (Throwable $exception) {
                    $this->error("  ERROR {$lga->name} — {$exception->getMessage()}");
                    $this->resolutionRows[] = [
                        'state_code' => $stateCode,
                        'state_name' => $state->name,
                        'lga_name' => $lga->name,
                        'status' => 'fetch_error',
                        'osm_relation_id' => $relationId,
                        'osm_name' => $match['name'],
                        'score' => number_format((float) $match['score'], 4, '.', ''),
                        'message' => $exception->getMessage(),
                    ];
                    continue;
                }

                $written = 0;
                foreach ($elements as $element) {
                    $row = $this->businessRow($element, $stateCode, (string) $lga->name);
                    if ($row === null) {
                        continue;
                    }

                    if (isset($knownExternalIds[$row['external_id']])) {
                        continue;
                    }

                    fputcsv($csv, array_map(
                        fn ($column) => $row[$column] ?? '',
                        self::CSV_HEADER
                    ));

                    $knownExternalIds[$row['external_id']] = true;
                    $written++;
                    $stateWritten++;
                    $totalWritten++;
                }

                fflush($csv);
                $this->checkpoint['completed'][$checkpointKey] = [
                    'state_code' => $stateCode,
                    'lga_id' => (int) $lga->id,
                    'lga_name' => $lga->name,
                    'osm_relation_id' => $relationId,
                    'osm_name' => $match['name'],
                    'score' => $match['score'],
                    'rows_written' => $written,
                    'completed_at' => now()->toIso8601String(),
                ];
                $this->saveCheckpoint();

                $this->resolutionRows[] = [
                    'state_code' => $stateCode,
                    'state_name' => $state->name,
                    'lga_name' => $lga->name,
                    'status' => 'completed',
                    'osm_relation_id' => $relationId,
                    'osm_name' => $match['name'],
                    'score' => number_format((float) $match['score'], 4, '.', ''),
                    'message' => "{$written} new rows written",
                ];

                $this->line("  OK {$lga->name} — {$written} new POIs");
                $this->sleepBetweenRequests();
            }

            fclose($csv);
            $this->line("  State summary: resolved={$stateResolved}, unresolved={$stateUnresolved}, new_rows={$stateWritten}");
            $this->newLine();
        }

        $resolutionPath = $this->writeResolutionReport();

        $this->table(
            ['Metric', 'Value'],
            [
                ['States/FCT processed', $states->count()],
                ['LGA matches resolved this run', $totalResolvedLgas],
                ['LGA matches unresolved this run', $totalUnresolvedLgas],
                ['New OSM rows written', $totalWritten],
                ['State-level fetch failures', $stateFailures],
                ['Resolution report', $this->relativeDisplayPath($resolutionPath)],
                ['Checkpoint', $this->relativeDisplayPath($this->checkpointPath)],
            ]
        );

        if ($stateFailures >= $states->count()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function fetchLgaBoundaries(string $stateCode, string $stateName): array
    {
        $iso = 'NG-' . $stateCode;
        $query = sprintf(
            '[out:json][timeout:%d];rel["boundary"="administrative"]["admin_level"="4"]["ISO3166-2"="%s"];map_to_area->.state;rel(area.state)["boundary"="administrative"]["admin_level"="6"];out ids tags;',
            min($this->timeout, 120),
            $this->overpassEscape($iso)
        );

        $data = $this->requestOverpass($query);
        $elements = array_values(array_filter(
            $data['elements'] ?? [],
            fn ($element) => ($element['type'] ?? null) === 'relation'
        ));

        if ($elements !== []) {
            return $elements;
        }

        $query = sprintf(
            '[out:json][timeout:%d];rel["boundary"="administrative"]["admin_level"="4"]["name"="%s"];map_to_area->.state;rel(area.state)["boundary"="administrative"]["admin_level"="6"];out ids tags;',
            min($this->timeout, 120),
            $this->overpassEscape($stateName)
        );

        $data = $this->requestOverpass($query);
        $elements = array_values(array_filter(
            $data['elements'] ?? [],
            fn ($element) => ($element['type'] ?? null) === 'relation'
        ));

        if ($elements === []) {
            throw new RuntimeException("No OSM admin_level=6 relations found for {$stateName} ({$stateCode}).");
        }

        return $elements;
    }

    private function fetchBusinessElements(int $relationId): array
    {
        $timeout = $this->timeout;
        $query = <<<QL
[out:json][timeout:{$timeout}];
rel({$relationId});
map_to_area->.lga;
(
  nwr(area.lga)["name"]["shop"];
  nwr(area.lga)["name"]["office"];
  nwr(area.lga)["name"]["craft"];
  nwr(area.lga)["name"]["amenity"~"^(restaurant|cafe|fast_food|bank|pharmacy|fuel|car_rental|car_wash|bar|pub|cinema|nightclub|bureau_de_change|driving_school|marketplace|veterinary|dentist|doctors|clinic|hospital)$"];
  nwr(area.lga)["name"]["tourism"~"^(hotel|guest_house|hostel|motel|apartment)$"];
  nwr(area.lga)["name"]["healthcare"];
);
out center tags qt;
QL;

        $data = $this->requestOverpass($query);
        return array_values($data['elements'] ?? []);
    }

    private function requestOverpass(string $query): array
    {
        $errors = [];

        foreach ($this->endpoints as $endpoint) {
            $endpoint = rtrim($endpoint, '/');

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 20,
                    CURLOPT_TIMEOUT => $this->timeout + 30,
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Content-Type: application/x-www-form-urlencoded',
                        'User-Agent: BusinessFinder-Nigeria-Importer/1.0 (+https://businessfinder.com.ng/)',
                    ],
                ]);

                $body = curl_exec($ch);
                $curlError = curl_error($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);

                if ($body !== false && $status >= 200 && $status < 300) {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                    $errors[] = "{$endpoint} returned invalid JSON";
                } else {
                    $errors[] = "{$endpoint} attempt {$attempt}: HTTP {$status}" . ($curlError !== '' ? " / {$curlError}" : '');
                }

                usleep((int) (min(20, 2 ** $attempt) * 1_000_000));
            }
        }

        throw new RuntimeException('Overpass request failed: ' . implode(' | ', array_slice($errors, -6)));
    }

    private function matchLgas(array $lgas, array $boundaries, int $stateId): array
    {
        $boundaryNames = [];

        foreach ($boundaries as $boundary) {
            $tags = $boundary['tags'] ?? [];
            $names = array_values(array_unique(array_filter([
                $tags['name'] ?? null,
                $tags['official_name'] ?? null,
                $tags['short_name'] ?? null,
                $tags['alt_name'] ?? null,
            ], fn ($value) => is_string($value) && trim($value) !== '')));

            foreach ($names as $name) {
                $normalized = $this->normalizeAdminName($name);
                if ($normalized === '') {
                    continue;
                }
                $boundaryNames[] = [
                    'id' => (int) $boundary['id'],
                    'name' => (string) ($tags['name'] ?? $name),
                    'candidate' => $name,
                    'normalized' => $normalized,
                ];
            }
        }

        $approvedAliases = DB::table('geography_lga_aliases')
            ->where('state_id', $stateId)
            ->where('source_name', 'OpenStreetMap')
            ->where('status', 'approved')
            ->get(['local_government_area_id', 'normalized_external_name', 'confidence_score'])
            ->groupBy('local_government_area_id');

        $matches = [];
        $usedRelations = [];

        foreach ($lgas as $lga) {
            $target = $this->normalizeAdminName((string) $lga->name);

            $aliasCandidates = [];
            foreach ($approvedAliases->get($lga->id, collect()) as $alias) {
                foreach ($boundaryNames as $item) {
                    if (isset($usedRelations[$item['id']])) {
                        continue;
                    }
                    if ($item['normalized'] === $alias->normalized_external_name) {
                        $aliasCandidates[$item['id']] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'score' => (float) $alias->confidence_score,
                        ];
                    }
                }
            }

            if (count($aliasCandidates) === 1) {
                $chosen = array_values($aliasCandidates)[0];
                $matches[$lga->id] = $chosen;
                $usedRelations[$chosen['id']] = true;
                continue;
            }

            $exact = array_values(array_filter(
                $boundaryNames,
                fn ($item) => $item['normalized'] === $target && ! isset($usedRelations[$item['id']])
            ));

            if ($exact !== []) {
                $chosen = $exact[0];
                $matches[$lga->id] = [
                    'id' => $chosen['id'],
                    'name' => $chosen['name'],
                    'score' => 1.0,
                ];
                $usedRelations[$chosen['id']] = true;
                continue;
            }

            $scores = [];
            foreach ($boundaryNames as $item) {
                if (isset($usedRelations[$item['id']])) {
                    continue;
                }
                similar_text($target, $item['normalized'], $percent);
                $scores[] = [
                    ...$item,
                    'score' => $percent / 100,
                ];
            }

            usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
            $best = $scores[0] ?? null;
            $second = $scores[1] ?? null;

            if (
                $best !== null
                && $best['score'] >= 0.92
                && ($second === null || ($best['score'] - $second['score']) >= 0.03)
            ) {
                $matches[$lga->id] = [
                    'id' => $best['id'],
                    'name' => $best['name'],
                    'score' => $best['score'],
                ];
                $usedRelations[$best['id']] = true;
            }
        }

        return $matches;
    }

    private function businessRow(array $element, string $stateCode, string $lgaName): ?array
    {
        $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
        $name = $this->cleanText($tags['name'] ?? null, 180);

        if ($name === null || $this->isGenericName($name)) {
            return null;
        }

        if ($this->isInactiveFeature($tags)) {
            return null;
        }

        $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
        $lon = $element['lon'] ?? ($element['center']['lon'] ?? null);

        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return null;
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        $type = (string) ($element['type'] ?? '');
        $id = isset($element['id']) ? (string) $element['id'] : '';

        if (! in_array($type, ['node', 'way', 'relation'], true) || $id === '') {
            return null;
        }

        $phone = $this->cleanText($tags['contact:phone'] ?? ($tags['phone'] ?? null), 40);
        $whatsapp = $this->cleanText($tags['contact:whatsapp'] ?? ($tags['whatsapp'] ?? null), 40);
        $email = $this->cleanText($tags['contact:email'] ?? ($tags['email'] ?? null), 180);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = null;
        }
        $website = $this->cleanText($tags['contact:website'] ?? ($tags['website'] ?? null), 500);
        $legalName = $this->cleanText($tags['official_name'] ?? null, 200);
        $postalCode = $this->cleanText($tags['addr:postcode'] ?? null, 30);

        $addressLine1 = $this->cleanText($tags['addr:full'] ?? null, 255);
        if ($addressLine1 === null) {
            $parts = array_values(array_filter([
                $this->cleanText($tags['addr:housenumber'] ?? null, 40),
                $this->cleanText($tags['addr:street'] ?? null, 180),
            ]));
            if ($parts !== []) {
                $addressLine1 = $this->cleanText(implode(' ', $parts), 255);
            }
        }
        if ($addressLine1 === null) {
            $addressLine1 = $this->cleanText($tags['addr:place'] ?? null, 255);
        }

        $addressLine2 = $this->cleanText($tags['addr:suburb'] ?? ($tags['addr:neighbourhood'] ?? null), 255);
        $landmark = $this->cleanText($tags['addr:city'] ?? ($tags['addr:town'] ?? null), 255);
        $categoryHint = $this->categoryHint($tags);

        $confidence = 0.70;
        if ($categoryHint !== null) {
            $confidence += 0.05;
        }
        if ($addressLine1 !== null) {
            $confidence += 0.05;
        }
        if ($phone !== null || $whatsapp !== null) {
            $confidence += 0.05;
        }
        if ($website !== null || $email !== null) {
            $confidence += 0.05;
        }
        if ($legalName !== null) {
            $confidence += 0.025;
        }
        $confidence = min(0.95, $confidence);

        return [
            'external_id' => "osm:{$type}:{$id}",
            'business_name' => $name,
            'state_code' => $stateCode,
            'lga_name' => $lgaName,
            'confidence_score' => number_format($confidence, 4, '.', ''),
            'source_url' => "https://www.openstreetmap.org/{$type}/{$id}",
            'legal_name' => $legalName ?? '',
            'cac_number' => '',
            'primary_phone' => $phone ?? '',
            'whatsapp_phone' => $whatsapp ?? '',
            'primary_email' => $email ?? '',
            'website_url' => $website ?? '',
            'address_line_1' => $addressLine1 ?? '',
            'address_line_2' => $addressLine2 ?? '',
            'landmark' => $landmark ?? '',
            'postal_code' => $postalCode ?? '',
            'latitude' => number_format($lat, 7, '.', ''),
            'longitude' => number_format($lon, 7, '.', ''),
            'category_hint' => $categoryHint ?? '',
        ];
    }

    private function categoryHint(array $tags): ?string
    {
        foreach (['shop', 'amenity', 'office', 'craft', 'tourism', 'healthcare'] as $key) {
            $value = $this->cleanText($tags[$key] ?? null, 120);
            if ($value !== null) {
                return $key . ':' . Str::slug($value, '_');
            }
        }

        return null;
    }

    private function isInactiveFeature(array $tags): bool
    {
        foreach (array_keys($tags) as $key) {
            if (str_starts_with((string) $key, 'disused:') || str_starts_with((string) $key, 'abandoned:')) {
                return true;
            }
        }

        foreach (['disused', 'abandoned', 'closed'] as $key) {
            $value = strtolower(trim((string) ($tags[$key] ?? '')));
            if (in_array($value, ['yes', 'true', '1'], true)) {
                return true;
            }
        }

        return false;
    }

    private function isGenericName(string $name): bool
    {
        $normalized = $this->normalizeAdminName($name);
        return in_array($normalized, [
            'shop', 'store', 'restaurant', 'hotel', 'pharmacy', 'bank', 'office',
            'supermarket', 'market', 'clinic', 'hospital', 'fuel station', 'petrol station',
            'car wash', 'bar', 'cafe', 'fast food', 'guest house', 'guesthouse',
        ], true);
    }

    private function normalizeAdminName(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/\b(local government area|local govt area|local government|area council|municipal area council|lga)\b/i', ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function overpassEscape(string $value): string
    {
        return addcslashes($value, "\\\"");
    }

    private function cleanText(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    private function openCsv(string $path, bool $resume)
    {
        $exists = is_file($path) && filesize($path) > 0;
        $mode = ($resume && $exists) ? 'a' : 'w';
        $handle = fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV: {$path}");
        }

        if ($mode === 'w') {
            fputcsv($handle, self::CSV_HEADER);
        }

        return $handle;
    }

    private function existingExternalIds(string $path, bool $resume): array
    {
        if (! $resume || ! is_file($path) || filesize($path) === 0) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        $ids = [];
        $index = is_array($header) ? array_search('external_id', $header, true) : false;

        if ($index !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $id = trim((string) ($row[$index] ?? ''));
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        fclose($handle);
        return $ids;
    }

    private function loadCheckpoint(bool $resume): array
    {
        if (! $resume || ! is_file($this->checkpointPath)) {
            return [
                'version' => 1,
                'source' => 'OpenStreetMap',
                'created_at' => now()->toIso8601String(),
                'completed' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->checkpointPath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Checkpoint JSON is invalid.');
        }
        $decoded['completed'] = is_array($decoded['completed'] ?? null) ? $decoded['completed'] : [];
        return $decoded;
    }

    private function saveCheckpoint(): void
    {
        $this->checkpoint['updated_at'] = now()->toIso8601String();
        $json = json_encode($this->checkpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($this->checkpointPath, $json . PHP_EOL) === false) {
            throw new RuntimeException('Could not write checkpoint file.');
        }
    }

    private function writeResolutionReport(): string
    {
        $path = $this->outputDir . '/lga-resolution-report.csv';
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException("Could not write resolution report: {$path}");
        }

        $header = ['state_code', 'state_name', 'lga_name', 'status', 'osm_relation_id', 'osm_name', 'score', 'message'];
        fputcsv($handle, $header);
        foreach ($this->resolutionRows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column] ?? '', $header));
        }
        fclose($handle);
        return $path;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Output directory cannot be empty.');
        }

        if (str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        return rtrim(base_path($path), '/');
    }

    private function relativeDisplayPath(string $path): string
    {
        $base = rtrim(base_path(), '/') . '/';
        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private function sleepBetweenRequests(): void
    {
        if ($this->sleepSeconds > 0) {
            usleep((int) round($this->sleepSeconds * 1_000_000));
        }
    }
}
