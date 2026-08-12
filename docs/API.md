# Справочник API

Этот документ описывает **все** HTTP-эндпоинты системы. Прочитайте сначала
[REQUEST_LIFECYCLE.md](REQUEST_LIFECYCLE.md) — там объясняется разница между
тремя типами ответов, которые тут встречаются:

- **Inertia-страница** — маршрут отдаёт целую страницу (Vue-компонент +
  пропсы). Такой маршрут не предназначен для вызова "как API" откуда-то
  извне — это то, что видит пользователь, переходя по сайту. Для таких
  маршрутов ниже указан Vue-компонент и краткое описание передаваемых
  пропсов, а не JSON-схема.
- **JSON-эндпоинт веб-интерфейса** — обычный `axios`-запрос из уже
  загруженной Vue-страницы (поиск, автодополнение, живые обновления). Ведёт
  себя как настоящий JSON API, но требует ту же cookie-сессию, что и
  остальной сайт (не подходит для внешних интеграций).
- **Мобильный API** (`/api/*`) — настоящий REST/JSON API с авторизацией по
  Bearer-токену (Laravel Sanctum), для Android-приложения и мобильной PWA
  (`app.vega8.ru`). Единственная часть этого документа, которую стоит
  рассматривать как "публичный контракт" для внешнего клиента.

**Аутентификация:**
- Веб (Inertia + JSON-эндпоинты веб-интерфейса) — обычная сессия Laravel
  (кука после логина через `POST /login`). Все запросы защищены CSRF-токеном
  (уже обрабатывается автоматически и Inertia, и `axios` через мета-тег на
  странице).
- Мобильный API — `Authorization: Bearer <токен>`, токен выдаётся `POST
  /api/auth/login`.

Почти все веб-маршруты (кроме логина и нескольких публичных вебхуков) идут
через middleware `auth` + `active` — незалогиненный/деактивированный
пользователь получит редирект на `/login`. Дополнительно часть маршрутов
требует `can:manage-settings` (роль `admin`/`head_support`) — отмечено в
таблицах ниже.

---

## Часть 1. Веб-маршруты (Inertia-страницы)

Формат таблиц: **Метод и путь** · **Контроллер → метод** · **Права** ·
**Vue-страница** — и что на ней есть.

### Аутентификация

| Метод и путь | Контроллер | Права | Компонент |
|---|---|---|---|
| `GET /login` | `Auth\AuthenticatedSessionController@create` | гость | `Auth/Login.vue` — форма логина, показывает капчу и баннер блокировки IP при подборе пароля |
| `POST /login` | `Auth\AuthenticatedSessionController@store` | гость | — |
| `GET /captcha` | `Auth\CaptchaController@generate` | гость | картинка капчи |
| `POST /logout` | `Auth\AuthenticatedSessionController@destroy` | auth | — |
| `GET /logout` | (замыкание в `routes/auth.php`) | — | просто редиректит на `/`, ничего не разлогинивает — защита от случайных GET на logout из закладок |

### Профиль

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `PUT /profile` | `ProfileController@update` | auth. Правит **только** свои поля: `name, phone, email, telegram_chat_id, max_chat_id, notify_on_days_off`. Роль/логин/пароль тут не меняются |

### Дашборд

| Метод и путь | Контроллер → метод | Права | Компонент/ответ |
|---|---|---|---|
| `GET /` | `DashboardController@index` | auth | `Dashboard/Index.vue` — заявки на сегодня, просроченные, счётчики по территориям/участкам, заявки на подключение |
| `GET /dashboard/new-since` | `DashboardController@newTicketsSince` | auth | JSON — поллинг новых заявок с момента `?since=<timestamp>`, до 20 штук, для live-тоста на дашборде |

### Заявки (Tickets)

