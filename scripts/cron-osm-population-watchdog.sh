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
LOCKPID="$LOCKDIR/pid"

cd "$APP" || exit 1
mkdir -p "$APP/storage/logs" "$OUTDIR"

[ -f "$DONE" ] && exit 0

release_lock() {
    rm -f "$LOCKPID" 2>/dev/null || true
    rmdir "$LOCKDIR" 2>/dev/null || true
}

lock_is_live() {
    [ -f "$LOCKPID" ] || return 1

    LOCK_OWNER="$(cat "$LOCKPID" 2>/dev/null || true)"
    case "$LOCK_OWNER" in
        ''|*[!0-9]*) return 1 ;;
    esac

    kill -0 "$LOCK_OWNER" 2>/dev/null || return 1

    LOCK_CMD="$(ps -p "$LOCK_OWNER" -o args= 2>/dev/null || true)"
    case "$LOCK_CMD" in
        *cron-osm-population-watchdog.sh*) return 0 ;;
    esac

    return 1
}

acquire_lock() {
    if mkdir "$LOCKDIR" 2>/dev/null; then
        printf '%s\n' "$$" > "$LOCKPID"
        trap release_lock EXIT HUP INT TERM
        return 0
    fi

    if lock_is_live; then
        exit 0
    fi

    # If a watchdog died after mkdir but before writing the owner PID,
    # avoid racing a just-created lock. Only reap ownerless locks that
    # are at least two minutes old.
    if [ ! -f "$LOCKPID" ]; then
        if ! find "$LOCKDIR" -maxdepth 0 -mmin +1 -print 2>/dev/null \
            | grep -q .
        then
            exit 0
        fi
    fi

    printf '\n[%s] watchdog removing stale lock directory\n' \
        "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" >> "$LOG"

    rm -rf "$LOCKDIR" 2>/dev/null || exit 0

    if ! mkdir "$LOCKDIR" 2>/dev/null; then
        exit 0
    fi

    printf '%s\n' "$$" > "$LOCKPID"
    trap release_lock EXIT HUP INT TERM
}

acquire_lock

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

# If the runner has died, a synchronous PHP fetch command can survive
# reparented to PID 1. Remove only orphaned BusinessFinder OSM fetchers
# before starting a checkpoint/resume cycle.
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
