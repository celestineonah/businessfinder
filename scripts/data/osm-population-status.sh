#!/bin/sh
set -u

cd "$(dirname "$0")/../.."

PHP84="${PHP84:-/opt/alt/php84/usr/bin/php}"
OUTDIR="storage/app/imports/businesses/osm"
LOG="storage/logs/stage-6e-osm-population.log"
PIDFILE="$OUTDIR/population.pid"

echo "===== STAGE 6E.1 STATUS ====="

if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses-resilient.sh' >/dev/null; then
    echo "Runner: RUNNING"
elif ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses.sh' >/dev/null; then
    echo "Runner: ORIGINAL STAGE 6E STILL RUNNING"
else
    echo "Runner: NOT RUNNING — cron watchdog should restart it within one minute"
fi

if [ -f "$PIDFILE" ]; then
    echo "PID file: $(cat "$PIDFILE" 2>/dev/null || true)"
fi

echo
echo "===== STAGED STATE CSVs ====="

STATE_COUNT=0
TOTAL_ROWS=0

for csv in "$OUTDIR"/state-*.csv; do
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
echo "===== LAST 80 LOG LINES ====="

tail -80 "$LOG" 2>/dev/null || true