| Метод и путь | Контроллер → метод | Права | Компонент/ответ |
|---|---|---|---|
| `GET /tickets` | `TicketController@index` | `TicketPolicy::viewAny` | `Tickets/Index.vue` — список с фильтрами/сортировкой, пагинация 25 |
| `GET /tickets/create` | `TicketController@create` | `TicketPolicy::create` | `Tickets/Create.vue` |
| `POST /tickets` | `TicketController@store` (`StoreTicketRequest`) | `TicketPolicy::create` | редирект на карточку созданной заявки |
| `GET /tickets/{ticket}` | `TicketController@show` | `TicketPolicy::view` | `Tickets/Show.vue` — полная карточка, набор `can*` пропсов из `TicketPolicy` |
| `GET /tickets/{ticket}/edit` | `TicketController@edit` | `TicketPolicy::update` | `Tickets/Edit.vue` |
| `PUT /tickets/{ticket}` | `TicketController@update` (`UpdateTicketRequest`) | `TicketPolicy::update` | — (`status_id` тут не может быть финальным — закрытие только через `/close`) |
| `DELETE /tickets/{ticket}` | `TicketController@destroy` | `TicketPolicy::delete` (только admin, и только если акт ещё не утверждён бригадиром) | soft-delete |
| `POST /tickets/{ticket}/start` | `TicketController@start` | `TicketPolicy::start` (только из статуса `new`/`paused`) | — |
| `POST /tickets/{ticket}/pause` | `TicketController@pause` | `TicketPolicy::pause` (только из `in_progress`) | — |
| `POST /tickets/{ticket}/close` | `TicketController@close` | `TicketPolicy::close` | см. врезку "Закрытие заявки с материалами" ниже |
| `POST /tickets/{ticket}/cancel` | `TicketController@cancel` | `TicketPolicy::cancel` | требует `comment` |
| `POST /tickets/{ticket}/reopen` | `TicketController@reopen` | `TicketPolicy::update` | статус → `new` |
| `POST /tickets/{ticket}/postpone` | `TicketController@postpone` | `TicketPolicy::postpone` | новая `scheduled_at` |
| `POST /tickets/{ticket}/assign` | `TicketController@assign` | `TicketPolicy::assign` | смена `brigade_id`/`assigned_to` |
| `POST /tickets/{ticket}/comments` | `TicketController@addComment` (`AddCommentRequest`) | `TicketPolicy::comment` (доступно всем) | — |
| `GET /tickets/map` | `TicketController@map` | `TicketPolicy::viewAny` | `Tickets/Map.vue` — Leaflet-карта, без AppLayout |
| `GET /tickets/map-data` | `TicketController@mapData` | `TicketPolicy::viewAny` | JSON точек с координатами. ⚠️ в отличие от `index`, **не скоупится по территориям** |
| `GET /tickets/free-slot` | `TicketController@freeSlot` | auth | JSON — ближайший свободный слот времени для бригады/даты |
| `GET /tickets/occupied-slots` | `TicketController@occupiedSlots` | auth | JSON — занятые слоты, чтобы задизейблить их в UI |
| `POST /tickets/bulk/close` | `TicketController@bulkClose` | по каждой заявке отдельно (пропускает те, что нельзя) | массовое закрытие до 500 заявок |
| `POST /tickets/bulk/reschedule` | `TicketController@bulkReschedule` | аналогично | массовый перенос |

**Закрытие заявки с материалами**: если в `POST /tickets/{id}/close` передан
непустой `materials`, сервер создаёт **Акт** (см. раздел "Акты" ниже) со
статусом `pending_foreman` и сам генерирует номер. У заявки может быть
только один акт за всю жизнь — повторная попытка передать `materials`, когда
акт уже есть, вернёт ошибку валидации с указанием номера существующего
акта (в мобильном API — тот же самый эндпоинт и то же правило, HTTP 422).

### Вложения

| Метод и путь | Контроллер → метод | Права | Ответ |
|---|---|---|---|
| `POST /attachments` | `AttachmentController@store` | `TicketPolicy::comment` | JSON карточки вложения |
| `GET /attachments/{id}/download` | `AttachmentController@download` | `TicketPolicy::view` заявки-владельца | скачивание файла |
| `DELETE /attachments/{id}` | `AttachmentController@destroy` | `TicketPolicy::update` заявки-владельца | JSON `{ok:true}` |

### Календарь

