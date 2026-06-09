#!/bin/sh
# Monitoring Asterisk queue -> HelpDesk
# Cron: * * * * * /storage/usbdisk1/mikopbx/queue_monitor.sh >> /var/log/queue_monitor.log 2>&1

QUEUE_NAME="QUEUE-F38325E796B3FFB8938BA383AA119148"
HELPDESK_URL="https://helpdesk.browsersdnr.ru/pbx/queue-status"
TOKEN="REDACTED"

OUTPUT=$(asterisk -rx "queue show $QUEUE_NAME" 2>/dev/null)

if [ -z "$OUTPUT" ]; then
  echo "$(date): ERROR - no output from asterisk" >&2
  exit 1
fi

WAITING=$(echo "$OUTPUT" | sed -n "s/.*has \([0-9]*\) call.*/\1/p" | head -1)
WAITING=${WAITING:-0}

TOTAL=$(echo "$OUTPUT" | grep -cE "\(dynamic\)|\(static\)")
TOTAL=${TOTAL:-0}

TALKING=$(echo "$OUTPUT" | grep -cE "\(In use\)|\(Ringing\)|\(Busy\)")
TALKING=${TALKING:-0}

ACTIVE=$(echo "$OUTPUT" | grep -E "\(dynamic\)|\(static\)" | grep -cvE "\(Paused\)|\(Unavailable\)")
ACTIVE=${ACTIVE:-0}

JSON="{\"token\":\"$TOKEN\",\"queue\":\"$QUEUE_NAME\",\"waiting\":$WAITING,\"talking\":$TALKING,\"active_members\":$ACTIVE,\"total_members\":$TOTAL}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$HELPDESK_URL" -H "Content-Type: application/json" -d "$JSON")

echo "$(date +%H:%M:%S) HTTP:$HTTP_CODE W:$WAITING T:$TALKING A:$ACTIVE/$TOTAL"
