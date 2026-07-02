** WARNING: connection is not using a post-quantum key exchange algorithm.
** This session may be vulnerable to "store now, decrypt later" attacks.
** The server may need to be upgraded. See https://openssh.com/pq.html
#!/usr/bin/env python3
# Детектор нативного DND (кнопка в MicroSIP, не *78) по факту SIP 486 Do Not Disturb.
# Обычный DIALSTATUS теряет эту фразу, поэтому читаем сырой pjsip-лог.
# Ловит только звонки к членам очереди техподдержки (сверяется с queue show).

import re, subprocess, os, sys, urllib.request, json

LOG = '/storage/usbdisk1/mikopbx/log/asterisk/verbose'
STATE = '/storage/usbdisk1/mikopbx/dnd_486_watcher.state'
LOCK = '/storage/usbdisk1/mikopbx/dnd_486_watcher.lock'
QUEUE_NAME = 'QUEUE-F38325E796B3FFB8938BA383AA119148'
HELPDESK_URL = 'https://vega8.ru/pbx/dnd-log'
HELPDESK_TOKEN = '<PBX_TOKEN из .env HelpDesk>'
TAIL_BYTES = 500_000

try:
    os.mkdir(LOCK)
except FileExistsError:
    sys.exit(0)

try:
    try:
        with open(STATE) as f:
            last_ts = f.read().strip()
    except FileNotFoundError:
        last_ts = ''

    try:
        size = os.path.getsize(LOG)
    except OSError:
        sys.exit(0)

    with open(LOG, 'rb') as f:
        f.seek(max(0, size - TAIL_BYTES))
        data = f.read().decode('utf-8', errors='ignore')

    lines = data.split('\n')

    raw = subprocess.run(['asterisk', '-rx', f'queue show {QUEUE_NAME}'],
                          capture_output=True, text=True, timeout=10).stdout
    raw = re.sub(r'\x1b\[[0-9;]*m', '', raw)
    members = set(re.findall(r'^\s*(\d+)\s+\(', raw, re.M))

    callid_ext = {}
    pending_ext = None
    for line in lines:
        m = re.match(r'^INVITE sip:(\d+)@', line)
        if m:
            pending_ext = m.group(1)
            continue
        m2 = re.match(r'^Call-ID:\s*(\S+)', line)
        if m2 and pending_ext:
            callid_ext[m2.group(1)] = pending_ext
            pending_ext = None

    new_max_ts = last_ts
    events = []
    cur_ts = None
    for i, line in enumerate(lines):
        m = re.match(r'^\[([\d-]+ [\d:]+)\].*Received SIP response', line)
        if m:
            cur_ts = m.group(1)
            continue
        if line.startswith('SIP/2.0 486') and cur_ts:
            for j in range(i, min(i + 8, len(lines))):
                mc = re.match(r'^Call-ID:\s*(\S+)', lines[j])
                if mc:
                    ext = callid_ext.get(mc.group(1))
                    if ext and ext in members and cur_ts > last_ts:
                        events.append((cur_ts, ext))
                        if cur_ts > new_max_ts:
                            new_max_ts = cur_ts
                    break

    for ts, ext in events:
        payload = json.dumps({'extension': ext, 'state': 'missed_dnd'}).encode()
        req = urllib.request.Request(HELPDESK_URL, data=payload,
            headers={'Authorization': f'Bearer {HELPDESK_TOKEN}',
                     'Content-Type': 'application/json'})
        try:
            urllib.request.urlopen(req, timeout=5)
        except Exception:
            pass

    if new_max_ts != last_ts:
        with open(STATE, 'w') as f:
            f.write(new_max_ts)
finally:
    os.rmdir(LOCK)
