** WARNING: connection is not using a post-quantum key exchange algorithm.
** This session may be vulnerable to "store now, decrypt later" attacks.
** The server may need to be upgraded. See https://openssh.com/pq.html
#!/bin/sh
# Monitoring Asterisk queue -> HelpDesk
# Cron (каждые 15 сек):
#   * * * * * /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1
#   * * * * * sleep 15 && /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1
#   * * * * * sleep 30 && /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1
#   * * * * * sleep 45 && /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1

export HELPDESK_TOKEN='<PBX_TOKEN из .env HelpDesk>'
export HELPDESK_URL=https://vega8.ru/pbx/queue-status

QUEUE_NAME="QUEUE-F38325E796B3FFB8938BA383AA119148"
TOKEN=${HELPDESK_TOKEN:-"REPLACE_WITH_YOUR_TOKEN"}

# Защита от параллельных запусков
LOCKFILE=/tmp/queue_monitor.lock
if ! mkdir "$LOCKFILE" 2>/dev/null; then
    exit 0
fi
trap "rm -rf $LOCKFILE" EXIT INT TERM

# Ротация лога: если > 5000 строк — оставляем последние 2000
LOG=/var/log/queue_monitor.log
if [ -f "$LOG" ] && [ "$(wc -l < "$LOG" 2>/dev/null)" -gt 5000 ]; then
    tail -n 2000 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi

# Получаем данные очереди
RAW=$(asterisk -rx "queue show $QUEUE_NAME" 2>/dev/null)
OUTPUT=$(echo "$RAW" | tr -d '\033' | sed 's/\[[0-9;]*m//g')

if [ -z "$OUTPUT" ]; then
    echo "$(date +%H:%M:%S) ERROR: asterisk не ответил"
    exit 1
fi

WAITING=$(echo "$OUTPUT" | awk 'NR==1{print $3}' | tr -dc '0-9')
WAITING=${WAITING:-0}
TOTAL=$(echo "$OUTPUT" | grep -c "has taken")
TOTAL=${TOTAL:-0}
TALKING=$(echo "$OUTPUT" | grep "has taken" | grep -c "(Busy)")
TALKING=${TALKING:-0}
ACTIVE=$(echo "$OUTPUT" | grep "has taken" | grep -cv "Unavailable")
ACTIVE=${ACTIVE:-0}

RAW_B64=$(printf "%s" "$OUTPUT" | base64 | tr -d '\n')

CHANNELS_RAW=$(asterisk -rx "core show channels verbose" 2>/dev/null)
CHANNELS_OUTPUT=$(echo "$CHANNELS_RAW" | tr -d '\033' | sed 's/\[[0-9;]*m//g')
CHANNELS_B64=$(printf "%s" "$CHANNELS_OUTPUT" | base64 | tr -d '\n')

CONTACTS=$(asterisk -rx "pjsip show contacts" 2>/dev/null | tr -d '\033' | sed 's/\[[0-9;]*m//g')

# Динамически берём список extension из очереди + фиксированный список
EXTS=$(echo "$OUTPUT" | grep "has taken" | awk '{print $1}' | tr -dc '0-9\n' | sort -u)
# Fallback если парсинг не дал результатов
if [ -z "$EXTS" ]; then
    EXTS="102 105 106 110 112 221"
fi

PHONES_JSON=""
for EXT in $EXTS; do
    LINE=$(echo "$CONTACTS" | grep "Contact:  $EXT/" | head -1)
    if [ -n "$LINE" ]; then
        STATUS=$(echo "$LINE" | awk '{print $4}' | tr -dc 'A-Za-z0-9_')
        RTT=$(echo "$LINE" | awk '{print $5}' | tr -dc '0-9.')
    fi
    STATUS=${STATUS:-Unknown}
    # Проверяем что RTT — число, иначе 0
    case "$RTT" in
        ''|*[!0-9.]*) RTT=0 ;;
    esac
    RTT=${RTT:-0}
    ENTRY="{\"extension\":\"$EXT\",\"status\":\"$STATUS\",\"rtt_ms\":$RTT}"
    PHONES_JSON="${PHONES_JSON:+$PHONES_JSON,}$ENTRY"
    unset STATUS RTT
done

# Транк
TRUNK_LINE=$(echo "$CONTACTS" | grep "Contact:  SIP-TRUNK-" | head -1)
if [ -n "$TRUNK_LINE" ]; then
    TRUNK_STATUS=$(echo "$TRUNK_LINE" | awk '{print $4}' | tr -dc 'A-Za-z0-9_')
    TRUNK_RTT=$(echo "$TRUNK_LINE" | awk '{print $5}' | tr -dc '0-9.')
fi
TRUNK_STATUS=${TRUNK_STATUS:-Unknown}
case "$TRUNK_RTT" in
    ''|*[!0-9.]*) TRUNK_RTT=0 ;;
esac
TRUNK_RTT=${TRUNK_RTT:-0}
TRUNK_JSON="{\"status\":\"$TRUNK_STATUS\",\"rtt_ms\":$TRUNK_RTT}"

JSON="{\"token\":\"$TOKEN\",\"queue\":\"$QUEUE_NAME\",\"waiting\":$WAITING,\"talking\":$TALKING,\"active_members\":$ACTIVE,\"total_members\":$TOTAL,\"raw\":\"$RAW_B64\",\"channels_raw\":\"$CHANNELS_B64\",\"phones\":[$PHONES_JSON],\"trunk\":$TRUNK_JSON}"

HTTP_CODE=$(curl -s -o /tmp/queue_monitor_resp.txt -w "%{http_code}" \
    --max-time 10 --connect-timeout 5 \
    -X POST "$HELPDESK_URL" \
    -H "Content-Type: application/json" \
    -d "$JSON")

if [ "$HTTP_CODE" = "200" ]; then
    echo "$(date +%H:%M:%S) HTTP:$HTTP_CODE W:$WAITING T:$TALKING A:$ACTIVE/$TOTAL"
else
    RESP=$(cat /tmp/queue_monitor_resp.txt 2>/dev/null | head -c 200 | tr -d '\n')
    echo "$(date +%H:%M:%S) HTTP:${HTTP_CODE:-ERR} W:$WAITING T:$TALKING A:$ACTIVE/$TOTAL RESP:$RESP"
fi

# Проверяем команду в ответе от хелпдеска
CMD=$(grep -o '"cmd":"[^"]*"' /tmp/queue_monitor_resp.txt 2>/dev/null | sed 's/"cmd":"//;s/"//')
if [ -n "$CMD" ]; then
    case "$CMD" in
        pjsip_reload)
            asterisk -rx "module reload res_pjsip.so" >/dev/null 2>&1
            echo "$(date +%H:%M:%S) CMD pjsip_reload: executed"
            ;;
        queue_reload)
            asterisk -rx "queue reload all" >/dev/null 2>&1
            echo "$(date +%H:%M:%S) CMD queue_reload: executed"
            ;;
        qualify_all)
            asterisk -rx "pjsip qualify all" >/dev/null 2>&1
            echo "$(date +%H:%M:%S) CMD qualify_all: executed"
            ;;
        *)
            echo "$(date +%H:%M:%S) CMD unknown: $CMD"
            ;;
    esac
fi
