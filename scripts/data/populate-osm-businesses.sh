#!/bin/sh
set -eu

cd "$(dirname "$0")/../.."

PHP84="${PHP84:-/opt/alt/php84/usr/bin/php}"
OUTDIR="${OUTDIR:-storage/app/imports/businesses/osm}"
SOURCE_URL="https://www.openstreetmap.org/copyright"
SLEEP_SECONDS="${SLEEP_SECONDS:-1.5}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-180}"

mkdir -p "$OUTDIR" storage/app/reports

echo "===== STAGE 6E — FETCH REAL OSM BUSINESSES ====="
echo "Source: OpenStreetMap contributors (ODbL)"
echo "Output: $OUTDIR"
echo

"$PHP84" artisan businessfinder:fetch-osm-businesses \
    --output-dir="$OUTDIR" \
    --sleep="$SLEEP_SECONDS" \
    --timeout="$TIMEOUT_SECONDS" \
    --resume

echo
echo "===== STATE-BY-STATE DRY-RUN + APPLY ====="

imported_files=0
empty_files=0
failed_files=0

for csv in "$OUTDIR"/state-*.csv; do
    [ -f "$csv" ] || continue

    rows=$(($(wc -l < "$csv") - 1))
    if [ "$rows" -le 0 ]; then
        echo "SKIP $(basename "$csv") — no source rows"
        empty_files=$((empty_files + 1))
        continue
    fi

    echo
    echo "----- $(basename "$csv") : $rows source rows -----"

    if ! "$PHP84" artisan businessfinder:import-businesses \
        "$csv" \
        --source="OpenStreetMap" \
        --source-url="$SOURCE_URL" \
        --min-confidence=0.70; then
        echo "DRY-RUN FAILED: $csv"
        failed_files=$((failed_files + 1))
        continue
    fi

    if ! "$PHP84" artisan businessfinder:import-businesses \
        "$csv" \
        --source="OpenStreetMap" \
        --source-url="$SOURCE_URL" \
        --min-confidence=0.70 \
        --apply; then
        echo "APPLY FAILED: $csv"
        failed_files=$((failed_files + 1))
        continue
    fi

    imported_files=$((imported_files + 1))
done

echo
echo "===== NATIONWIDE COVERAGE AFTER IMPORT ====="
"$PHP84" artisan businessfinder:business-coverage \
    --csv=storage/app/reports/business-coverage.csv

echo
echo "===== DATABASE COUNTS ====="
"$PHP84" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$publicBusinesses = DB::table('businesses')
    ->where('listing_status', 'listed')
    ->where('is_active', true)
    ->whereNotNull('published_at')
    ->whereNull('deleted_at')
    ->count();

$osmSources = DB::table('business_sources')
    ->where('source_name', 'OpenStreetMap')
    ->where('is_active', true)
    ->count();

$unclaimed = DB::table('businesses')
    ->where('claim_status', 'unclaimed')
    ->where('listing_status', 'listed')
    ->where('is_active', true)
    ->whereNotNull('published_at')
    ->whereNull('deleted_at')
    ->count();

foreach ([
    'public_businesses' => $publicBusinesses,
    'openstreetmap_sources' => $osmSources,
    'public_unclaimed' => $unclaimed,
    'claims' => DB::table('business_claims')->count(),
    'verifications' => DB::table('business_verifications')->count(),
] as $label => $count) {
    echo $label . ': ' . $count . PHP_EOL;
}
PHP

echo
echo "===== POPULATION RUN SUMMARY ====="
echo "State CSVs imported: $imported_files"
echo "State CSVs empty:    $empty_files"
echo "State CSVs failed:   $failed_files"
echo "Coverage report:     storage/app/reports/business-coverage.csv"
echo "LGA resolution:      $OUTDIR/lga-resolution-report.csv"
echo "Checkpoint:          $OUTDIR/checkpoint.json"
echo

echo "STAGE 6E POPULATION RUN COMPLETE"
