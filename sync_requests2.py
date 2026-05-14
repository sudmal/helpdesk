#!/usr/bin/env python3
"""
Синхронизация заявок из старой системы (sudmalsz_sputnik) в новую (sudmalsz_hlpdesk).
Запускать каждые 5-10 минут через cron.
"""
import pymysql
import subprocess
import json
from datetime import datetime

# Подключения
old = pymysql.connect(host='localhost', user='sudmalsz_sputnik',
                      password='XCNj*hd0sCEb', database='sudmalsz_sputnik', charset='utf8mb4',
                      init_command="SET time_zone='+00:00'")
new = pymysql.connect(host='localhost', user='sudmalsz_hlpdesk',
                      password='AxdB1%8HPhiV', database='sudmalsz_hlpdesk', charset='utf8mb4',
                      init_command="SET time_zone='+03:00'")

old_cur = old.cursor(pymysql.cursors.DictCursor)
new_cur = new.cursor(pymysql.cursors.DictCursor)

# Маппинг секторов → territory_id в новой базе
SECTOR_MAP = {1: 1, 14: 14, 15: 15, 16: 16, 17: 17, 18: 18, 19: 19}  # old_sector_id → new_territory_id (IDs совпадают)

# Маппинг network → service_type_id
NETWORK_MAP = {'Интернет': 1, 'КТВ': 2, 'ВОЛС': 3}

# Территории, для которых используем API (полный пайплайн)
def get_live_territory_ids():
    new_cur.execute("SELECT id FROM territories WHERE name REGEXP 'Гвардей|Донецк'")
    return {r['id'] for r in new_cur.fetchall()}

# Маппинг type_request_id → new type_id (названия совпадают)
def get_type_map():
    new_cur.execute("SELECT id, name FROM ticket_types")
    types = {r['name']: r['id'] for r in new_cur.fetchall()}
    old_cur.execute("SELECT id, title FROM type_requests")
    return {r['id']: types.get(r['title']) for r in old_cur.fetchall()}

# Статусы новой системы
def get_statuses():
    new_cur.execute("SELECT id, slug FROM ticket_statuses")
    return {r['slug']: r['id'] for r in new_cur.fetchall()}

# Пользователи — маппинг по логину
def get_user_map():
    new_cur.execute("SELECT id, login FROM users")
    new_users = {r['login']: r['id'] for r in new_cur.fetchall()}
    old_cur.execute("SELECT id, login FROM users WHERE deleted_at IS NULL")
    return {r['id']: new_users.get(r['login']) for r in old_cur.fetchall()}

# Адреса — находим по улице+дому
def find_address(quarter_title, house_num, apartment):
    new_cur.execute("""
        SELECT id FROM addresses
        WHERE street = %s AND building = %s
        LIMIT 1
    """, (quarter_title, house_num))
    row = new_cur.fetchone()
    return row['id'] if row else None

# Маппинг территория → бригада (берём первую бригаду для территории)
def get_brigade_map():
    new_cur.execute("""
        SELECT bt.territory_id, MIN(bt.brigade_id) as brigade_id
        FROM brigade_territory bt
        GROUP BY bt.territory_id
    """)
    return {r['territory_id']: r['brigade_id'] for r in new_cur.fetchall()}

LIVE_TERRITORY_IDS = get_live_territory_ids()
type_map    = get_type_map()
brigade_map = get_brigade_map()
statuses    = get_statuses()
user_map    = get_user_map()

# Путь к Laravel и токен для artisan-команды
LARAVEL_DIR = '/home/s/sudmalsz/fsm.sputnik-tele.com'
PHP_BIN     = '/usr/local/bin/php8.2'
API_TOKEN   = 'ZXzIkL064WlBeJQSs9KbnYOAaHhpjC8V'

# Проверяем по номеру: старые заявки имеют номер вида "old-XXXXX"
new_cur.execute("SELECT number FROM tickets WHERE number LIKE 'old-%'")
synced = {r['number'] for r in new_cur.fetchall()}

# Берём новые и изменённые заявки за последние 24 часа
old_cur.execute("""
    SELECT r.*,
           q.title as quarter_title,
           h.numHouse as house_num,
           s.title as sector_title
    FROM requests r
    JOIN quarters q ON q.id = r.quarter_id
    JOIN houses h ON h.id = r.house_id
    JOIN sectors s ON s.id = r.sector_id
    WHERE r.deleted_at IS NULL
      AND (r.created_at >= NOW() - INTERVAL 1 DAY
           OR r.updated_at >= NOW() - INTERVAL 1 DAY)
    ORDER BY r.id
""")

requests = old_cur.fetchall()
print(f"Заявок для синхронизации: {len(requests)}")

