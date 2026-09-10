#!/bin/sh
set -u

cd "$(dirname "$0")/../.."

PHP84="${PHP84:-/opt/alt/php84/usr/bin/php}"
OUTDIR="storage/app/imports/businesses/osm"
LOG="storage/logs/stage-6e-osm-population.log"
PIDFILE="$OUTDIR/population.pid"

STATE_CODES="FC AB AD AK AN BA BY BE BO CR DE EB ED EK EN GO IM JI KD KN KT KE KO KW LA NA NI OG ON OS OY PL RI SO TA YO ZA"

echo "===== STAGE 6E.2 STATUS ====="

if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses-statewise.sh' >/dev/null; then
    echo "Runner: STATEWISE RUNNING"
elif ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses-resilient.sh' >/dev/null; then
    echo "Runner: OLD RESILIENT RUNNER STILL RUNNING"
elif ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses.sh' >/dev/null; then
    echo "Runner: ORIGINAL RUNNER STILL RUNNING"
else
    echo "Runner: NOT RUNNING"
fi

if [ -f "$PIDFILE" ]; then
    echo "PID file: $(cat "$PIDFILE" 2>/dev/null || true)"
fi

echo
echo "===== JURISDICTION MARKERS ====="

DONE=0
PENDING=0

for code in $STATE_CODES
do
    lower="$(printf '%s' "$code" | tr '[:upper:]' '[:lower:]')"

    if [ -f "$OUTDIR/state-$lower.population-complete" ]; then
        DONE=$((DONE + 1))
    else
        PENDING=$((PENDING + 1))
    fi
done

echo "Completed jurisdiction passes: $DONE / 37"
echo "Pending jurisdiction passes:   $PENDING / 37"

echo
echo "===== STAGED DATA ====="

STATE_COUNT=0
TOTAL_ROWS=0

for csv in "$OUTDIR"/state-*.csv
do
    [ -f "$csv" ] || continue

    STATE_COUNT=$((STATE_COUNT + 1))
    ROWS=$(($(wc -l < "$csv") - 1))
    [ "$ROWS" -lt 0 ] && ROWS=0
    TOTAL_ROWS=$((TOTAL_ROWS + ROWS))

    printf "%-26s %8s\n" "$(basename "$csv")" "$ROWS"
done

echo
echo "State CSV count: $STATE_COUNT"
echo "Total staged source rows: $TOTAL_ROWS"

echo
echo "===== PUBLIC COVERAGE ====="

"$PHP84" artisan businessfinder:business-coverage || true

echo
echo "===== LAST 100 LOG LINES ====="

tail -100 "$LOG" 2>/dev/null || true
