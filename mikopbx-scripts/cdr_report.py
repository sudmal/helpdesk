
import subprocess
import json
import requests
from datetime import datetime
import os
import sys

# --- КОНФИГУРАЦИЯ ---
DB_PATH = "/storage/usbdisk1/mikopbx/astlogs/asterisk/cdr.db"

DEPARTMENT_CONFIG = {
    "Техподдержка": {
        "dst_nums": [102, 105, 106, 110, 112]
    },
    "Абонотдел": {
        "dst_nums": [200, 201, 235]
    }
}

# Telegram настройки
TELEGRAM_BOT_TOKEN = "REPLACE_WITH_TELEGRAM_BOT_TOKEN"
TELEGRAM_CHAT_IDS = ["436020968"]

MIN_DURATION = 10 


REPORT_START_HOUR = "08"

# --- Вспомогательные функции ---

def _get_start_datetime_str():
    """Возвращает строку с текущей датой и указанным временем начала отчета."""
    today = datetime.now().strftime("%Y-%m-%d")
    return f"{today} {REPORT_START_HOUR}"

def run_sql_query(db_path, dst_nums, start_time, min_duration):
    """
    Выполняет SQL запрос к SQLite базе данных и возвращает результат в виде списка словарей.
    """
    dst_nums_str = ','.join(map(str, dst_nums))
    
    query = (
        f"select start,answer,src_num,dst_num,disposition,duration "
        f"from cdr_general where start>'{start_time}' "
        f"and duration > {min_duration} "
        f"and dst_num in ({dst_nums_str});"
    )

    try:
        command = ['sqlite3', '-separator', '|', db_path, query]
        
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            check=True, # Поднимет CalledProcessError если команда вернет ненулевой код
            encoding='utf-8' # Убедимся, что вывод обрабатывается как UTF-8
        )
        
        lines = result.stdout.strip().split('\n')
        if not lines:
            return []

        # Парсим вывод sqlite3
        # Ожидаем, что вывод будет примерно таким:
        # +-------------------------+...
        # |          start          |...
        # +-------------------------+...
        # | 2025-12-29 08:42:49.584 |...
        
        data_rows = []
        column_names = []
        header_parsed = False

        for line in lines:
            line = line.strip()
            if not line:
                continue
            
            # Пропускаем строки с разделителями (+-+)
            if line.startswith('+') and '|' in line:
                continue

            # Первая строка с данными (после разделителя заголовков) - это и есть заголовки
            if not header_parsed:
                # Очищаем заголовки, удаляем пустые части
                column_names = [name.strip() for name in line.split('|') if name.strip()]
                if column_names:
                    header_parsed = True
                continue

            # Строки с данными
            parts = [p.strip() for p in line.split('|') if p.strip()]
            if parts and len(parts) == len(column_names):
                data_rows.append(dict(zip(column_names, parts)))
        
        return data_rows

    except subprocess.CalledProcessError as e:
        print(f"Ошибка выполнения SQL запроса: {e}", file=sys.stderr)
        print(f"STDOUT: {e.stdout}", file=sys.stderr)
        print(f"STDERR: {e.stderr}", file=sys.stderr)
        return []
    except FileNotFoundError:
        print(f"Ошибка: Команда 'sqlite3' не найдена или база данных по пути '{db_path}' не существует.", file=sys.stderr)
        return []
    except Exception as e:
        print(f"Неизвестная ошибка при выполнении SQL: {e}", file=sys.stderr)
        return []