| Метод и путь | Контроллер → метод | Права | Ответ |
|---|---|---|---|
| `GET /calendar` | `CalendarController@index` | auth | `Calendar/Index.vue` (FullCalendar) |
| `GET /calendar/events` | `CalendarController@events` | auth, скоуп по территории | JSON-массив событий FullCalendar — заявки + заявки на подключение вперемешку |

### Территории

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `GET /territories` | `TerritoryController@index` | `manage-settings` |
| `POST /territories` | `TerritoryController@store` | `manage-settings`. Новую территорию автоматически получают все активные `operator`/`head_support`/`peo`/`logistics` (роли, которым положено видеть всё) |
| `PUT /territories/{territory}` | `TerritoryController@update` | `manage-settings` |
| `DELETE /territories/{territory}` | `TerritoryController@destroy` | `manage-settings`, блокируется если есть адреса |

### Бригады и расписание

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `GET /brigades` | `BrigadeController@index` | `manage-settings` |
| `POST /brigades` | `BrigadeController@store` | `manage-settings` |
| `PUT /brigades/{brigade}` | `BrigadeController@update` | `manage-settings` |
| `DELETE /brigades/{brigade}` | `BrigadeController@destroy` | `manage-settings`, блокируется если есть история заявок (используйте `toggle-active`) |
| `PATCH /brigades/{brigade}/toggle-active` | `BrigadeController@toggleActive` | `manage-settings` — способ "расформировать" бригаду без потери истории |
| `GET /brigades/{brigade}` | `BrigadeController@show` | `manage-settings` **или** бригадир этой бригады |
| `PUT /brigades/{brigade}/members` | `BrigadeController@updateMembers` | то же |
| `PATCH /brigades/{brigade}/min-workers` | `BrigadeController@updateMinWorkers` | то же |
| `GET /brigades/{brigade}/schedule` | `BrigadeScheduleController@show` | `manage-settings` или бригадир |
| `POST /brigades/{brigade}/schedule/save` | `BrigadeScheduleController@save` | то же |
| `POST /brigades/{brigade}/schedule/generate` | `BrigadeScheduleController@generate` | то же — вычисляет предложение расписания (без сохранения) |
| `GET /brigades/{brigade}/schedule/export` | `BrigadeScheduleController@export` | то же — выгрузка `.xlsx` |
| `POST /brigades/{brigade}/schedule/toggle-exclude` | `BrigadeScheduleController@toggleExclude` | то же — исключить участника из генерации расписания |
| `GET /brigades/{brigade}/schedule/logs` | `BrigadeScheduleController@logs` | то же — последние 200 записей журнала изменений |

### Адреса

Подробно разобрано на реальном примере в
[REQUEST_LIFECYCLE.md](REQUEST_LIFECYCLE.md). Иерархия: город → улица → дом
→ квартира; нет отдельной сущности "город/улица/дом" — это агрегаты по
текстовым полям `Address.city/street/building`.

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `GET /addresses` | `AddressController@index` | auth |
| `POST /addresses` | `AddressController@store` | auth. Защита от дублей/опечаток города и улицы, см. ниже |
| `PUT /addresses/{address}` | `AddressController@update` | auth |
| `DELETE /addresses/{address}` | `AddressController@destroy` | admin (`id=1`), блокируется если есть заявки/звонки на адрес |
| `DELETE /addresses/city` \| `/street` \| `/building` | `destroyCity`/`destroyStreet`/`destroyBuilding` | admin (`id=1`) — массовое удаление всей группы, та же защита от висячих ссылок |
| `PATCH /addresses/rename-city` \| `/rename-street` \| `/rename-building` | `renameCity`/`renameStreet`/`renameBuilding` | `manage-settings`. При совпадении с существующим именем — сливает записи (просит подтверждения) |
| `PATCH /addresses/{address}/geocode` | `storeGeocode` | auth — сохранить координаты (lat/lng) |
| `POST /addresses/import` | `AddressController@import` (через `AddressImportService`) | auth — импорт CSV/XLSX |
| `POST /addresses/bulk-set-type` | `bulkSetType` | auth — массово пометить дома как ЧС/МКД |
| `POST /addresses/set-territory` | `setBuildingTerritory` | auth |
| `GET /addresses/search` | `search` | auth, JSON — быстрый поиск |
| `GET /addresses/hierarchy` | `hierarchy` | auth, JSON — данные для дерева город/улица/дом |

