#!/bin/sh
set -u

APP="/home/u699775886/businessfinder-app"
PHP84="/opt/alt/php84/usr/bin/php"
LOG="$APP/storage/logs/stage-6e-osm-population.log"
PIDFILE="$APP/storage/app/imports/businesses/osm/population.pid"
RUNNER="$APP/scripts/data/populate-osm-businesses-resilient.sh"

cd "$APP" || exit 1

mkdir -p \
    "$APP/storage/logs" \
    "$APP/storage/app/imports/businesses/osm"

# Existing new-runner PID still alive.
if [ -f "$PIDFILE" ]; then
    PID="$(cat "$PIDFILE" 2>/dev/null || true)"

    case "$PID" in
        ''|*[!0-9]*)
            ;;
        *)
            if kill -0 "$PID" 2>/dev/null; then
                exit 0
            fi
            ;;
    esac
fi

# Respect the original Stage 6E process if it is still running.
if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses.sh' >/dev/null; then
    exit 0
fi

# Respect the resilient runner if detected even with a stale/missing PID file.
if ps -ef 2>/dev/null | grep '[p]opulate-osm-businesses-resilient.sh' >/dev/null; then
    exit 0
fi

printf '\n[%s] watchdog starting/resuming Stage 6E.1\n' \
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

printf '[%s] Stage 6E.1 started PID %s\n' \
    "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
    "$PID" \
    >> "$LOG"

exit 0
