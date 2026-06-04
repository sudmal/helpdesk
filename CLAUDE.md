# CLAUDE.md — HelpDesk

## Обзор проекта

**HelpDesk** — система управления заявками техподдержки интернет-провайдера. Диспетчеры принимают заявки от абонентов, назначают бригады, отслеживают выполнение. Бригадиры и монтажники работают через мобильное приложение (Android). Уведомления идут в Telegram, Max (корпоративный мессенджер) и email.

Ключевые сущности: заявки (Ticket), бригады (Brigade), территории (Territory), адреса (Address), расписание бригад (BrigadeSchedule), заявки на подключение (ConnectionRequest).

---

## Технологический стек

### Backend
- **PHP 8.2** + **Laravel 12**
- **MySQL 8.0** — основная БД
- **Redis** — очереди (`QUEUE_CONNECTION=redis`) и кэш (`CACHE_DRIVER=redis`)
- `predis/predis` ^3.4 — PHP-клиент Redis
- `inertiajs/inertia-laravel` ^3.0 — SSR-like SPA без отдельного API для веба
- `laravel/sanctum` ^4.0 — Bearer-токены для мобильного API
- `tightenco/ziggy` ^2.0 — Laravel-routes в JavaScript
- `shuchkin/simplexlsxgen` ^1.5 — экспорт расписания в Excel (без COM/ext, чистый PHP)
- `laravel-notification-channels/webpush` ^10.5 — Web Push уведомления
- `guzzlehttp/guzzle` ^7.8 — HTTP-клиент для внешних API

### Frontend
- **Vue 3** ^3.4
- **Inertia.js** `@inertiajs/vue3` ^2.3 — роутинг через Laravel, props вместо fetch
- **Tailwind CSS** ^3.4
- **Vite** ^5.2 с `laravel-vite-plugin`
- `@fullcalendar/vue3` ^6.1 — календарь заявок с drag-and-drop
- `chart.js` ^4.4 — графики на странице отчётов
- `dayjs` ^1.11 — работа с датами
- `lucide-vue-next` ^0.378 — иконки

---

## Структура директорий

```
app/
├── Console/Commands/          # artisan-команды
├── Http/
│   ├── Controllers/           # веб-контроллеры (Inertia)
│   │   └── Api/               # REST-контроллеры для мобильного API
│   ├── Middleware/
│   │   ├── EnsureUserIsActive.php   # выбивает деактивированных пользователей
│   │   ├── HandleInertiaRequests.php # shared props: auth, flash, closeReasons
│   │   └── ForceJsonResponse.php    # API: всегда JSON
│   └── Requests/              # Form Request валидация
├── Models/
├── Notifications/
│   ├── TelegramChannel.php    # кастомный канал — логирует в NotificationsLog
│   └── MaxChannel.php         # кастомный канал для Max мессенджера
├── Observers/
│   └── TicketObserver.php     # авто-логирование истории заявок
├── Policies/
│   └── TicketPolicy.php       # авторизация действий с заявками
└── Services/
    ├── TicketService.php          # create, updateStatus, assign, storeAttachment
    ├── LanBillingService.php      # поиск абонента по телефону/договору
    ├── AddressImportService.php   # импорт адресов из CSV/XLS
    ├── TelegramService.php        # форматирование + отправка в Telegram
    ├── MaxService.php             # broadcast + форматирование для Max
    └── LoginThrottleService.php   # блокировка IP при переборе паролей

resources/js/
├── app.js                     # точка входа Inertia
├── Pages/                     # Inertia-страницы (1:1 с контроллерами)
│   ├── Dashboard/Index.vue
│   ├── Tickets/{Index,Show,Create,Edit}.vue
│   ├── Calendar/Index.vue
│   ├── Brigades/{Index,Show,Schedule}.vue
│   ├── Reports/Index.vue
│   └── Settings/Index.vue
└── Components/
    ├── Layout/{AppLayout,Sidebar,Topbar}.vue
    ├── Tickets/               # TicketCard, CommentThread, AttachmentUpload...
    ├── Addresses/             # AddressSearch, LanBillingLookup
    └── UI/                    # Modal, Confirm, Dropdown, Badge, Pagination...

routes/
├── web.php                    # все веб-маршруты (Inertia, middleware: auth + active)
├── api.php                    # REST API для мобильного приложения (Sanctum)
├── console.php                # расписание scheduler
└── auth.php                   # login/logout

database/
├── migrations/                # хронологические миграции
└── seeders/DatabaseSeeder.php
```

---

## Команды