**Защита от плодения городов/улиц через опечатки**: при создании нового
адреса `store()` сверяет введённый город с уже существующими без учёта
префикса типа (`Address::normalizeCity()`) — при совпадении с другим
написанием просит подтвердить, при полностью новом городе создание
разрешено только `id=1`. То же самое (тихая коррекция вместо диалога)
работает и при массовом импорте CSV/XLSX. На уровне улицы похожая проверка
дублей есть в `store()` (без ограничения по правам — только предупреждение).

### LANBilling (поиск абонента)

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `GET /lanbilling/lookup` | `LanBillingController@lookup` | auth, JSON — поиск по `?phone=` или `?contract=` во внешнем биллинге |

### Настройки

Все — под `manage-settings`, кроме отдельно отмеченных.

| Метод и путь | Контроллер → метод | Комментарий |
|---|---|---|
| `GET /settings` | `SettingsController@index` | `Settings/Index.vue` — вся SPA-панель настроек одним пропс-пакетом |
| `POST/PUT/DELETE /settings/ticket-types[/{id}]` | `storeType`/`updateType`/`destroyType` | типы заявок, включая флаг `allows_promotion` |
| `POST/PUT/DELETE /settings/ticket-statuses[/{id}]` | `storeStatus`/`updateStatus`/`destroyStatus` | статусы заявок |
| `POST/PUT/DELETE /settings/users[/{id}]` | `storeUser`/`updateUser`/`destroyUser` | пользователям ролей operator/head_support/peo/logistics территории принудительно проставляются **все** — бизнес-правило "видят всё" реализовано через данные |
| `POST /settings/users/{user}/test-notify` | `testNotify` | тестовая отправка уведомления по каналу |
| `POST/PUT/DELETE /settings/services[/{id}]` | `ServiceTypeController` | участки (типы услуг) |
| `POST/PUT/DELETE /settings/promotions[/{id}]` | `PromotionController` | справочник акций на подключение |
| `PUT /settings/roles/{role}` | `updateRole` | права роли `admin` через UI не меняются |
| `PUT /settings/general` | `updateGeneral` | рабочие часы, шаг расписания, TTL вложений, рабочие дни, параметры анти-брутфорс защиты логина |
| `PUT /settings/notifications` | `updateNotifications` | вкл/выкл и время утренней сводки/вечернего отчёта |
| `POST /settings/notifications/daily-summary` \| `/evening-report` | `sendDailySummary`/`sendEveningReport` | ручной запуск рассылки прямо сейчас |
| `POST /settings/sort/service-types` \| `/territories` | `sortServiceTypes`/`sortTerritories` | порядок отображения (drag&drop в UI) |
| `GET/PUT /settings/lanbilling` | `lanbilling`/`updateLanbilling` | **пишет прямо в файл `.env`** на диске, не в БД |
| `PUT /settings/service-request-services` | `updateServiceRequestServices` | список услуг для формы "Запрос услуги" |
| `GET /settings/security/data` | `securityData` | заблокированные IP + последние попытки логина |
| `POST /settings/security/unblock` | `unblockIp` | снять блокировку IP вручную |
| `GET /settings/health/data` | `healthData` | отчёт о здоровье сервера (диск/SMART/CPU/RAM/сервисы) |

### Отчёты

Все под `manage-settings`, кроме `material-dynamics` (доступен также
`reports.view` — ПЭО/Логистика/Абонотдел). Все возвращают JSON для графиков
(Chart.js), кроме `index`.

| Метод и путь | Контроллер → метод |
|---|---|
| `GET /reports` | `ReportsController@index` — просто оболочка `Reports/Index.vue`, данные грузятся отдельными запросами ниже |
| `GET /reports/brigade-load` | `brigadeLoadData` |
| `GET /reports/territory-frequency` | `territoryFrequencyData` |
| `GET /reports/material-dynamics` | `materialDynamicsData` |
| `GET /reports/deadline-compliance` | `deadlineComplianceData` |
| `GET /reports/distribution` | `distributionData` |
| `GET /reports/call-stats` | `callStatsData` |

