# MikoPBX — кастомные скрипты

Резервная копия скриптов и конфигурации с сервера MikoPBX (192.168.186.186).
Дата первого копирования: 2026-06-27. Дополнено: 2026-07-02 (DND-детект).

> **Важно:** это снимок состояния, а не источник истины. `lb_ivr.sh` в этой
> папке однажды уже разошёлся с реальным сервером (была версия v3, на сервере
> давно v7) — бэкап не обновляли при доработках. Обновлять эту папку при
> каждой правке скриптов на самой MikoPBX, иначе снимок снова протухнет.

## Сервер

| Параметр | Значение |
|----------|----------|
| IP | 192.168.186.186 (локальная сеть) |
| SSH | root@192.168.186.186, ключ ~/.ssh/id_ed25519 |
| SSH-демон | Dropbear (не OpenSSH) |
| Asterisk | 20.7.0, PJSIP (не chan_sip) |
| Хранилище скриптов | /storage/usbdisk1/mikopbx/ |

> Внимание: /root/.ssh/ на MikoPBX — tmpfs, слетает при перезагрузке.
> Ключ нужно добавлять через веб-панель: Системные настройки → SSH → SSH Authorized Keys.

Корень файловой системы MikoPBX — tmpfs. Персистентны только
`/storage/usbdisk1/mikopbx/` (все скрипты, звуки) и `/cf/conf/mikopbx.db`
(настройки MikoPBX, включая `m_CustomFiles` и `m_DialplanApplications`).
`/etc/asterisk/*.conf` регенерируются из `mikopbx.db` при каждой перезагрузке —
править их напрямую бессмысленно, правки живут только до ребута.

---

## Скрипты

### queue_monitor.sh
**Расположение на MikoPBX:** /storage/usbdisk1/mikopbx/queue_monitor.sh

