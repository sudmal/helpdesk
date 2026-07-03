#!/usr/bin/env python3
# Детектор нативного DND (кнопка в MicroSIP, не *78) по факту SIP 486 Do Not Disturb.
# Обычный DIALSTATUS теряет эту фразу, поэтому читаем сырой pjsip-лог.
# Ловит только звонки к членам очереди техподдержки (сверяется с queue show).
#
# POST делается через curl (subprocess), НЕ через urllib.request -- urllib
# на этой урезанной сборке Python/MikoPBX рвёт HTTPS-соединение с
# RemoteDisconnected без единой ошибки в логе, из-за чего события молча
# терялись (обнаружено 2026-07-03).
#
# Call-ID у INVITE ищем СТРОГО в границах одного SIP-сообщения (до первой
# пустой строки) -- лог многопоточный, строки от разных одновременных
# сообщений перемежаются, "первый попавшийся Call-ID после INVITE" без
# границы блока иногда привязывал 486 не к тому добавочному (обнаружено
# 2026-07-03: ложное срабатывание на офлайн-номере 102).

import re, subprocess, os, sys

LOG = '/storage/usbdisk1/mikopbx/log/asterisk/verbose'
STATE = '/storage/usbdisk1/mikopbx/dnd_486_watcher.state'
LOCK = '/storage/usbdisk1/mikopbx/dnd_486_watcher.lock'
QUEUE_NAME = 'QUEUE-F38325E796B3FFB8938BA383AA119148'
HELPDESK_URL = 'https://vega8.ru/pbx/dnd-log'
HELPDESK_TOKEN = '<PBX_TOKEN из .env HelpDesk>'
TAIL_BYTES = 500_000


def find_callid_in_block(lines, start_idx):
    """Ищет Call-ID в пределах одного SIP-сообщения, начиная с start_idx,
    до первой пустой строки или начала нового лог-блока. Возвращает None,
    если Call-ID не найден в границах блока (не привязываем к чужому)."""
    for j in range(start_idx, min(start_idx + 20, len(lines))):
        line = lines[j]
        if line.strip() == '' or re.match(r'^\[[\d-]+ [\d:]+\]', line):
            return None
        m = re.match(r'^Call-ID:\s*(\S+)', line)
        if m:
            return m.group(1)
    return None


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
    members = set(re.findall(r'^\s*(\d+)\s+\(Local/', raw, re.M))

    callid_ext = {}
    for i, line in enumerate(lines):
        m = re.match(r'^INVITE sip:(\d+)@', line)
        if not m:
            continue
        callid = find_callid_in_block(lines, i)
        if callid:
            callid_ext[callid] = m.group(1)

    new_max_ts = last_ts
    events = []
    cur_ts = None
    for i, line in enumerate(lines):
        m = re.match(r'^\[([\d-]+ [\d:]+)\].*Received SIP response', line)
        if m:
            cur_ts = m.group(1)
            continue
        if line.startswith('SIP/2.0 486') and cur_ts:
            callid = find_callid_in_block(lines, i)
            if callid:
                ext = callid_ext.get(callid)
                if ext and ext in members and cur_ts > last_ts:
                    events.append((cur_ts, ext))
                    if cur_ts > new_max_ts:
                        new_max_ts = cur_ts
            cur_ts = None

    for ts, ext in events:
        payload = '{"extension":"%s","state":"missed_dnd"}' % ext
        subprocess.run([
            'curl', '-s', '--max-time', '5', '-X', 'POST', HELPDESK_URL,
            '-H', 'Authorization: Bearer ' + HELPDESK_TOKEN,
            '-H', 'Content-Type: application/json',
            '-d', payload,
        ], capture_output=True, timeout=8)

    if new_max_ts != last_ts:
        with open(STATE, 'w') as f:
            f.write(new_max_ts)
finally:
    os.rmdir(LOCK)