Плюс отчёты по материалам, переехавшие в раздел "Акты" (доступ —
`reports.view`, проверяется внутри методов, не middleware):

| Метод и путь | Контроллер → метод |
|---|---|
| `GET /acts/report/consumption` | `MaterialReportController@consumption` — расход материалов, с разбивкой по бригаде/территории/участку |
| `GET /acts/report/monthly-matrix` | `monthlyMatrix` — матрица материал × месяц |
| `GET /acts/report/forecast` | `forecast` — линейный/сезонный прогноз расхода по топ-N материалам |
| `GET /acts/report/export` | `exportCsv` — CSV-выгрузка того же, что и `consumption` |

### Акты

Документ о выполненных работах с материалами. Полный workflow:
`pending_foreman` (создан монтажником при закрытии заявки) → `approved`
(утверждён бригадиром) → office-этапы (ПЭО/Логистика/Абонотдел, только
веб) → `pending_subscriber_dept` → `completed` (архив). Подробные правила —
кто что может — в `app/Policies/ActPolicy.php`, коротко: почти все действия
проверяют роль + принадлежность к той же бригаде/территории (`scopeMatch`),
плюс у создателя акта и у admin есть отдельные исключения из общего правила
— детали см. в самом файле политики, там обширные комментарии по каждому
историческому исключению.

| Метод и путь | Контроллер → метод | Кто может |
|---|---|---|
| `GET /acts` | `ActController@index` | `ActPolicy::viewAny` — три вкладки (`active`/`archive`/`reports`) |
| `GET /acts/{act}` | `show` | `ActPolicy::view` |
| `GET /acts/{act}/print` | `print` | `ActPolicy::view`, недоступно для `pending_foreman` — PDF через DomPDF |
| `POST /acts/{act}/approve` | `approve` | бригадир (`ActPolicy::foremanReview`), только из `pending_foreman` |
| `POST /acts/{act}/process-peo` \| `/process-logistics` \| `/process-subscriber-dept` | соответствующие методы | роли ПЭО/Логистика/Абонотдел, каждая — своя "виза", независимо друг от друга |
| `POST /acts/{act}/complete` | `complete` | Абонотдел, только когда все нужные визы уже проставлены |
| `POST/PUT/DELETE /acts/{act}/materials[/{material}]` | `addMaterial`/`updateMaterial`/`removeMaterial` | бригадир (окно: `pending_foreman` и `approved`, пока ни одна офисная сторона не начала обработку) |
| `POST /acts/{act}/acknowledge` | `acknowledge` | только создатель акта, когда бригадир поправил состав после него |

### Онбординг и справка

| Метод и путь | Контроллер → метод |
|---|---|
| `POST /onboarding/{tour}/seen` | `OnboardingController@markSeen` — отметить обучающий тур пройденным/скрытым |
| `GET /help` | `HelpController@index` — статическая справка |

### Материалы (справочник)

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `GET /materials` | `MaterialController@index` | auth |
| `POST/PUT /materials[/{id}]` | `store`/`update` | `materials.manage` |
| `DELETE /materials/{id}` | `destroy` | `materials.manage` — деактивирует, не удаляет |
| `POST /tickets/{ticket}/materials` | `storeForTicket` | auth — устаревший прямой путь (новые сценарии идут через Акты) |

### Push-уведомления

| Метод и путь | Контроллер → метод |
|---|---|
| `GET /push/vapid-key` | `PushController@vapidKey` |
| `POST /push/subscribe` \| `/unsubscribe` | `subscribe`/`unsubscribe` |

### Заявки на услуги (ServiceRequests)

| Метод и путь | Контроллер → метод | Права |
|---|---|---|
| `GET /service-requests` | `index` | auth |
| `POST /service-requests` | `store` | auth |
| `PUT /service-requests/{id}` | `update` | auth, только пока `status=pending` |
| `POST /service-requests/{id}/accept` \| `/reject` | `accept`/`reject` | `manage-settings` |
| `DELETE /service-requests/{id}` | `destroy` | только `admin` |
| `GET /service-requests/{id}/detail` | `detail` | auth, JSON |

