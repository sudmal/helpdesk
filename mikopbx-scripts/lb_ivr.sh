** WARNING: connection is not using a post-quantum key exchange algorithm.
** This session may be vulnerable to "store now, decrypt later" attacks.
** The server may need to be upgraded. See https://openssh.com/pq.html
#!/bin/bash
# LanBilling IVR v7: поиск абонента -> меню + журнал действий

LB_URL='http://193.233.140.18:34012'
LB_LOGIN='<логин LanBilling>'
LB_PASS='<пароль LanBilling>'
SND='/storage/usbdisk1/mikopbx/media/custom'
RU_DIGITS='/offload/asterisk/sounds/ru-ru/digits'
EXTRA_DIGITS='/storage/usbdisk1/mikopbx/media/custom/num'
HELPDESK_IVR_URL='https://vega8.ru/pbx/ivr-log'
HELPDESK_TOKEN='<PBX_TOKEN из .env HelpDesk>'
COOKIE=$(mktemp /tmp/lb_ivr_ck.XXXXXX)
LAST_RESULT=0

say_balance() {
    local n=$1
    if [ -z "$n" ] || [ "$n" = "0" ]; then play "$RU_DIGITS/0"; return; fi

    local th=$(( n / 1000 ))
    local rem=$(( n % 1000 ))
    local hun=$(( rem / 100 ))
    local sub=$(( rem % 100 ))

    # Тысячи
    if [ "$th" -gt 0 ]; then
        if   [ "$th" = "1" ]; then
            play "${EXTRA_DIGITS}_1f"; play "$RU_DIGITS/thousand"
        elif [ "$th" = "2" ]; then
            play "${EXTRA_DIGITS}_2ff"; play "${EXTRA_DIGITS}_thousand2"
        elif [ "$th" = "3" ] || [ "$th" = "4" ]; then
            play "$RU_DIGITS/$th"; play "${EXTRA_DIGITS}_thousand2"
        else
            play "$RU_DIGITS/$th"; play "${EXTRA_DIGITS}_thousand5"
        fi
    fi

    # Сотни
    if [ "$hun" -gt 0 ]; then
        if [ "$hun" = "1" ]; then
            play "$RU_DIGITS/hundred"
        else
            play "${EXTRA_DIGITS}_${hun}00"
        fi
    fi

    # Десятки и единицы
    if [ "$sub" -ge 10 ] && [ "$sub" -le 19 ]; then
        play "$RU_DIGITS/$sub"
    elif [ "$sub" -ge 20 ]; then
        play "$RU_DIGITS/$(( sub / 10 * 10 ))"
        local u=$(( sub % 10 ))
        [ "$u" -gt 0 ] && play "$RU_DIGITS/$u"
    elif [ "$sub" -gt 0 ]; then
        play "$RU_DIGITS/$sub"
    fi
}

cleanup() { rm -f "$COOKIE"; }
trap cleanup EXIT

agi_do() {
    echo "$*"
    local resp
    IFS= read -r resp
    LAST_RESULT="${resp#*result=}"
    LAST_RESULT="${LAST_RESULT%% *}"
}
play()       { agi_do "STREAM FILE $1 0123456789#*"; }
get_digit()  { agi_do "GET DATA $1 ${2:-7000} 1"; }
say_num()    { agi_do "SAY NUMBER $1 ''"; }
agi_hangup() { agi_do "HANGUP"; }

goto_queue() {
    local action="${1:-transfer_to_support}"
    log_action "$action"
    agi_do "SET CONTEXT internal"
    agi_do "SET EXTENSION 2002"
    agi_do "SET PRIORITY 1"
    exit 0
}

soap() {
    local env="<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Header/><soap:Body>$1</soap:Body></soap:Envelope>"
    curl -s --connect-timeout 3 --max-time 8 \
         -b "$COOKIE" -c "$COOKIE" \
         -H 'Content-Type:text/xml;charset=UTF-8' \
         -d "$env" "$LB_URL" 2>/dev/null || true
}

xval() {
    echo "$1" | grep -o "<${2}>[^<]*</${2}>" | sed "s/<${2}>//;s/<\/${2}>//" | head -1
}

