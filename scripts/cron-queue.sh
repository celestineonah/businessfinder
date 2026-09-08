#!/bin/sh

APP="/home/u699775886/businessfinder-app"
PHP="/opt/alt/php84/usr/bin/php"
ERROR_LOG="$APP/storage/logs/cron-errors.log"
TMP="$APP/storage/framework/cron-queue.$$.tmp"

cd "$APP" || exit 1

"$PHP" artisan queue:work \
    --stop-when-empty \
    --tries=3 \
    --timeout=60 \
    > "$TMP" 2>&1

STATUS=$?

if [ "$STATUS" -ne 0 ]; then
    printf '\n[%s] queue worker failed (exit %s)\n' \
        "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
        "$STATUS" >> "$ERROR_LOG"

    cat "$TMP" >> "$ERROR_LOG"
fi

rm -f "$TMP"
exit "$STATUS"
