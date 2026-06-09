#!/bin/sh
# Monitoring Asterisk queue -> HelpDesk
# Cron: * * * * * /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1

QUEUE_NAME="QUEUE-F38325E796B3FFB8938BA383AA119148"
HELPDESK_URL="https://helpdesk.browsersdnr.ru/pbx/queue-status"
TOKEN="REDACTED"

# Получить вывод и убрать ANSI-коды (цвета терминала)
RAW=$(asterisk -rx "queue show $QUEUE_NAME" 2>/dev/null)
OUTPUT=$(echo "$RAW" | tr -d "\033" | sed "s/\[[0-9;]*m//g")

if [ -z "$OUTPUT" ]; then
  echo "$(date): ERROR - no output from asterisk" >&2
  exit 1
fi

# Ожидают в очереди: строка 'has N calls'
WAITING=$(echo "$OUTPUT" | awk "/has [0-9]+ call/{for(i=1;i<=NF;i++){if($i==\"has\"){print $(i+1); exit}}}")
WAITING=${WAITING:-0}

# Всего операторов: строки с 'has taken'
TOTAL=$(echo "$OUTPUT" | grep -c "has taken")
TOTAL=${TOTAL:-0}

# Разговаривают: 'has taken' + '(in call)' или '(Busy)'
TALKING=$(echo "$OUTPUT" | grep "has taken" | grep -cE "\(in call\)|\(Busy\)")
TALKING=${TALKING:-0}

# Активных (не Unavailable): строки 'has taken' без '(Unavailable)'
ACTIVE=$(echo "$OUTPUT" | grep "has taken" | grep -cvE "\(Unavailable\)")
ACTIVE=${ACTIVE:-0}

JSON="{\"token\":\"$TOKEN\",\"queue\":\"$QUEUE_NAME\",\"waiting\":$WAITING,\"talking\":$TALKING,\"active_members\":$ACTIVE,\"total_members\":$TOTAL}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$HELPDESK_URL" -H "Content-Type: application/json" -d "$JSON")

echo "$(date +%H:%M:%S) HTTP:$HTTP_CODE W:$WAITING T:$TALKING A:$ACTIVE/$TOTAL"