created = 0
updated = 0
skipped = 0
api_created = 0
api_skipped = 0

for r in requests:
    old_number = f"old-{r['id']}"
    territory_id = SECTOR_MAP.get(r['sector_id'])
    is_live = territory_id in LIVE_TERRITORY_IDS

    # Адрес
    address_id = find_address(r['quarter_title'], r['house_num'], r['apartment_number'])
    if not address_id:
        skipped += 1
        continue

    # === Живая территория: создание через API ===
    if is_live:
        if old_number in synced:
            # Для живых территорий не синхронизируем обновления/закрытия
            continue
        if r['is_canceled']:
            # Отменённые не создаём
            continue

        type_id = type_map.get(r['type_request_id'])
        service_type_id = NETWORK_MAP.get(r['network'], 1)
        creator_id = user_map.get(r['creator_id']) or 1

        exec_date = str(r['execution_date']) if r['execution_date'] else str(r['created_at'])[:10]

        payload = {
            'old_id':          r['id'],
            'address_id':      address_id,
            'apartment':       r['apartment_number'] or '',
            'type_id':         type_id,
            'service_type_id': service_type_id,
            'phone':           r['phone_number'] or '',
            'description':     r['description'] or '',
            'execution_date':  exec_date,
            'creator_id':      creator_id,
        }
        try:
            cmd_data = {**payload, 'token': API_TOKEN}
            result = subprocess.run(
                [PHP_BIN, 'artisan', 'sync:ticket', json.dumps(cmd_data)],
                cwd=LARAVEL_DIR, capture_output=True, text=True, timeout=30
            )
            out = result.stdout.strip()
            resp_data = json.loads(out.split('\n')[-1]) if out else {}
            status = resp_data.get('status')
            if status == 'created':
                synced.add(old_number)
                api_created += 1
            elif status == 'already_synced':
                synced.add(old_number)
            else:
                print(f"  CLI error for {old_number}: {resp_data} | stderr: {result.stderr[:200]}")
                api_skipped += 1
        except Exception as e:
            print(f"  CLI exception for {old_number}: {e}")
            api_skipped += 1
        continue

    # === Обычная территория: прямая запись в БД ===

    # Определяем статус
    if r['is_canceled']:
        status_slug = 'cancelled'
    elif r['act_number'] or r['executor_comment']:
        status_slug = 'closed'
    elif r['is_postponed']:
        status_slug = 'postponed'
    else:
        status_slug = 'new'

    status_id = statuses.get(status_slug, statuses.get('new'))

    # Время выезда
    exec_time = '09:00:00' if r['time_period'] == 'AM' else '13:00:00'
    if r['execution_time']:
        exec_time = r['execution_time'] + ':00'
    scheduled_at = f"{r['execution_date']} {exec_time}"

    creator_id  = user_map.get(r['creator_id']) or 1
    type_id     = type_map.get(r['type_request_id'])
    service_type_id = NETWORK_MAP.get(r['network'], 1)
    brigade_id   = brigade_map.get(territory_id)

    if old_number in synced:
        # Обновляем только если изменился статус или комментарий
        new_cur.execute("SELECT status_id, act_number, close_notes FROM tickets WHERE number = %s", (old_number,))
        existing = new_cur.fetchone()
        if existing and (
            existing['status_id'] != status_id or
            existing['act_number'] != r['act_number'] or
            existing['close_notes'] != r['executor_comment']
        ):
            new_cur.execute("""
                UPDATE tickets SET
                    status_id    = %s,
                    act_number   = %s,
                    close_notes  = %s,
                    closed_at    = %s,
                    updated_at   = NOW()
                WHERE number = %s
            """, (
                status_id,
                r['act_number'],
                r['executor_comment'],
                r['updated_at'] if status_slug == 'closed' else None,
                old_number
            ))
            updated += 1
    else:
        # Создаём новую заявку
        new_cur.execute("""
            INSERT INTO tickets
              (number, address_id, apartment, type_id, service_type_id, status_id,
               phone, description, act_number, close_notes, scheduled_at,
               created_by, closed_at, created_at, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
        """, (
            old_number,
            address_id,
            r['apartment_number'],
            type_id,
            service_type_id,
            status_id,
            r['phone_number'],
            r['description'] or '',
            r['act_number'],
            r['executor_comment'],
            scheduled_at,
            creator_id,
            r['updated_at'] if status_slug == 'closed' else None,
            r['created_at'],
            r['updated_at'],
        ))
        synced.add(old_number)
        created += 1

new.commit()
print(f"{datetime.now()}: Создано (SQL): {created} | Обновлено: {updated} | API: {api_created} | Пропущено: {skipped+api_skipped}")

old_cur.close(); new_cur.close()
old.close(); new.close()