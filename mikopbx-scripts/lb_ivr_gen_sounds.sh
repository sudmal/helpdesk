#!/bin/sh
# Генерация IVR-звуков для LanBilling IVR через Google TTS
# Запускать однократно с MikoPBX

OUTDIR="/offload/asterisk/sounds/custom-ivr"
mkdir -p "$OUTDIR"

tts() {
    local id="$1"
    local text="$2"
    local enc=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$text'))" 2>/dev/null || \
                python  -c "import urllib, sys; print urllib.quote(sys.argv[1])" "$text" 2>/dev/null || \
                echo "$text" | sed 's/ /+/g')
    curl -s -A "Mozilla/5.0 (X11; Linux x86_64)" \
        "https://translate.google.com/translate_tts?ie=UTF-8&q=${enc}&tl=ru&client=tw-ob" \
        -o "/tmp/tts_${id}.mp3" && \
    sox "/tmp/tts_${id}.mp3" -r 8000 -c 1 "$OUTDIR/${id}.gsm" 2>/dev/null && \
    sox "/tmp/tts_${id}.mp3" -r 8000 -c 1 "$OUTDIR/${id}.wav" 2>/dev/null && \
    echo "OK: $id" || echo "FAIL: $id"
    sleep 0.5
}

tts "ivr_lb_welcome"       "Информация по вашему лицевому счёту."
tts "ivr_lb_status_active" "Статус вашего счёта — активен."
tts "ivr_lb_status_blocked" "Ваш счёт заблокирован по финансовым причинам."
tts "ivr_lb_balance"       "Баланс вашего счёта составляет"
tts "ivr_lb_rublei"        "рублей."
tts "ivr_lb_promise_offer" "Для подключения обещанного платежа нажмите один."
tts "ivr_lb_promise_done"  "Обещанный платёж успешно подключён. Доступ к сети будет восстановлен в течение нескольких минут."
tts "ivr_lb_promise_na"    "Услуга обещанного платежа для вашего счёта в данный момент недоступна."
tts "ivr_lb_not_found"     "Ваш номер не найден в нашей системе. Обратитесь в службу поддержки."
tts "ivr_lb_error"         "Произошла ошибка при обработке запроса. Обратитесь в службу поддержки."
tts "ivr_lb_bye"           "Спасибо за обращение. До свидания."
tts "ivr_lb_minus"         "минус"

echo "Готово. Файлы в $OUTDIR:"
ls "$OUTDIR"
