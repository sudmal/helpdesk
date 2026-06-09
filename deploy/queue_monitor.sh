#!/bin/sh
# Monitoring Asterisk queue -> HelpDesk
# Cron: * * * * * /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1
#
# export HELPDESK_TOKEN=ВАШ_ТОКЕН
# export HELPDESK_URL=https://vega8.ru/pbx/queue-status

QUEUE_NAME="QUEUE-F38325E796B3FFB8938BA383AA119148"
HELPDESK_URL=${HELPDESK_URL:-"https://vega8.ru/pbx/queue-status"}
TOKEN=${HELPDESK_TOKEN:-"REPLACE_WITH_YOUR_TOKEN"}

RAW=$(asterisk -rx "queue show $QUEUE_NAME" 2>/dev/null)
OUTPUT=$(echo "$RAW" | tr -d '\033' | sed 's/\[[0-9;]*m//g')

if [ -z "$OUTPUT" ]; then
  echo "$(date): ERROR" >&2
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

# Сырой вывод в base64 для детального разбора на сервере
RAW_B64=$(printf "%s" "$OUTPUT" | base64 | tr -d '\n')

JSON="{\"token\":\"$TOKEN\",\"queue\":\"$QUEUE_NAME\",\"waiting\":$WAITING,\"talking\":$TALKING,\"active_members\":$ACTIVE,\"total_members\":$TOTAL,\"raw\":\"$RAW_B64\"}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$HELPDESK_URL" -H "Content-Type: application/json" -d "$JSON")

echo "$(date +%H:%M:%S) HTTP:$HTTP_CODE W:$WAITING T:$TALKING A:$ACTIVE/$TOTAL"
