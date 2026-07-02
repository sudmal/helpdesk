** WARNING: connection is not using a post-quantum key exchange algorithm.
** This session may be vulnerable to "store now, decrypt later" attacks.
** The server may need to be upgraded. See https://openssh.com/pq.html
#!/bin/bash
# DND toggle -> лог в HelpDesk

HELPDESK_URL='https://vega8.ru/pbx/dnd-log'
HELPDESK_TOKEN='<PBX_TOKEN из .env HelpDesk>'

STATE="$1"

EXTENSION=''
while IFS= read -r line && [ -n "$line" ]; do
    case "${line%%: *}" in
        agi_callerid)  EXTENSION="${line#*: }" ;;
    esac
done

curl -s --max-time 5 -X POST "$HELPDESK_URL"     -H "Authorization: Bearer ${HELPDESK_TOKEN}"     -H "Content-Type: application/json"     -d "{\"extension\":\"${EXTENSION}\",\"state\":\"${STATE}\"}"     >/dev/null 2>&1
