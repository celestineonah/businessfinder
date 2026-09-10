#!/bin/sh
set -u

APP="/home/u699775886/businessfinder-app"
PHP84="/opt/alt/php84/usr/bin/php"
OUTDIR="$APP/storage/app/imports/businesses/osm"
LOG="$APP/storage/logs/stage-6e-osm-population.log"
PIDFILE="$OUTDIR/population.pid"
RUNNER="$APP/scripts/data/populate-osm-businesses-statewise.sh"
DONE="$OUTDIR/statewise-population.complete"
LOCKDIR="$OUTDIR/watchdog.lock"

cd "$APP" || exit 1
mkdir -p "$APP/storage/logs" "$OUTDIR"

[ -f "$DONE" ] && exit 0

# Atomic lock prevents concurrent cron invocations from both starting a runner.
if ! mkdir "$LOCKDIR" 2>/dev/null; then
    exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT HUP INT TERM

if [ -f "$PIDFILE" ]; then
    PID="$(cat "$PIDFILE" 2>/dev/null || true)"
    case "$PID" in
        ''|*[!0-9]*) ;;
        *)
            if kill -0 "$PID" 2>/dev/null; then
                CMD="$(ps -p "$PID" -o args= 2>/dev/null || true)"
                case "$CMD" in
                    *populate-osm-businesses-statewise.sh*) exit 0 ;;
                esac
            fi
            ;;
    esac
fi

if ps -eo comm=,args= 2>/dev/null \
    | awk '$1 == "sh" && index($0, "/scripts/data/populate-osm-businesses-statewise.sh") { found=1 } END { exit found ? 0 : 1 }'
then
    exit 0
fi

# A dead statewise parent can leave its PHP fetch child reparented to PID 1.
# Match by command identity rather than output-dir representation because
# the running command may use either a relative or absolute output path.
ORPHANS="$(
    ps -eo pid=,ppid=,comm=,args= 2>/dev/null \
        | awk '
            $2 == 1 &&
            ($3 == "php" || index($3, "php") == 1) &&
            index($0, "artisan businessfinder:fetch-osm-businesses") {
                print $1
            }
        '
)"

if [ -n "$ORPHANS" ]; then
    printf '\n[%s] watchdog found orphan OSM fetcher(s): %s\n' \
        "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
        "$(printf '%s' "$ORPHANS" | tr '\n' ' ')" >> "$LOG"

    for PID in $ORPHANS
    do
        kill "$PID" 2>/dev/null || true
    done

    sleep 3

    for PID in $ORPHANS
    do
        if kill -0 "$PID" 2>/dev/null; then
            kill -9 "$PID" 2>/dev/null || true
        fi
    done
fi

printf '\n[%s] watchdog starting/resuming Stage 6E.3 self-sanitizing statewise population\n' \
    "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" >> "$LOG"

nohup env \
    PHP84="$PHP84" \
    SLEEP_SECONDS=2.5 \
    TIMEOUT_SECONDS=180 \
    sh "$RUNNER" \
    >> "$LOG" 2>&1 < /dev/null &

PID=$!
printf '%s\n' "$PID" > "$PIDFILE"
printf '[%s] Stage 6E.3 started PID %s\n' \
    "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" "$PID" >> "$LOG"

exit 0