### Заявки на подключение (ConnectionRequests)

Отдельная сущность от обычных заявок (`Ticket`) — новые абоненты, которых
ещё не подключили. Свой статус-флаг (`pending`/`scheduled`/`rejected`/
`closed`/`cancelled`), своя история (`ConnectionRequestLog`).

| Метод и путь | Контроллер → метод | Комментарий |
|---|---|---|
| `GET /connection-requests` | `index` | скоуп по территории, сортировка "важности" (нужен звонок → просрочены → остальные) |
| `POST /connection-requests` | `store` | `territory_id`+`brigade_id` обязательны сразу |
| `PUT /connection-requests/{id}` | `update` | нельзя перевести в `scheduled`, пока `feasibility !== 'possible'` |
| `POST /connection-requests/{id}/feasibility` | `feasibility` | ответ монтажника/бригадира "технически возможно ли" — обязательный шаг перед назначением даты, ставит `needs_callback=true` в любом случае |
| `POST /connection-requests/{id}/mark-called` | `markCalled` | сбрасывает `needs_callback`; если до этого был ответ "невозможно" — автоматически переводит в `rejected` |
| `POST /connection-requests/{id}/close` | `close` | статус → `closed`, при наличии `materials` создаёт Акт (аналогично `tickets/{id}/close`) |
| `POST /connection-requests/{id}/add-act` | `addAct` | ретроактивное создание акта для уже закрытой заявки без акта — только бригадир своей бригады/admin |
| `POST /connection-requests/{id}/cancel` | `cancel` | статус → `cancelled` — самостоятельный отказ абонента (в отличие от `rejected` — отказ по инициативе оператора/монтажника) |
| `DELETE /connection-requests/{id}` | `destroy` | soft-delete, доступно через `?trashed=1` в `index` |
| `GET /connection-requests/{id}/detail` | `detail` | JSON, полная карточка включая soft-deleted |

### Синхронизация со старой системой

| Метод и путь | Контроллер → метод | Авторизация |
|---|---|---|
| `POST /sync/ticket` | `SyncController@store` | Bearer-токен в заголовке, без CSRF (см. `bootstrap/app.php`) — для "живых" территорий |
| `POST /sync/legacy-ticket` | `SyncController@storeLegacy` | Bearer-токен + allowlist по IP — для остальных, старых территорий |

### АТС / телефония

| Метод и путь | Контроллер → метод | Авторизация |
|---|---|---|
| `POST /api/pbx/incoming` | `PbxController@webhook` | Bearer-токен — входящий звонок, создаёт запись `Call` |
| `GET /pbx/lookup` | `PbxController@lookup` | auth — поиск последнего звонка/заявок по телефону |
| `POST /pbx/queue-status` | `queueStatus` | Bearer-токен — снапшот состояния очереди от Asterisk-скрипта |
| `POST /pbx/trigger-cmd` | `triggerCmd` | auth — поставить в очередь команду для watchdog-скрипта (`pjsip_reload` и т.п.) |
| `GET /pbx/queue-latest` | `queueLatest` | auth — лёгкий поллинг для бейджа в меню |
| `GET /pbx/queue-history` | `queueHistory` | auth — история очереди за N часов + DND-статусы |
| `GET /pbx/dnd-status` | `dndStatus` | Bearer-токен — для watchdog-скрипта |
| `POST /pbx/alert` | `alert` | Bearer-токен — ретранслятор алертов от MikoPBX в Telegram/Max (у PBX закрыт прямой исходящий HTTPS) |
| `POST /pbx/ivr-log` | `ivrLog` | Bearer-токен — лог обращений к автоинформатору |
| `POST /pbx/dnd-log` | `dndLog` | Bearer-токен — лог включения/выключения "не беспокоить" |
| `GET /ivr-log` | `IvrLogController@index` | auth — страница журнала |
| `GET /pbx/ivr-log-data` | `IvrLogController@data` | auth, JSON |
| `GET/POST/PUT/DELETE /pbx/shift-reports[...]` `/pbx/shift-definitions[...]` | `ShiftReportController` | auth (чтение), `manage-settings` (изменение определений смен, ручная пересборка отчёта) |