Мониторинг очереди Asterisk. Каждые 15 секунд отправляет состояние очереди
на хелпдеск (POST https://vega8.ru/pbx/queue-status).

Дополнительно: читает ответ сервера и выполняет команды, если хелпдеск вернул поле "cmd":
- **pjsip_reload** — перезагрузить модуль PJSIP (решает проблемы с регистрацией)
- **queue_reload** — перезагрузить очереди Asterisk
- **qualify_all** — отправить OPTIONS-пинг всем контактам

Кнопки для отправки команд — в хелпдеске: Звонки → Очередь АТС (видны только admin/head_support).

**Токен:** в скрипте, переменная HELPDESK_TOKEN — заменена на плейсхолдер
`<PBX_TOKEN из .env HelpDesk>`. Тот же токен в .env хелпдеска: `PBX_TOKEN`,
используется всеми вебхуками MikoPBX→HelpDesk (queue-status, ivr-log, dnd-log).

---

### dnd_log.sh (добавлено 2026-07-02)
**Расположение на MikoPBX:** /storage/usbdisk1/mikopbx/agi-bin/dnd_log.sh

AGI-скрипт, вызывается диалплан-приложениями `*78`/`*79` (см. `dialplan-apps/`).
Читает `agi_callerid` (номер того, кто набрал звёздочку) и аргумент `on`/`off`,
шлёт `POST https://vega8.ru/pbx/dnd-log` с `{"extension": ..., "state": "on"|"off"}`.

### dnd_486_watcher.py (добавлено 2026-07-02)
**Расположение на MikoPBX:** /storage/usbdisk1/mikopbx/agi-bin/dnd_486_watcher.py

Отдельный канал детекта DND — ловит случаи, когда оператор включает DND
**прямо в MicroSIP** (кнопкой), а не через `*78`. Presence-PUBLISH от MicroSIP
проверяли — статус DND туда не попадает, поймать нечем. Единственный рабочий
способ: читать сырой pjsip-лог Asterisk и искать реальный ответ на INVITE
`SIP/2.0 486 Do Not Disturb` (обычный DIALSTATUS в диалплане сворачивает это
в обезличенный BUSY, теряя фразу).

По крону (раз в 30 сек):
1. Читает последние ~500КБ `verbose`-лога Asterisk
2. Сопоставляет `486`-ответы с исходным INVITE по Call-ID → добавочный
3. Сверяет с `asterisk -rx "queue show QUEUE-..."` — берёт **только** членов
   очереди техподдержки, игнорирует остальные добавочные
4. Шлёт `POST /pbx/dnd-log` с `state=missed_dnd` (отдельная метка от
   честного `*78` — на хелпдеске не путаются "сам подтвердил" / "поймали по факту")

**Требует `debug=yes` в `[global]` секции pjsip.conf** — см. `custom_files/pjsip_conf_patch.sh`.
Без этого фраза "Do Not Disturb" вообще нигде не логируется. Это осознанный
компромисс (подробный SIP-лог — это много лишнего для человека, но без него
не поймать этот конкретный кейс) — обсуждали с пользователем 2026-07-02.

State-файл (позиция чтения лога) — `/storage/usbdisk1/mikopbx/dnd_486_watcher.state`,
lock — `/storage/usbdisk1/mikopbx/dnd_486_watcher.lock`. **Не /tmp** — по
явной просьбе пользователя ничего рабочее не хранить на tmpfs.

---

### cdr_report.sh + cdr_report.py
**Расположение на MikoPBX:** /storage/usbdisk1/mikopbx/

Отчёт по CDR (Call Detail Records). Запускается ежедневно в 22:00.

---

### lanbill-phbook/lbphone.sh
**Расположение на MikoPBX:** /storage/usbdisk1/mikopbx/lanbill-phbook/

Синхронизация телефонной книги из LanBilling в MikoPBX.

### lanbill-phbook/lbblock.sh
Блокировка звонков по данным из LanBilling.

### lanbill-phbook/lbsumm.sh
Вспомогательный скрипт суммирования.

---

### lb_ivr.sh — LanBilling IVR (самообслуживание)
**На МикоПБХ:** `/storage/usbdisk1/mikopbx/agi-bin/lb_ivr.sh`
(в диалплане вызывается по полному пути, не из `/var/lib/asterisk/agi-bin/`)

Bash AGI скрипт v7 — информирование абонента по телефону, обещанный платёж,
интерактивное меню. Логирует каждое действие на хелпдеск
(`POST /pbx/ivr-log`, страница журнала: HelpDesk → IVR-лог).

**Зависимости:**
- python3 (XML парсинг, есть на МикоПБХ)
- Звуки: `/storage/usbdisk1/mikopbx/media/custom/ivr_lb_*.gsm` (генератор: lb_ivr_gen_sounds.sh)
- LanBilling API: http://193.233.140.18:34012
- LB_LOGIN/LB_PASS — учётка LanBilling, заменена на плейсхолдер, спросить у администратора
- HELPDESK_TOKEN — тот же `PBX_TOKEN`, что у остальных скриптов

**Восстановление звуков:** запустить lb_ivr_gen_sounds.sh на МикоПБХ (одноразово, нужен интернет).

---

## crontab.txt
Полный дамп crontab пользователя root. Включает `queue_monitor.sh` (4×/мин)
и `dnd_486_watcher.py` (2×/мин, добавлено 2026-07-02).

Хранится в `mikopbx.db` → `m_CustomFiles` (`filepath='/var/spool/cron/crontabs/root'`,
`mode='append'`) — **не переживает перезагрузку сам по себе**, регенерируется
из БД. Обновлять и БД, и живой crontab одновременно:
```bash
B64=$(base64 -w0 crontab.txt)
ssh root@192.168.186.186 "sqlite3 /cf/conf/mikopbx.db \"UPDATE m_CustomFiles SET content='\$B64', changed='1' WHERE filepath='/var/spool/cron/crontabs/root';\""
ssh root@192.168.186.186 "crontab crontab.txt"
```

---

## dialplan-apps/
Кастомные приложения диалплана (Телефония → Приложения диалпланов в MikoPBX).
Хранятся в `/cf/conf/mikopbx.db`, таблица `m_DialplanApplications`, поле
`applicationlogic` в base64. Имя файла: `dp_<extension>_<db_id>.txt`.

| Файл | Расширение | Назначение |
|------|-----------|------------|
| dp_000063_1.txt | 000063 | Reads back the extension — зачитывает номер звонящего |
| dp_000064_4.txt | 000064 | 0000MILLI — тест milliwatt |
| dp_10003246_5.txt | 10003246 | Echo test |
| dp_2200100_6.txt | 2200100 | **DAYTIME_SELECTOR** — основной входящий маршрут: проверка блокировки (lbblock.sh), определение имени абонента (lbphone.sh), маршрутизация в очередь |
| dp_2200101_7.txt | 2200101 | PLAY_VSE_ZANYATY — проигрывает "все операторы заняты" |
| dp_2200102_8.txt | 2200102 | TMP — временный/тестовый маршрут |
| dp_\*78_10.txt | *78 | **DND_ON** — включить DND (2026-07-02) |
| dp_\*79_11.txt | *79 | **DND_OFF** — выключить DND (2026-07-02) |

Отдельно: `dp_2200110_9.txt` — LB_IVR, вызывает `lb_ivr.sh`, в этой папке
файла на неё пока нет, добавить при следующей синхронизации.

**Восстановление:** в МикоПБХ создать приложение с тем же extension (или
через `sqlite3 mikopbx.db` INSERT), вставить код из файла, `asterisk -rx
'dialplan reload'`. Проверить: `asterisk -rx "dialplan show _<extension>@applications"`.

---

## custom_files/ (добавлено 2026-07-02)
Правки конфигурационных файлов Asterisk через `m_CustomFiles` в `mikopbx.db`
(режимы `override`/`append`/`script`). Раньше в этой папке не отслеживались —
существовали на сервере ещё до 2026-06-27, но не были задокументированы.

### pjsip_conf_patch.sh → m_CustomFiles id=45, mode=script
Скрипт получает путь к свежесгенерированному `pjsip.conf` в `$1`:
- `qualify_frequency 60→10`, `qualify_timeout 5→3.0` — быстрее видеть отвал регистрации
- `rtp_keepalive = 30` всем эндпоинтам
- `default_expiration 3600→120`
- `debug=yes` в `[global]` (добавлено 2026-07-02) — нужно для детекта DND
  по 486, см. `dnd_486_watcher.py` выше

```bash
B64=$(base64 -w0 custom_files/pjsip_conf_patch.sh)
ssh root@192.168.186.186 "sqlite3 /cf/conf/mikopbx.db \"UPDATE m_CustomFiles SET content='\$B64', changed='1' WHERE id=45;\""
# применить сразу, не дожидаясь перезагрузки:
ssh root@192.168.186.186 "bash custom_files/pjsip_conf_patch.sh /etc/asterisk/pjsip.conf && asterisk -rx 'module reload res_pjsip.so' && asterisk -rx 'pjsip set logger on'"
```

### static_routes.txt → m_CustomFiles id=38, mode=override, filepath=/etc/static-routes
Статические маршруты (транк оператора связи, внутренние сети). Существовали
до текущей сессии, не моя работа — включены для полноты бэкапа.

### etc_hosts_append.txt → m_CustomFiles id=37, mode=append, filepath=/etc/hosts
Одна строка (закомментирована), существовала до текущей сессии.

---

## media/
Кастомные звуковые файлы (сохранены только .mp3).
На сервере находятся в `/storage/usbdisk1/mikopbx/media/custom/`.
При восстановлении загрузить через МикоПБХ: Телефония → Звуковые файлы.

---

## Деплой на новый MikoPBX (с нуля)

1. Скопировать `*.sh`/`*.py` в `/storage/usbdisk1/mikopbx/` (и `lanbill-phbook/`
   на своё место), `dnd_log.sh`/`dnd_486_watcher.py`/`lb_ivr.sh` — в
   `/storage/usbdisk1/mikopbx/agi-bin/`
2. Вставить реальные секреты вместо плейсхолдеров (`HELPDESK_TOKEN` = `PBX_TOKEN`
   из `.env` HelpDesk, `LB_LOGIN`/`LB_PASS` — спросить у администратора)
3. `sed -i 's/\r//' <все .sh и .py>` (CRLF после копирования с Windows ломает скрипты)
4. `chmod +x` всем `.sh`/`.py`
5. Восстановить crontab: `crontab crontab.txt` + записать в `m_CustomFiles` (см. выше)
6. Восстановить `dialplan-apps/*.txt` через `m_DialplanApplications` (см. выше)
7. Применить `custom_files/pjsip_conf_patch.sh` к `pjsip.conf` + записать в `m_CustomFiles`
8. Восстановить звуки из `media/` + запустить `lb_ivr_gen_sounds.sh`
9. Добавить SSH-ключ через веб-панель: Системные настройки → SSH → SSH Authorized Keys
10. `asterisk -rx 'dialplan reload'`, `asterisk -rx 'module reload res_pjsip.so'`

---

## Как DND работает целиком (кратко)

- **`*78`/`*79` с телефона** → диалплан выставляет `DEVICE_STATE(Custom:EXT)`
  (уже встроено в хинты MikoPBX как `PJSIP/EXT&Custom:EXT` — ничего не ломает) →
  сразу видно в очереди/BLF → `dnd_log.sh` шлёт `state=on/off`.
- **DND-кнопка в MicroSIP** (не звёздочка) → сервер узнаёт только по факту
  отказа реального звонка (`dnd_486_watcher.py`, `state=missed_dnd`).
- Оба канала пишут в таблицу `dnd_logs` на HelpDesk, отображаются на странице
  **"Звонки"** (не отдельным пунктом меню — по явной просьбе пользователя
  2026-07-02): бейдж у оператора в списке "Операторы" + маркеры на графике.
