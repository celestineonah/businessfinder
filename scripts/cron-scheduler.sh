#!/bin/sh

APP="/home/u699775886/businessfinder-app"
PHP="/opt/alt/php84/usr/bin/php"
ERROR_LOG="$APP/storage/logs/cron-errors.log"
TMP="$APP/storage/framework/cron-scheduler.$$.tmp"

cd "$APP" || exit 1

"$PHP" artisan schedule:run > "$TMP" 2>&1
STATUS=$?

if [ "$STATUS" -ne 0 ]; then
    printf '\n[%s] scheduler failed (exit %s)\n' \
        "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
        "$STATUS" >> "$ERROR_LOG"

    cat "$TMP" >> "$ERROR_LOG"
fi

rm -f "$TMP"
exit "$STATUS"
