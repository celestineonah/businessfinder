#!/bin/sh
set -u

APP="/home/u699775886/businessfinder-app"
PHP84="/opt/alt/php84/usr/bin/php"
OUTDIR="$APP/storage/app/imports/businesses/osm"
LOG="$APP/storage/logs/stage-6e-osm-population.log"
PIDFILE="$OUTDIR/population.pid"
RUNNER="$APP/scripts/data/populate-osm-businesses-statewise.sh"
DONE="$OUTDIR/statewise-population.complete"

cd "$APP" || exit 1

mkdir -p "$APP/storage/logs" "$OUTDIR"

if [ -f "$DONE" ]; then
    exit 0
fi

if [ -f "$PIDFILE" ]; then
    PID="$(cat "$PIDFILE" 2>/dev/null || true)"

    case "$PID" in
        ''|*[!0-9]*)
            ;;
        *)
            if kill -0 "$PID" 2>/dev/null; then
                CMD="$(ps -p "$PID" -o args= 2>/dev/null || true)"
                case "$CMD" in
                    *populate-osm-businesses-statewise.sh*)
                        exit 0
                        ;;
                    *populate-osm-businesses-resilient.sh*)
                        exit 0
                        ;;
                    *populate-osm-businesses.sh*)
                        exit 0
                        ;;
                esac
            fi
            ;;
    esac
fi

if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses-statewise.sh' >/dev/null; then
    exit 0
fi

if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses-resilient.sh' >/dev/null; then
    exit 0
fi

if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses.sh' >/dev/null; then
    exit 0
fi

printf '\n[%s] watchdog starting/resuming Stage 6E.2 statewise accelerator\n' \
    "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
    >> "$LOG"

nohup env \
    PHP84="$PHP84" \
    SLEEP_SECONDS=2.5 \
    TIMEOUT_SECONDS=180 \
    sh "$RUNNER" \
    >> "$LOG" \
    2>&1 \
    < /dev/null &

PID=$!
printf '%s\n' "$PID" > "$PIDFILE"

printf '[%s] Stage 6E.2 started PID %s\n' \
    "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
    "$PID" \
    >> "$LOG"

exit 0
