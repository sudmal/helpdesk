# HelpDesk — Структура проекта

## Технологический стек
- PHP 8.2 + Laravel 11
- MySQL 8.0
- Redis (очереди, кэш)
- Vue 3 + Inertia.js
- Tailwind CSS 3
- FullCalendar.js 6
- Vite

## Установка

```bash
composer create-project laravel/laravel helpdesk
cd helpdesk

# Пакеты backend
composer require \
  inertiajs/inertia-laravel \
  tightenco/ziggy \
  maatwebsite/excel \
  spatie/laravel-activitylog \
  laravel/sanctum

# Пакеты frontend
npm install \
  @inertiajs/vue3 \
  @fullcalendar/vue3 \
  @fullcalendar/daygrid \
  @fullcalendar/timegrid \
  @fullcalendar/interaction \
  @headlessui/vue \
  @heroicons/vue \
  axios \
  dayjs

# После копирования файлов проекта:
php artisan migrate --seed
php artisan storage:link
```

## Структура директорий

```
helpdesk/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── SendDailySummary.php      # утренняя сводка бригадирам (cron)
│   │       └── SendEveningReport.php     # вечерний отчёт руководителям (cron)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   ├── TicketController.php
│   │   │   ├── CalendarController.php
│   │   │   ├── TerritoryController.php
│   │   │   ├── BrigadeController.php
│   │   │   ├── AddressController.php
│   │   │   ├── AttachmentController.php
│   │   │   ├── LanBillingController.php
│   │   │   └── SettingsController.php
│   │   ├── Middleware/
│   │   │   └── EnsureUserIsActive.php    # блокировка деактивированных
│   │   └── Requests/
│   │       ├── StoreTicketRequest.php
│   │       ├── UpdateTicketRequest.php
│   │       ├── StoreAddressRequest.php
│   │       └── ImportAddressesRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Territory.php
│   │   ├── Brigade.php
│   │   ├── Address.php
│   │   ├── Ticket.php
│   │   ├── TicketType.php
│   │   ├── TicketStatus.php
│   │   ├── TicketComment.php
│   │   ├── TicketAttachment.php
│   │   ├── TicketHistory.php
│   │   └── NotificationsLog.php
│   ├── Notifications/
│   │   ├── DailySummaryNotification.php  # Telegram + Email
│   │   ├── EveningReportNotification.php
│   │   └── TicketStatusChanged.php
│   ├── Observers/
│   │   └── TicketObserver.php            # авто-логирование истории
│   ├── Policies/
│   │   └── TicketPolicy.php
│   └── Services/
│       ├── TicketService.php             # бизнес-логика заявок
│       ├── LanBillingService.php         # интеграция с API биллинга
│       ├── AddressImportService.php      # импорт CSV/XLS
│       └── NotificationService.php      # отправка уведомлений
├── database/
│   ├── migrations/
│   │   ├── 2024_01_01_000001_create_roles_table.php
│   │   ├── 2024_01_01_000002_create_users_table.php
│   │   ├── 2024_01_01_000003_create_territories_table.php
│   │   ├── 2024_01_01_000004_create_brigades_tables.php
│   │   ├── 2024_01_01_000005_create_addresses_table.php
│   │   ├── 2024_01_01_000006_create_ticket_types_statuses.php
│   │   ├── 2024_01_01_000007_create_tickets_table.php
│   │   └── 2024_01_01_000008_create_ticket_support_tables.php
│   └── seeders/
│       └── DatabaseSeeder.php
├── resources/
│   ├── js/
│   │   ├── app.js                        # точка входа Inertia
│   │   ├── Pages/
│   │   │   ├── Dashboard/
│   │   │   │   └── Index.vue
│   │   │   ├── Tickets/
│   │   │   │   ├── Index.vue             # список + фильтры
│   │   │   │   ├── Show.vue              # карточка заявки
│   │   │   │   ├── Create.vue            # создание
│   │   │   │   └── Edit.vue
│   │   │   ├── Calendar/
│   │   │   │   └── Index.vue             # FullCalendar
│   │   │   ├── Territories/
│   │   │   │   └── Index.vue
│   │   │   ├── Brigades/
│   │   │   │   └── Index.vue
│   │   │   ├── Addresses/
│   │   │   │   └── Index.vue
│   │   │   └── Settings/
│   │   │       ├── Index.vue             # типы + статусы
│   │   │       ├── Users.vue             # управление пользователями
│   │   │       └── LanBilling.vue
│   │   └── Components/
│   │       ├── Layout/
│   │       │   ├── AppLayout.vue
│   │       │   ├── Sidebar.vue
│   │       │   └── Topbar.vue
│   │       ├── Tickets/
│   │       │   ├── TicketCard.vue
│   │       │   ├── TicketBadge.vue       # статус / тип
│   │       │   ├── CommentThread.vue
│   │       │   ├── AttachmentUpload.vue  # drag-n-drop загрузка файлов
│   │       │   ├── AttachmentList.vue
│   │       │   └── AddressHistoryPanel.vue # история заявок по адресу
│   │       ├── Addresses/
│   │       │   ├── AddressSearch.vue     # поиск + подсказки
│   │       │   └── LanBillingLookup.vue  # поиск по телефону/договору
│   │       └── UI/
│   │           ├── Modal.vue
│   │           ├── Confirm.vue
│   │           ├── Dropdown.vue
│   │           ├── Badge.vue
│   │           ├── FilePreview.vue       # предпросмотр фото/видео
│   │           └── Pagination.vue
│   └── views/
│       └── app.blade.php                 # Inertia root template
├── routes/
│   ├── web.php
│   └── auth.php
└── config/
    ├── lanbilling.php                    # URL API + токен
    └── notifications.php                 # расписание рассылок
```

## Настройка Cron (scheduler)

В `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    // Утренняя сводка бригадирам: 08:00 каждый день
    $schedule->command('helpdesk:daily-summary')
             ->dailyAt('08:00')
             ->withoutOverlapping();

    // Вечерний отчёт руководителям: 20:00
    $schedule->command('helpdesk:evening-report')
             ->dailyAt('20:00')
             ->withoutOverlapping();
}
```

Cron строка на сервере:
```
* * * * * cd /var/www/helpdesk && php artisan schedule:run >> /dev/null 2>&1
```

## Переменные окружения (.env)

```env
APP_NAME="HelpDesk"
APP_URL=https://helpdesk.example.com

DB_CONNECTION=mysql
DB_DATABASE=helpdesk
DB_USERNAME=helpdesk
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis

# LANBilling
LANBILLING_URL=http://billing.example.com/api
LANBILLING_LOGIN=api_user
LANBILLING_PASSWORD=api_pass

# Telegram Bot
TELEGRAM_BOT_TOKEN=1234567890:AABBcc...

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=helpdesk@example.com
MAIL_PASSWORD=secret

# Storage (local или s3)
FILESYSTEM_DISK=local
```