xval_from_blocked() {
    local xml="$1" tag="$2"
    python3 -c "
import sys, re
xml = open('/dev/stdin').read()
rets = re.findall(r'<ret>(.*?)</ret>', xml, re.S)
result = ''
first  = ''
for r in rets:
    m = re.search(r'<$tag>([^<]*)</$tag>', r)
    b = re.search(r'<blocked>([^<]*)</blocked>', r)
    if m and not first:
        first = m.group(1)
    if m and b and b.group(1) == '4':
        result = m.group(1)
        break
print(result or first)
" <<< "$xml"
}

log_action() {
    local action="$1" details="${2:-}"
    [ -z "$action" ] && return
    local bal_val="${BALANCE:-}"
    [ -z "$bal_val" ] && bal_val="null"
    curl -s --max-time 5 -X POST "$HELPDESK_IVR_URL" \
        -H "Authorization: Bearer ${HELPDESK_TOKEN}" \
        -H "Content-Type: application/json" \
        -d "{\"call_id\":\"${CALL_ID}\",\"phone\":\"${PHONE}\",\"name\":\"${USERNAME}\",\"agrmnum\":\"${AGRMNUM}\",\"address\":\"${ADDRESS}\",\"balance\":${bal_val},\"blocked\":${BLOCKED:-0},\"action\":\"${action}\",\"details\":\"${details}\"}" \
        >/dev/null 2>&1 &
    disown
}

# -- Читаем заголовки AGI
CALLERID=''
CALL_ID=''
while IFS= read -r line && [ -n "$line" ]; do
    case "${line%%: *}" in
        agi_callerid)  CALLERID="${line#*: }" ;;
        agi_uniqueid)  CALL_ID="${line#*: }" ;;
    esac
done

PHONE=$(echo "$CALLERID" | tr -d '+ ()-')
[ "${#PHONE}" -eq 10 ] && PHONE="7${PHONE}"
[ "${#PHONE}" -eq 11 ] && [ "${PHONE:0:1}" = "8" ] && PHONE="7${PHONE:1}"

USERNAME=''
AGRMNUM=''
ADDRESS=''
BLOCKED=0
BALANCE=''

agi_do "ANSWER"
sleep 0.5

# -- Логин
LB_RESP=$(soap "<urn:Login><login>${LB_LOGIN}</login><pass>${LB_PASS}</pass></urn:Login>")
if [ -z "$LB_RESP" ]; then
    log_action "api_error" "login failed"
    play "$SND/ivr_lb_error"; goto_queue "transfer_to_support"
fi