### Telegram-бот

| Метод и путь | Контроллер → метод |
|---|---|
| `POST /telegram/webhook` | `TelegramController@webhook` — публичный, без проверки подписи запроса |
| `GET /telegram/set-webhook` | `setWebhook` — auth, разово прописывает вебхук в Telegram API |

### Прочее

| Метод и путь | Что делает |
|---|---|
| `GET /apk/get` | Публичный, без auth. Читает `public/apk/version.json`, редиректит на актуальный `.apk` — постоянная ссылка, не меняется между релизами Android-приложения |

---

## Часть 2. Заметки по JSON-эндпоинтам (детали не описаны в Части 1)

Большинство JSON-эндпоинтов из Части 1 уже содержат всё нужное описание
(это, как правило, простые "посчитать и вернуть массив для графика/списка").
Стоит отдельно отметить особенности пары самых нетривиальных:

- **`GET /addresses/hierarchy`** и связанные — движок "проводника" по
  адресам (город→улица→дом→квартира), см. полный разбор в
  [REQUEST_LIFECYCLE.md](REQUEST_LIFECYCLE.md).
- **`GET /tickets/free-slot`** — в "нестрогом" режиме ищет ближайший
  свободный слот вперёд по дням (до 60 попыток); в "строгом" (`strict_date=1`,
  когда пользователь явно выбрал конкретную дату — например, монтажник
  вечером заносит уже выполненную заявку) — проверяет только эту дату и
  возвращает `null`, если слотов нет, не перескакивая на другой день.
- **`POST /settings/users`/`PUT /settings/users/{id}`** — для ролей
  `operator`/`head_support`/`peo`/`logistics` список территорий из формы
  **принудительно заменяется на все существующие территории**, даже если в
  форме отмечены не все — так реализовано правило "эти роли видят всё".

---

## Часть 3. Мобильный API (`/api/*`)

Полная авторитетная версия этого раздела с историей изменений по датам —
файл **`API_MOBILE.md`** в корне репозитория (им пользуется Android-
разработчик, у него есть собственная копия — **при любом изменении
мобильного API нужно поправить и его**, копия синхронизируется вручную).
Здесь — компактный пересказ текущего состояния без changelog-заметок.

**Base URL**: `https://<host>/api`. **Авторизация**: `Authorization: Bearer
<токен>`, получен через `POST /auth/login`, живёт до явного логаута.

### Аутентификация

`POST /auth/login` — `{login, password}` → `{token, user: {id, name, role}}`.
При повторном логине старый токен канала (`mobile` или `pwa` — определяется
полем `client` в запросе) удаляется, выдаётся новый — это позволяет
отдельно видеть в Настройках → Пользователи, кто заходил из приложения, а
кто из PWA.

`POST /auth/logout` — удаляет текущий токен.

### Профиль

`GET /profile` / `PUT /profile` — свои данные (`name, phone, email,
telegram_chat_id, max_chat_id, notify_on_days_off`). Роль/логин/пароль не
редактируются.

### Заявки

`GET /tickets` — 4 списка одним ответом: `overdue`, `today`, `new_today`,
`tomorrow` (плюс `synced_at`). `GET /tickets/{id}` — полная карточка.

Объект **Ticket**: `id, number, scheduled_at, closed_at, description, phone,
apartment, close_notes, act_number, act, address{full,street,building},
territory{id,name}, type, type_allows_promotion, service_type{id,name,color},
status{name,is_final,color,slug}, brigade, assignee, comments[], attachments[]`.

- `act` — `null`, если заявку закрыли без материалов; иначе краткая карточка
  акта (полная — через `GET /acts/{id}`).
- `type_allows_promotion` — можно ли предложить акцию при закрытии именно
  этой заявки (настраивается в Настройках → Типы заявок на вебе).

