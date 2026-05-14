# HelpDesk — Структура проекта

## Технологический стек
- PHP 8.2 + Laravel 12
- MySQL 8.0
- Vue 3 + Inertia.js v2
- Tailwind CSS 3
- FullCalendar.js 6
- Vite

## Установка

```bash
# После копирования файлов проекта:
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
```

## Структура директорий

```
app/
├── Console/Commands/
│   ├── SendDailySummary.php        # утренняя сводка (cron)
│   ├── SendEveningReport.php       # вечерний отчёт (cron)
│   └── SyncTicketCommand.php       # artisan sync:ticket (вызывается скриптом синхронизации)
├── Http/Controllers/
│   ├── DashboardController.php
│   ├── TicketController.php
│   ├── CalendarController.php
│   ├── TerritoryController.php
│   ├── BrigadeController.php       # CRUD + show для бригадира + updateMembers
│   ├── BrigadeScheduleController.php  # расписание бригад (генерация, сохранение)
│   ├── AddressController.php
│   ├── AttachmentController.php
│   ├── LanBillingController.php
│   ├── SettingsController.php
│   ├── SyncController.php          # POST /sync/ticket — приём заявок из старой системы
│   ├── TelegramController.php      # webhook Telegram бота
│   ├── ReportsController.php
│   ├── HelpController.php
│   ├── MaterialController.php
│   └── PushController.php
├── Http/Middleware/
│   ├── EnsureUserIsActive.php      # блокировка деактивированных пользователей
│   └── HandleInertiaRequests.php   # shared props: auth, flash, closeReasons
├── Models/
│   ├── User.php
│   ├── Role.php
│   ├── Territory.php
│   ├── Brigade.php                 # fillable: name, foreman_id, min_workers
│   ├── BrigadeSchedule.php
│   ├── ScheduleHoliday.php
│   ├── Address.php
│   ├── Ticket.php
│   ├── TicketType.php
│   ├── TicketStatus.php
│   ├── TicketComment.php
│   ├── TicketAttachment.php
│   ├── TicketHistory.php
│   ├── NotificationsLog.php
│   ├── Material.php
│   └── SystemSettings.php
├── Notifications/
│   ├── DailySummaryNotification.php   # Telegram + Email + Max
│   ├── EveningReportNotification.php  # Telegram + Email + Max
│   ├── NewTicketNotification.php      # Push + Telegram + Max broadcast
│   ├── TelegramChannel.php
│   └── MaxChannel.php                 # Max (корпоративный мессенджер)
├── Observers/
│   └── TicketObserver.php             # авто-логирование истории заявок
├── Services/
│   ├── TicketService.php              # бизнес-логика заявок
│   ├── LanBillingService.php          # интеграция с API биллинга
│   ├── AddressImportService.php       # импорт CSV/XLS адресов
│   ├── TelegramService.php            # отправка в Telegram + форматирование
│   └── MaxService.php                 # broadcast + форматирование для Max
└── Policies/
    └── TicketPolicy.php

resources/js/
├── Pages/
│   ├── Dashboard/Index.vue
│   ├── Tickets/
│   │   ├── Index.vue               # список + фильтры
│   │   ├── Show.vue                # карточка заявки
│   │   ├── Create.vue
│   │   └── Edit.vue
│   ├── Calendar/Index.vue
│   ├── Brigades/
│   │   ├── Index.vue               # список бригад (только manage-settings)
│   │   ├── Show.vue                # страница бригадира — состав + расписание
│   │   └── Schedule.vue            # сетка расписания
│   ├── Territories/Index.vue
│   ├── Addresses/Index.vue
│   ├── Settings/Index.vue
│   ├── Reports/Index.vue
│   └── Help/Index.vue
└── Components/
    ├── Layout/
    │   ├── AppLayout.vue
    │   ├── Sidebar.vue
    │   └── Topbar.vue
    ├── Tickets/
    │   ├── TicketCard.vue
    │   ├── TicketBadge.vue
    │   ├── CommentThread.vue
    │   ├── AttachmentUpload.vue
    │   ├── AttachmentList.vue
    │   └── AddressHistoryPanel.vue
    ├── Addresses/
    │   ├── AddressSearch.vue
    │   └── LanBillingLookup.vue
    └── UI/
        ├── Modal.vue
        ├── Confirm.vue
        ├── Dropdown.vue
        ├── Badge.vue
        ├── FilePreview.vue
        └── Pagination.vue
```

## Переменные окружения (.env)

```env
APP_NAME="HelpDesk"
APP_URL=https://helpdesk.example.com

DB_CONNECTION=mysql
DB_DATABASE=helpdesk
DB_USERNAME=helpdesk
DB_PASSWORD=secret

QUEUE_CONNECTION=database

# Telegram Bot
TELEGRAM_BOT_TOKEN=1234567890:AABBcc...

# Max (корпоративный мессенджер)
MAX_BOT_TOKEN=your_max_bot_token_here

# Синхронизация из старой системы
SYNC_TOKEN=your_random_token_here

# Готовые причины закрытия (через точку с запятой)
CLOSE_REASONS="Работы выполнены;Абонент недоступен;Ложный вызов;Нет доступа к оборудованию"

# LANBilling
LANBILLING_URL=http://billing.example.com/api
LANBILLING_LOGIN=api_user
LANBILLING_PASSWORD=api_pass

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=helpdesk@example.com
MAIL_PASSWORD=secret
```

## Настройка Cron (scheduler)

В `routes/console.php`:
```php
Schedule::command('helpdesk:daily-summary')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('helpdesk:evening-report')->dailyAt('20:00')->withoutOverlapping();
```

Cron строка на сервере:
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Роли и доступ

| Роль | Ключ | Основные права |
|------|------|----------------|
| Администратор | `admin` | Полный доступ |
| Руководитель поддержки | `head_support` | Настройки, все заявки, отчёты |
| Диспетчер | `dispatcher` | Создание и управление заявками |
| Бригадир | `foreman` | Своя бригада + расписание, создание заявок |
| Техник | `technician` | Заявки своих территорий |

Бригадир видит пункт «Моя бригада» в боковом меню и может управлять составом бригады.

## Интеграции

**Telegram Bot** — уведомления о новых заявках + утренние сводки + вечерние отчёты. Фильтрация по территориям пользователя.

**Max** — корпоративный мессенджер, аналогичные уведомления. Поля: `max_chat_id`, `notify_max` в профиле пользователя.

**LANBilling** — поиск абонента по телефону/договору при создании заявки.

**Синхронизация** — скрипт `sync_requests.py` импортирует заявки из старой системы через artisan-команду `sync:ticket`.