### Установка
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
```

### Разработка
```bash
npm run dev          # Vite dev-сервер с HMR
php artisan serve    # Laravel dev-сервер
```

### Очереди
```bash
php artisan queue:work --queue=default,notifications
```

### Artisan-команды приложения
```bash
php artisan helpdesk:daily-summary          # утренняя сводка (или с --scheduled)
php artisan helpdesk:evening-report         # вечерний отчёт
php artisan helpdesk:morning-report         # утренний отчёт
php artisan helpdesk:close-overdue --days=30 # автозакрытие просроченных
php artisan sync:ticket                     # импорт из старой системы
php artisan helpdesk:cleanup-addresses
php artisan helpdesk:geocode-addresses
```

### Планировщик (cron на сервере)
```
* * * * * cd /var/www/helpdesk && php artisan schedule:run >> /dev/null 2>&1
```

Расписание в `routes/console.php`:
- `helpdesk:daily-summary --scheduled` — каждую минуту, время из `SystemSetting`
- `helpdesk:evening-report --scheduled` — каждую минуту, время из `SystemSetting`
- `helpdesk:morning-report --scheduled` — каждую минуту, время из `SystemSetting`
- `helpdesk:close-overdue` — ежедневно в 03:00

### Telegram Bot
```bash
# Открыть в браузере (auth required):
GET /telegram/set-webhook
```

---

## Архитектурные решения

### Inertia.js монолит
Веб-интерфейс построен на Inertia.js: контроллер возвращает `Inertia::render('Page/Name', [...props])`, Vue получает данные как props без отдельных fetch-запросов. Нет GraphQL, нет REST для веба.

### Dual API: Inertia (web) + REST (mobile)
Мобильное приложение (Android) использует отдельные контроллеры `App\Http\Controllers\Api\*` с Sanctum Bearer-токенами. Документация API в `API_MOBILE.md`.

### Service Layer
Бизнес-логика вынесена из контроллеров в сервисы. `TicketService` — центральный: создание (в транзакции), смена статуса, назначение, загрузка вложений.

### Роли и разрешения
`Role.permissions` хранит JSON-массив строк. Поддерживает wildcards: `*` (всё), `tickets.*` (все операции с заявками). Хелперы на `User`: `hasPermission()`, `isAdmin()`, `isForeman()` и т.д. Политики авторизации — `TicketPolicy`.

Роли: `admin`, `head_support`, `operator`, `foreman`, `technician`.

### Нумерация заявок
`Ticket::generateNumber()` — префикс по типу услуги: `i-` (интернет), `c-` (КТВ), `T-` (остальное). Формат: `i-000042`. Ищет первый свободный номер с учётом soft-deleted.

### Уведомления
Три кастомных канала: `TelegramChannel`, `MaxChannel`, стандартный `mail`. Каждая отправка логируется в `NotificationsLog`. Пользователь сам выбирает каналы (`notify_telegram`, `notify_email`, `notify_max`, `notify_on_days_off`).

### Расписание бригад
`BrigadeSchedule` хранит статус участника на дату (работает/выходной). Флаг `exclude_from_schedule` на pivot-таблице `brigade_user` исключает человека из генерации расписания. Экспорт в Excel через `SimpleXLSXGen`.

### Безопасность
`LoginThrottleService` — блокировка IP через Redis (счётчик) + таблица `blocked_ips`. Параметры (кол-во попыток, время блокировки) настраиваются через `SystemSetting`. Разблокировка вручную через страницу настроек (Security).

### SystemSetting
Настройки хранятся в таблице `system_settings` (key-value). Используется для: времени рассылок, включения/выключения уведомлений, параметров безопасности, LANBilling credentials.

---

## Интеграции

| Система | Описание |
|---------|---------|
| **Telegram Bot** | Уведомления о новых заявках, утренние сводки, вечерние отчёты. Webhook: `POST /telegram/webhook` |
| **Max** | Корпоративный мессенджер, аналогичные уведомления. Поле `max_chat_id` на User |
| **LANBilling** | Поиск абонента по телефону/договору при создании заявки |
| **PBX** | Входящие вызовы: `POST /api/pbx/incoming` (без auth), логируется в `calls` |
| **Sync API** | Импорт из старой системы: `POST /sync/ticket` (токен в заголовке, без CSRF) |
| **Web Push** | Push-уведомления браузера через `laravel-notification-channels/webpush` |
| **Mobile API** | Android-приложение, Bearer Sanctum, см. `API_MOBILE.md` |

---

## Важные соглашения

- **Middleware `active`** — алиас для `EnsureUserIsActive`, применяется ко всем web-маршрутам
- **CSRF исключения** — `telegram/webhook` и `sync/ticket` в `bootstrap/app.php`
- **Alias `@`** в JS — `resources/js/` (настроен в `vite.config.js`)
- **SoftDeletes** — включён на модели `Ticket`
- **Файлы вложений** — хранятся в `storage/app/public/tickets/{ticket_id}/attachments/`, доступны через `FILESYSTEM_DISK=public`
- **Заявки на подключение** (`ConnectionRequest`) — отдельная сущность, не путать с `Ticket`; свой статус-флаг (строка, не FK), своя страница
- **`ForceJsonResponse`** — middleware на API-группе, гарантирует JSON даже при 404/500
- **Команды с `--scheduled`** — проверяют время и флаг `enabled` из `SystemSetting` перед отправкой