`POST /tickets/{id}/close` — JSON или multipart (если есть фото). Поля:
`close_notes, act_type (regular|repair), materials[], promotion_id,
attachments[]`. `act_type` обязателен, если передан непустой `materials`
(создаётся Акт). `promotion_id` допустим только если: материалы не пусты,
`act_type=regular`, и у заявки `type_allows_promotion=true`. Если у заявки
уже есть акт (переоткрывали и закрывают повторно с материалами) — `422` с
понятным сообщением вместо падения.

`POST /tickets/{id}/attachments` — добавить файлы к заявке в любой момент.
`POST /tickets/{id}/comments` — `body` и/или `attachments[]`, доступно
независимо от статуса заявки (открыта/закрыта — не важно).
`POST /tickets/{id}/reschedule` — `{scheduled_at, comment}`, дата строго в
будущем.

### Справочники

`GET /service_types`, `GET /materials`, `GET /promotions` — простые списки
активных записей для выпадающих списков в приложении.

### Заявки на подключение

`GET /connection-requests` (пагинация, фильтры `territory_id`/`status`/
`search`/`per_page`) — по умолчанию активные + закрытые не старше 2 суток.
`GET /connection-requests/{id}` — полная карточка, включая `materials` и
`logs` (последних нет в списке — только в детальной карточке).

Объект **ConnectionRequest** содержит, помимо очевидных полей: `needs_callback`
(нужно перезвонить клиенту), `feasibility`/`feasibility_comment`/
`feasibility_by_user`/`feasibility_at` (ответ монтажника на "технически
возможно ли"), `act` (аналогично Ticket).

`POST /connection-requests` — создать (`status` всегда становится `pending`).
`PUT /connection-requests/{id}` — обновить/назначить дату/отклонить;
перевод в `scheduled` заблокирован (`422`), пока `feasibility !== possible`.
`POST /connection-requests/{id}/close` — завершить, опционально с
`materials`/`promotion_id` (создаёт Акт, как у заявок).
`POST /connection-requests/{id}/mark-called` — сбросить `needs_callback`
(и авто-отклонить, если до этого монтажник ответил "невозможно").
`POST /connection-requests/{id}/feasibility` — `{answer: possible|impossible,
comment}`, доступно `technician`/`foreman`/`admin`/`head_support`.
`DELETE /connection-requests/{id}` — удалить.

### Акты — полевая часть

**В мобильном API доступна только полевая часть workflow** — от создания до
`approved`. Офисные звенья (ПЭО/Логистика/Абонотдел) работают только через
веб.

- **Монтажник** создаёт акт при закрытии заявки/подключения; не может
  редактировать свой же акт (нечего согласовывать с собой), только смотреть
  и подтверждать чужие правки (`acknowledge`).
- **Бригадир** утверждает (`approve`) и может править состав материалов на
  **любом** акте своей бригады, включая свой собственный, пока идёт
  соответствующее "окно" (`pending_foreman` или `approved`, пока ни одна
  офисная сторона не начала обработку).

`GET /acts` — очередь актов пользователя (не `completed`), с флагами `can:
{foreman_review, edit_materials, acknowledge}` под текущего пользователя —
использовать их напрямую, не дублировать логику ролей в приложении.
`GET /acts/{id}` — полная карточка с `materials[]` и `history[]`.
`POST /acts/{id}/approve` — утверждение бригадиром.
`POST/PUT/DELETE /acts/{id}/materials[/{materialId}]` — добавить/изменить/
удалить позицию материала.
`POST /acts/{id}/acknowledge` — монтажник подтверждает, что видел правки
бригадира (доступно только создателю акта, когда `materials_changed_at`
не `null`).

### Расписание бригады

`GET /schedule` — календарь на 1–3 месяца вперёд для бригады пользователя
(или указанной `brigade_id`, если есть доступ), с флагами выходных/
праздников и статусом каждого участника по дням.

### Коды ошибок

| Код | Причина |
|---|---|
| 401 | нет/неверный токен |
| 403 | нет прав (чужая заявка / не та бригада) |
| 404 | не найдено |
| 422 | ошибка валидации, тело содержит `errors` |
| 500 | серверная ошибка |

Полные примеры запросов/ответов, все нюансы по датам изменений и заметки
специально для Android-реализации — смотрите **`API_MOBILE.md`** в корне
репозитория.