# -- Поиск абонента
AGRMID=''; BLOCKED=0; BALANCE=''
if [ "${#PHONE}" -ge 10 ]; then
    LB_RESP=$(soap "<getAccounts xmlns=\"urn:api3\"><flt><phone xsi:type=\"xsd:string\">${PHONE}</phone></flt></getAccounts>")
    ACC_UID=$(xval "$LB_RESP" 'uid')
    USERNAME=$(xval "$LB_RESP" 'name')
    if [ -n "$ACC_UID" ] && [ "$ACC_UID" != "0" ]; then
        LB_RESP=$(soap "<getVgroups xmlns=\"urn:api3\"><flt><userid xsi:type=\"xsd:int\">${ACC_UID}</userid></flt></getVgroups>")
        AGRMID=$(xval_from_blocked "$LB_RESP" 'agrmid')
        BLOCKED=$(xval_from_blocked "$LB_RESP" 'blocked')
        BALANCE=$(xval_from_blocked "$LB_RESP" 'balance')
        AGRMNUM=$(xval_from_blocked "$LB_RESP" 'agrmnum')
        ADDRESS=$(python3 -c "
import sys, re
xml = open('/dev/stdin').read()
rets = re.findall(r'<ret>(.*?)</ret>', xml, re.S)
first = ''
for r in rets:
    m = re.search(r'<address>([^<]*,[^<]*)</address>', r, re.S)
    if m:
        parts = m.group(1).split(',')
        addr = ' '.join(p.strip() for p in parts[4:8] if p.strip())
        if not first:
            first = addr
        b = re.search(r'<blocked>([^<]*)</blocked>', r)
        if b and b.group(1) == '4':
            print(addr)
            exit()
print(first)
" <<< "$LB_RESP")
    fi
fi

# Не найден -- сразу в очередь ТП
if [ -z "$AGRMID" ]; then
    goto_queue "not_found"
fi

# Баланс: целая часть
BAL_INT=$(echo "${BALANCE:-0}" | grep -oE '^-?[0-9]+' | head -1)
BAL_INT="${BAL_INT:-0}"
if [ "${BAL_INT:0:1}" = "-" ]; then
    BAL_MINUS=1; BAL_ABS="${BAL_INT:1}"
else
    BAL_MINUS=0; BAL_ABS="$BAL_INT"
fi
BAL_ABS="${BAL_ABS:-0}"

# -- Настройки обещанного платежа (однократно)
PP_AVAIL=0; PP_MIN=''; PP_MAX=''; SUMM=''; PP_DONE=0
if [ "${BLOCKED:-0}" = "4" ]; then
    LB_RESP=$(soap "<getPPSettings xmlns=\"urn:api3\"><agrm>${AGRMID}</agrm></getPPSettings>")
    PP_AVAIL=$(xval "$LB_RESP" 'promiseavailable')
    PP_MIN=$(xval   "$LB_RESP" 'promisemin')
    PP_MAX=$(xval   "$LB_RESP" 'promisemax')
    if [ "${PP_AVAIL:-0}" = "1" ]; then
        LB_RESP=$(soap "<getRecommendedPayment xmlns=\"urn:api3\"><id>${AGRMID}</id></getRecommendedPayment>")
        REC=$(xval "$LB_RESP" 'ret')
        SUMM=$(awk -v rec="${REC:-0}" -v mx="${PP_MAX:-0}" -v bal="${BALANCE:-0}" '
            BEGIN {
                s = (rec+0 > 0) ? rec : 1000
                # Если баланс отрицательный — добавляем долг, чтобы счёт вышел в плюс
                if (bal+0 < 0) s = s + (-bal+0)
                # Округляем вверх до целых рублей
                s = int(s) + (s > int(s) ? 1 : 0)
                if (mx > 0 && s > mx) s = mx
                printf "%.2f", s
            }')
    fi
fi

# -- Главное меню
MENU_TRIES=0
while true; do
    get_digit "$SND/ivr_lb_main_menu"
    case "$LAST_RESULT" in
        1)
            MENU_TRIES=0
            log_action "balance_check"
            if [ "${BLOCKED:-0}" = "4" ]; then
                play "$SND/ivr_lb_status_blocked"
            else
                play "$SND/ivr_lb_status_active"
            fi
            play "$SND/ivr_lb_balance"
            [ "$BAL_MINUS" = "1" ] && play "$RU_DIGITS/minus"
            say_balance "$BAL_ABS"
            play "$SND/ivr_lb_rublei"

            sleep 0.3

            if [ "${BLOCKED:-0}" = "4" ]; then
                if [ "$PP_DONE" = "1" ]; then
                    :
                elif [ "${PP_AVAIL:-0}" = "1" ] && [ -n "$SUMM" ]; then
                    log_action "pp_offered" "${SUMM}"
                    get_digit "$SND/ivr_lb_promise_offer"
                    if [ "$LAST_RESULT" = "1" ]; then
                        LB_RESP=$(soap "<PromisePayment xmlns=\"urn:api3\"><agrm>${AGRMID}</agrm><summ>${SUMM}</summ></PromisePayment>")
                        if [ -z "$LB_RESP" ] || echo "$LB_RESP" | grep -q Fault; then
                            log_action "api_error" "PromisePayment failed"
                            play "$SND/ivr_lb_error"
                        else
                            log_action "pp_activated" "${SUMM}"
                            play "$SND/ivr_lb_promise_done"
                            PP_DONE=1
                        fi
                    else
                        log_action "pp_declined"
                    fi
                else
                    play "$SND/ivr_lb_promise_na"
                fi
            fi
            ;;
        2)
            goto_queue "transfer_to_support"
            ;;
        *)
            MENU_TRIES=$((MENU_TRIES + 1))
            if [ "$MENU_TRIES" -ge 2 ]; then
                goto_queue "transfer_to_support"
            fi
            # иначе: цикл продолжается, меню повторится
            ;;
    esac
done