def analyze_calls_for_department(department_name, dst_nums, start_time, min_duration):
    """
    Анализирует данные о звонках для указанного отдела.
    Возвращает словарь с общей статистикой.
    """
    print(f"Получение данных для {department_name} (номера: {dst_nums})...")
    calls = run_sql_query(DB_PATH, dst_nums, start_time, min_duration)

    total_calls = len(calls)
    answered_calls = 0
    unique_unanswered_src_nums = set()

    for call in calls:
        if call.get('disposition') == 'ANSWERED':
            answered_calls += 1
        elif call.get('disposition') == 'NOANSWER':
            unique_unanswered_src_nums.add(call.get('src_num'))
    
    unanswered_calls = total_calls - answered_calls # Просто количество неотвеченных
    unique_unanswered_count = len(unique_unanswered_src_nums) # Уникальные src_num для NOANSWER
    
    print(f"Для {department_name}: Всего звонков: {total_calls}, Отвечено: {answered_calls}, Неотвечено: {unanswered_calls} (уник. src: {unique_unanswered_count})")

    return {
        "department_name": department_name,
        "total_calls": total_calls,
        "answered_calls": answered_calls,
        "unanswered_calls": unanswered_calls, # Общее количество неотвеченных
        "unique_unanswered_src": unique_unanswered_count # Уникальные src_num для неотвеченных
    }

def generate_report_message(department_stats_list):
    """
    Генерирует форматированное сообщение для Telegram.
    """
    today_date = datetime.now().strftime("%d.%m.%Y")
    report_start_time = f"{REPORT_START_HOUR}:00"

    message = f"📊 *Ежедневный отчёт по звонкам* (с {report_start_time} {today_date})\n\n"
    message += f"Минимальная длительность звонка для отчета: *{MIN_DURATION} секунд*\n\n"

    for stats in department_stats_list:
        message += (
            f"*{stats['department_name']}* (номера: {', '.join(map(str, DEPARTMENT_CONFIG[stats['department_name']]['dst_nums']))}):\n"
            f"  📞 Всего звонков: `{stats['total_calls']}`\n"
            f"  ✅ Отвечено: `{stats['answered_calls']}`\n"
            f"  ❌ Неотвечено: `{stats['unanswered_calls']}`\n"
            f"  👤 Уникальных неотвеченных caller ID: `{stats['unique_unanswered_src']}`\n\n"
        )
    
    message += "--- Конец отчета ---"
    return message

def send_telegram_message(message, bot_token, chat_ids):
    """
    Отправляет сообщение в Telegram.
    """
    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    headers = {"Content-Type": "application/json"}
    
    for chat_id in chat_ids:
        payload = {
            "chat_id": chat_id,
            "text": message,
            "parse_mode": "Markdown" # Используем Markdown для форматирования
        }
        try:
            response = requests.post(url, headers=headers, json=payload)
            response.raise_for_status() # Вызовет исключение для ошибок HTTP (4xx или 5xx)
            print(f"Сообщение успешно отправлено в чат {chat_id}.")
        except requests.exceptions.RequestException as e:
            print(f"Ошибка отправки сообщения в Telegram (чат {chat_id}): {e}", file=sys.stderr)
            if response is not None:
                print(f"Ответ Telegram API: {response.text}", file=sys.stderr)

# --- Основная логика ---

if __name__ == "__main__":
    start_time_filter = _get_start_datetime_str()
    print(f"Запуск отчета за звонки с {start_time_filter}...")

    all_department_stats = []
    
    for dept_name, config in DEPARTMENT_CONFIG.items():
        stats = analyze_calls_for_department(
            dept_name, 
            config["dst_nums"], 
            start_time_filter, 
            MIN_DURATION
        )
        all_department_stats.append(stats)

    report_message = generate_report_message(all_department_stats)
    print("\n--- Сформирован отчет ---")
    print(report_message)
    print("--------------------------")

    if TELEGRAM_BOT_TOKEN == "REPLACE_WITH_TELEGRAM_BOT_TOKEN" or not TELEGRAM_CHAT_IDS or "436020968" in TELEGRAM_CHAT_IDS:
        print("\nВнимание: Не настроены Telegram BOT_TOKEN или CHAT_IDS. Сообщение не будет отправлено в Telegram.", file=sys.stderr)
        print("Пожалуйста, обновите переменные TELEGRAM_BOT_TOKEN и TELEGRAM_CHAT_IDS в скрипте.", file=sys.stderr)
    else:
        send_telegram_message(report_message, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_IDS)

