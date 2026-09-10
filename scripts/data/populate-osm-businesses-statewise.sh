#!/bin/sh
set -u

cd "$(dirname "$0")/../.."

PHP84="${PHP84:-/opt/alt/php84/usr/bin/php}"
OUTDIR="${OUTDIR:-storage/app/imports/businesses/osm}"
LOGDIR="storage/logs"
SOURCE_URL="https://www.openstreetmap.org/copyright"

SLEEP_SECONDS="${SLEEP_SECONDS:-2.5}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-180}"

ENDPOINT_1="${ENDPOINT_1:-https://overpass.private.coffee/api/interpreter}"
ENDPOINT_2="${ENDPOINT_2:-https://maps.mail.ru/osm/tools/overpass/api/interpreter}"
ENDPOINT_3="${ENDPOINT_3:-https://overpass-api.de/api/interpreter}"

STATE_CODES="FC AB AD AK AN BA BY BE BO CR DE EB ED EK EN GO IM JI KD KN KT KE KO KW LA NA NI OG ON OS OY PL RI SO TA YO ZA"

mkdir -p "$OUTDIR" "$LOGDIR" storage/app/reports

import_state() {
    code="$1"
    lower="$(printf '%s' "$code" | tr '[:upper:]' '[:lower:]')"
    csv="$OUTDIR/state-$lower.csv"

    if [ ! -f "$csv" ]; then
        echo "IMPORT $code — no state CSV yet"
        return 0
    fi

    rows=$(($(wc -l < "$csv") - 1))
    [ "$rows" -lt 0 ] && rows=0

    if [ "$rows" -eq 0 ]; then
        echo "IMPORT $code — CSV has no source rows"
        return 0
    fi

    echo "IMPORT $code — $rows staged source rows"

    if ! "$PHP84" artisan businessfinder:import-businesses \
        "$csv" \
        --source="OpenStreetMap" \
        --source-url="$SOURCE_URL" \
        --min-confidence=0.70
    then
        echo "IMPORT $code — DRY-RUN FAILED"
        return 1
    fi

    if ! "$PHP84" artisan businessfinder:import-businesses \
        "$csv" \
        --source="OpenStreetMap" \
        --source-url="$SOURCE_URL" \
        --min-confidence=0.70 \
        --apply
    then
        echo "IMPORT $code — APPLY FAILED"
        return 1
    fi

    echo "IMPORT $code — COMPLETE"
    return 0
}

echo "===== STAGE 6E.2 — STATEWISE NATIONWIDE ACCELERATOR ====="
echo "Source: OpenStreetMap contributors (ODbL)"
echo "Strategy: fetch one jurisdiction at a time, import immediately"
echo "Output: $OUTDIR"
echo

for code in $STATE_CODES
do
    lower="$(printf '%s' "$code" | tr '[:upper:]' '[:lower:]')"
    marker="$OUTDIR/state-$lower.population-complete"
    attempts="$OUTDIR/state-$lower.fetch-attempts"

    if [ -f "$marker" ]; then
        echo "===== $code — ALREADY COMPLETE ====="
        continue
    fi

    echo
    echo "===== $code — FETCH ====="

    FETCH_STATUS=0

    "$PHP84" artisan businessfinder:fetch-osm-businesses \
        --state="$code" \
        --output-dir="$OUTDIR" \
        --sleep="$SLEEP_SECONDS" \
        --timeout="$TIMEOUT_SECONDS" \
        --endpoint="$ENDPOINT_1" \
        --endpoint="$ENDPOINT_2" \
        --endpoint="$ENDPOINT_3" \
        --resume \
        || FETCH_STATUS=$?

    echo "FETCH $code exit status: $FETCH_STATUS"

    echo
    echo "===== $code — IMMEDIATE IMPORT ====="

    IMPORT_STATUS=0
    import_state "$code" || IMPORT_STATUS=$?

    # The fetcher's resolution report describes the current invocation.
    FETCH_ERRORS=0
    if [ -f "$OUTDIR/lga-resolution-report.csv" ]; then
        FETCH_ERRORS="$(grep -c 'fetch_error' "$OUTDIR/lga-resolution-report.csv" 2>/dev/null || true)"
    fi

    if [ "$FETCH_STATUS" -eq 0 ] && [ "$IMPORT_STATUS" -eq 0 ] && [ "$FETCH_ERRORS" -eq 0 ]; then
        date -u '+%Y-%m-%dT%H:%M:%SZ' > "$marker"
        rm -f "$attempts"
        echo "STATE $code — PASS COMPLETE"
    else
        count=0
        if [ -f "$attempts" ]; then
            count="$(cat "$attempts" 2>/dev/null || printf '0')"
        fi

        case "$count" in
            ''|*[!0-9]*) count=0 ;;
        esac

        count=$((count + 1))
        printf '%s\n' "$count" > "$attempts"

        echo "STATE $code — INCOMPLETE (attempt $count); it will retry on a later watchdog cycle."
    fi

    echo
    echo "===== COVERAGE AFTER $code ====="

    "$PHP84" artisan businessfinder:business-coverage \
        --csv=storage/app/reports/business-coverage.csv \
        || true

    # Small courtesy pause between jurisdictions.
    sleep 3
done

echo
echo "===== FINAL STATEWISE COVERAGE ====="

"$PHP84" artisan businessfinder:business-coverage \
    --csv=storage/app/reports/business-coverage.csv \
    || true

MISSING=0
for code in $STATE_CODES
do
    lower="$(printf '%s' "$code" | tr '[:upper:]' '[:lower:]')"
    marker="$OUTDIR/state-$lower.population-complete"

    if [ ! -f "$marker" ]; then
        MISSING=$((MISSING + 1))
    fi
done

echo
echo "Jurisdictions without completion markers: $MISSING"

if [ "$MISSING" -eq 0 ]; then
    date -u '+%Y-%m-%dT%H:%M:%SZ' > "$OUTDIR/statewise-population.complete"
    echo "STAGE 6E.2 STATEWISE POPULATION COMPLETE"
else
    rm -f "$OUTDIR/statewise-population.complete"
    echo "STAGE 6E.2 CYCLE COMPLETE — watchdog will retry remaining jurisdictions"
fi

exit 0
