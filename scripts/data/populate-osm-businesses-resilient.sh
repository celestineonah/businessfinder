#!/bin/sh
set -eu

cd "$(dirname "$0")/../.."

PHP84="${PHP84:-/opt/alt/php84/usr/bin/php}"
OUTDIR="${OUTDIR:-storage/app/imports/businesses/osm}"
SOURCE_URL="https://www.openstreetmap.org/copyright"
SLEEP_SECONDS="${SLEEP_SECONDS:-2.5}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-180}"

ENDPOINT_1="${ENDPOINT_1:-https://overpass.private.coffee/api/interpreter}"
ENDPOINT_2="${ENDPOINT_2:-https://maps.mail.ru/osm/tools/overpass/api/interpreter}"
ENDPOINT_3="${ENDPOINT_3:-https://overpass-api.de/api/interpreter}"

mkdir -p "$OUTDIR" storage/app/reports storage/logs

import_ready_csvs() {
    echo
    echo "===== INCREMENTAL DRY-RUN + APPLY OF STAGED STATE CSVs ====="

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
        echo "----- $(basename "$csv") : $rows staged source rows -----"

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
    echo "Incremental state CSVs imported: $imported_files"
    echo "Incremental state CSVs empty:    $empty_files"
    echo "Incremental state CSVs failed:   $failed_files"
}

echo "===== STAGE 6E.1 — RESILIENT NATIONWIDE OSM POPULATION ====="
echo "Source: OpenStreetMap contributors (ODbL)"
echo "Output: $OUTDIR"
echo "Endpoint priority:"
echo "  1. $ENDPOINT_1"
echo "  2. $ENDPOINT_2"
echo "  3. $ENDPOINT_3"
echo

# Make already-collected data visible before continuing the long fetch.
import_ready_csvs

echo
echo "===== RESUME NATIONWIDE OSM FETCH ====="

FETCH_STATUS=0

"$PHP84" artisan businessfinder:fetch-osm-businesses \
    --output-dir="$OUTDIR" \
    --sleep="$SLEEP_SECONDS" \
    --timeout="$TIMEOUT_SECONDS" \
    --endpoint="$ENDPOINT_1" \
    --endpoint="$ENDPOINT_2" \
    --endpoint="$ENDPOINT_3" \
    --resume \
    || FETCH_STATUS=$?

echo
echo "OSM fetch exit status: $FETCH_STATUS"

# Always import whatever additional rows were staged before a host timeout/exit.
import_ready_csvs

echo
echo "===== CURRENT NATIONWIDE COVERAGE ====="

"$PHP84" artisan businessfinder:business-coverage \
    --csv=storage/app/reports/business-coverage.csv \
    || true

echo
echo "===== CURRENT DATABASE COUNTS ====="

"$PHP84" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$public = DB::table('businesses')
    ->where('listing_status', 'listed')
    ->where('is_active', true)
    ->whereNotNull('published_at')
    ->whereNull('deleted_at')
    ->count();

$osm = DB::table('business_sources')
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

echo "public_businesses: {$public}" . PHP_EOL;
echo "openstreetmap_sources: {$osm}" . PHP_EOL;
echo "public_unclaimed: {$unclaimed}" . PHP_EOL;
echo "claims: " . DB::table('business_claims')->count() . PHP_EOL;
echo "verifications: " . DB::table('business_verifications')->count() . PHP_EOL;
PHP

echo
echo "===== STAGE 6E.1 RUN RESULT ====="

if [ "$FETCH_STATUS" -eq 0 ]; then
    echo "Fetch traversal reached a normal command exit."
else
    echo "Fetch traversal ended non-zero; watchdog will retry automatically."
fi

echo "Coverage report: storage/app/reports/business-coverage.csv"
echo "Checkpoint:      $OUTDIR/checkpoint.json"
echo
echo "STAGE 6E.1 RESILIENT POPULATION CYCLE COMPLETE"

exit 0
