#!/bin/bash
# --- КОНФИГУРАЦИЯ ---
DB_PATH="/storage/usbdisk1/mikopbx/astlogs/asterisk/cdr.db"

# Telegram настройки
TELEGRAM_BOT_TOKEN="REPLACE_WITH_TELEGRAM_BOT_TOKEN"
TELEGRAM_CHAT_IDS="-1001816111133"


#TELEGRAM_CHAT_IDS="436020968"
# Минимальная длительность звонка
MIN_DURATION=1

# Период отчета (часы)
REPORT_START_HOUR="08"
REPORT_END_HOUR="21"

# Параметры повторных попыток при блокировке БД
MAX_DB_RETRIES=5
DB_RETRY_DELAY=2

# Конфигурация отделов
DEPARTMENT_CONFIG=(
    "Техподдержка:102 105 106 110 112"
    "Абонотдел:200 201 235"
)

# --- ПРОВЕРКИ ---
command -v sqlite3 >/dev/null || { echo "Ошибка: sqlite3 не найден." >&2; exit 1; }
command -v curl >/dev/null || { echo "Ошибка: curl не найден." >&2; exit 1; }
[ -f "$DB_PATH" ] || { echo "Ошибка: База $DB_PATH не найдена." >&2; exit 1; }

# --- ФУНКЦИИ ---

# Функция выполнения SQL запроса с обработкой блокировки
run_query() {
    local query="$1"
    local attempt=0
    local result=""
    
    while [ $attempt -lt $MAX_DB_RETRIES ]; do
        if result=$(sqlite3 -separator '|' "$DB_PATH" "$query" 2>/tmp/sqlite_err); then
            echo "$result"
            return 0
        else
            if grep -q "database is locked" /tmp/sqlite_err; then
                sleep $DB_RETRY_DELAY
                ((attempt++))
            else
                cat /tmp/sqlite_err >&2
                return 1
            fi
        fi
    done
    return 1
}

send_telegram() {
    local message="$1"
    local chat_id="$2"
    # Экранирование для JSON (минимальное для Markdown)
    local escaped_msg=$(echo "$message" | sed 's/"/\\"/g')
    
    curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
        -H "Content-Type: application/json" \
        -d "{\"chat_id\": \"$chat_id\", \"text\": \"$escaped_msg\", \"parse_mode\": \"Markdown\"}" > /dev/null
}

# --- ЛОГИКА ---

set -euo pipefail

current_date=$(date +"%Y-%m-%d")
display_date=$(date +"%d.%m.%Y")
start_filter="${current_date} ${REPORT_START_HOUR}:00:00"
end_filter="${current_date} ${REPORT_END_HOUR}:59:59"

REPORT_MESSAGE="📊 *Отчёт по звонкам за ${display_date}*\n"
REPORT_MESSAGE+="Период: ${REPORT_START_HOUR}:00 - ${REPORT_END_HOUR}:59\n"
REPORT_MESSAGE+="Мин. длительность: ${MIN_DURATION} сек.\n\n"

for dept_entry in "${DEPARTMENT_CONFIG[@]}"; do
    IFS=':' read -r dept_name dst_nums_str <<< "$dept_entry"
    dst_nums_sql=$(echo "$dst_nums_str" | tr ' ' ',')
    
    # 1. Общая статистика
    query_total="SELECT
        COUNT(*), 
        SUM(CASE WHEN disposition='ANSWERED' THEN 1 ELSE 0 END),
        SUM(CASE WHEN disposition='NOANSWER' THEN 1 ELSE 0 END),
        COUNT(DISTINCT CASE WHEN disposition='NOANSWER' THEN src_num END)
        FROM cdr_general 
        WHERE start BETWEEN '$start_filter' AND '$end_filter' 
        AND duration > $MIN_DURATION 
        AND dst_num IN ($dst_nums_sql);"

    stats=$(run_query "$query_total")
    
    total=$(echo "$stats" | cut -d'|' -f1)
    answered=$(echo "$stats" | cut -d'|' -f2)
    noanswer=$(echo "$stats" | cut -d'|' -f3)
    unique_missed=$(echo "$stats" | cut -d'|' -f4)

    # Если данных нет, sqlite вернет пустые поля или NULL
    total=${total:-0}; answered=${answered:-0}; noanswer=${noanswer:-0}; unique_missed=${unique_missed:-0}

    REPORT_MESSAGE+="*${dept_name}*\n"
    REPORT_MESSAGE+="\` Всего: $total | ✅ Отв: $answered | ❌ Проп: $noanswer | 👤 Уник: $unique_missed\`\n\n"

    # 2. Почасовое распределение (только для Техподдержки)
    if [[ "$dept_name" == "Техподдержка" && $noanswer -gt 0 ]]; then
        REPORT_MESSAGE+="📈 *Распределение пропущенных (ТП):*\n"
        
        query_hourly="SELECT strftime('%H', start) as hr, COUNT(*) 
            FROM cdr_general 
            WHERE start BETWEEN '$start_filter' AND '$end_filter' 
            AND disposition='NOANSWER' 
            AND dst_num IN ($dst_nums_sql) 
            AND duration > $MIN_DURATION
            GROUP BY hr ORDER BY hr;"
            
        hourly_stats=$(run_query "$query_hourly")
        
        if [ -n "$hourly_stats" ]; then
            while IFS='|' read -r hr count; do
                # Убираем ведущий ноль для красоты или оставляем
                REPORT_MESSAGE+="\`$hr:00-$hr:59 — $count зв.\`\n"
            done <<< "$hourly_stats"
            REPORT_MESSAGE+="\n"
        fi
    fi
done

# Отправка
for chat_id in $TELEGRAM_CHAT_IDS; do
    send_telegram "$REPORT_MESSAGE" "$chat_id"
done

exit 0
