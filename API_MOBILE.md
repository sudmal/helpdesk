# SP-Helpdesk Mobile API — Справочник

**Base URL:** `https://<host>/api`  
**Auth:** Bearer-токен в заголовке `Authorization: Bearer <token>`  
Токен получается при логине и действует до явного логаута.

---

## Аутентификация

### POST /auth/login
```json
{ "login": "user", "password": "pass" }
```
**200:**
```json
{ "token": "1|abc...", "user": { "id": 1, "name": "Иван", "role": "technician" } }
```

### POST /auth/logout
Заголовок: `Authorization: Bearer <token>`  
**200:** `{ "ok": true }`

---

## Заявки

### GET /tickets
Возвращает четыре списка заявок для текущего пользователя (бригада определяется по токену).

**200:**
```json
{
  "overdue":    [ ...ticket ],
  "today":      [ ...ticket ],
  "new_today":  [ ...ticket ],
  "tomorrow":   [ ...ticket ],
  "synced_at":  "2026-05-22T09:00:00+03:00"
}
```

### GET /tickets/{id}
**200:** полный объект ticket (см. структуру ниже)

---

## Структура объекта Ticket

```json
{
  "id": 123,
  "number": "ИНТ-00042",
  "scheduled_at": "2026-05-22T10:00:00+03:00",
  "closed_at": null,
  "description": "Не работает интернет",
  "phone": "+79991234567",
  "apartment": "15",
  "close_notes": null,
  "act_number": null,
  "act": null,
  "address": {
    "full": "ул. Ленина, 5",
    "street": "ул. Ленина",
    "building": "5"
  },
  "type": "Подключение",
  "service_type": { "id": 1, "name": "Интернет", "color": "#3b82f6" },
  "status": { "name": "В работе", "is_final": false, "color": "#f59e0b", "slug": "in_progress" },
  "brigade": "Бригада 1",
  "assignee": "Иванов И.И.",
  "comments": [
    {
      "id": 5,
      "body": "Позвонил клиенту",
      "author": "Иванов И.И.",
      "created_at": "2026-05-22T09:30:00+03:00",
      "attachments": [
        {
          "id": 14,
          "original_name": "screen.jpg",
          "url": "https://<host>/storage/tickets/123/attachments/uuid.jpg",
          "mime_type": "image/jpeg",
          "size": 102400
        }
      ]
    }
  ],
  "attachments": [
    {
      "id": 12,
      "original_name": "photo1.jpg",
      "url": "https://<host>/storage/tickets/123/attachments/uuid.jpg",
      "mime_type": "image/jpeg",
      "size": 204800
    }
  ]
}
```

> **`url`** — абсолютный, готов к использованию напрямую.  
> Комментарии без вложений возвращают `"attachments": []`.  
> **`act`** — `null`, если заявку закрыли без материалов; иначе краткая карточка акта (полная — через `GET /acts/{id}`, см. раздел «Акты» ниже).

---

## Закрытие заявки

### POST /tickets/{id}/close

Принимает **JSON** или **multipart/form-data** (если нужно приложить фото).

Если переданы `materials` — сервер создаёт полноценный **Акт** (см. раздел «Акты»)
со статусом `pending_foreman` и АВТОМАТИЧЕСКИ генерирует его номер
(`Act::generateNumber`, схема `<буква участка><r|v>-YYMMDDNN`, например `ir-26071601`).
**Номер акта на вход не принимается** — если материалов нет, акт вообще не создаётся.

**JSON (без фото):**
```
Content-Type: application/json

{
  "close_notes": "Заменили роутер",
  "act_type": "regular",
  "materials": [
    { "material_id": 5, "quantity": 2 },
    { "material_id": 8, "quantity": 1 }
  ]
}
```

**multipart/form-data (с фото):**
```
Content-Type: multipart/form-data

close_notes               = "Заменили роутер"
act_type                  = "regular"
materials[0][material_id] = 5
materials[0][quantity]    = 2
attachments[]             = <file: photo1.jpg>
attachments[]             = <file: photo2.jpg>
```

`close_notes`, `act_type`, `materials`, `attachments` — все опциональны, **кроме**:
`act_type` обязателен (`regular` — обычный акт, `repair` — ремонт/восстановление),
**если** передан непустой `materials`. Без материалов `act_type` не нужен и акт не создаётся.

**200:** полный объект ticket (включая вложения и `act`, если он был создан).

После закрытия с материалами акт уходит бригадиру на утверждение — см. «Акты» ниже:
приложению стоит показать монтажнику статус акта на карточке заявки (поле `ticket.act.status`)
и уведомлять его, когда `act.materials_changed_at` не `null` (бригадир поправил состав, надо подтвердить).

---

## Загрузка вложений

### POST /tickets/{id}/attachments

Загрузка файлов к заявке. Можно вызывать в любой момент — до или после закрытия.

```
Content-Type: multipart/form-data

attachments[]  = <file: photo1.jpg>
attachments[]  = <file: photo2.jpg>
```

Ограничения: форматы `jpeg/jpg/png/gif/pdf`, до 20 МБ на файл, до 10 файлов за раз.

**201:**
```json
{
  "attachments": [
    {
      "id": 12,
      "original_name": "photo1.jpg",
      "url": "https://<host>/storage/tickets/123/attachments/uuid.jpg",
      "mime_type": "image/jpeg",
      "size": 204800
    }
  ]
}
```

---

## Комментарии

### POST /tickets/{id}/comments

Добавить комментарий с опциональными вложениями.  
Нужно передать `body` или `attachments[]` (или оба).

**С фото (multipart/form-data):**
```
body           = "Проверил линию — обрыв на 3 этаже"
attachments[]  = <file: photo.jpg>
```

**Только текст (application/json):**
```json
{ "body": "Текст комментария" }
```

**201:**
```json
{
  "id": 7,
  "body": "Проверил линию — обрыв на 3 этаже",
  "author": "Иванов И.И.",
  "created_at": "2026-05-22T10:15:00+03:00",
  "attachments": [
    {
      "id": 13,
      "original_name": "photo.jpg",
      "url": "https://<host>/storage/tickets/123/attachments/uuid.jpg",
      "mime_type": "image/jpeg",
      "size": 153600
    }
  ]
}
```

Комментарий без вложений возвращает `"attachments": []`.

---

## Перенос заявки

### POST /tickets/{id}/reschedule

```json
{
  "scheduled_at": "2026-05-23 14:00:00",
  "comment": "Клиент просил перенести"
}
```

`scheduled_at` — обязательный, дата/время строго в будущем.  
**200:** полный объект ticket.

---

## Справочники

### GET /service_types
```json
[{ "id": 1, "name": "Интернет", "color": "#3b82f6" }]
```

### GET /materials
```json
[{ "id": 5, "code": "RJ45", "name": "Коннектор RJ-45", "unit": "шт", "price": 15.00 }]
```

---

## Рекомендуемый порядок при закрытии заявки с фото

**Вариант А — один запрос (проще):**
```
POST /tickets/{id}/close   multipart/form-data
  close_notes   = "..."
  act_number    = "..."
  attachments[] = photo1.jpg
  attachments[] = photo2.jpg
```

**Вариант Б — два запроса (если фото загружаются заранее):**
```
1. POST /tickets/{id}/attachments   → фото уже привязаны к заявке
2. POST /tickets/{id}/close         → JSON без файлов
```

Оба варианта корректны. Вариант А рекомендуется как более простой.

---


---

## Подключения

### GET /connection-requests

Список заявок на подключение для текущего пользователя (территории определяются по токену).

**Фильтр по умолчанию:** активные (`pending`, `scheduled`, `rejected`) + закрытые (`closed`) не старше 2 суток.

**Query-параметры (все опциональны):**

| Параметр | Описание |
|----------|----------|
| `territory_id` | Фильтр по территории |
| `status` | `pending` / `scheduled` / `rejected` / `closed` |
| `search` | Поиск по имени, телефону, адресу |
| `per_page` | Размер страницы (по умолчанию 50, макс. 100) |
| `page` | Номер страницы |

**200:**
```json
{
  "data": [ ...ConnectionRequest ],
  "current_page": 1,
  "last_page": 3,
  "total": 120,
  "territories": [
    { "id": 1, "name": "Северный район" }
  ],
  "synced_at": "2026-06-12T10:00:00+03:00"
}
```

### GET /connection-requests/{id}

**200:** полный объект `ConnectionRequest` (включая `materials`)

---

## Структура объекта ConnectionRequest

```json
{
  "id": 42,
  "name": "Иванов Иван Иванович",
  "phone": "+79991234567",
  "address_string": "ул. Ленина, 5, кв. 10",
  "description": "Хочет подключить интернет, 3 этаж",
  "status": "scheduled",
  "scheduled_at": "2026-06-15T14:00:00+03:00",
  "notes": null,
  "act_number": null,
  "act": null,
  "needs_callback": true,
  "territory": { "id": 1, "name": "Северный район" },
  "service_type": { "id": 1, "name": "Интернет", "color": "#3b82f6" },
  "creator": "Диспетчер Сидорова А.П.",
  "assigned_to": 5,
  "assignee": { "id": 5, "name": "Монтажник Петров И.В." },
  "created_at": "2026-06-10T09:30:00+03:00",
  "updated_at": "2026-06-11T11:00:00+03:00",
  "materials": [
    {
      "id": 1,
      "material_id": 5,
      "name": "Коннектор RJ-45",
      "code": "RJ45",
      "unit": "шт",
      "price_at_time": 15.00,
      "quantity": 2.0,
      "total": 30.00
    }
  ]
}
```

> `materials` возвращается только в `/connection-requests/{id}` и ответах экшн-эндпоинтов (`/close`, `/update`).  
> В списке (`GET /connection-requests`) поле `materials` **отсутствует**.  
> **`act`** — `null`, если заявку ещё не закрывали материалами; иначе краткая карточка акта
> (`id`, `number`, `status`, `materials_changed_at`) — полная версия через `GET /acts/{id}`, см. «Акты» ниже.  
> **`act_number`** — оставлено для обратной совместимости со старыми сборками приложения;
> для заявок, закрытых материалами, дублирует `act.number`. Для новых интеграций используйте `act`.

**Статусы:**

| Значение | Отображение | Терминальный |
|----------|-------------|:---:|
| `pending` | Ожидает | — |
| `scheduled` | Назначено | — |
| `rejected` | Отклонено | ✓ |
| `closed` | Выполнено | ✓ |

**Флаг `needs_callback`:** `true` — клиента нужно прозвонить (подтвердить визит или сообщить об отклонении). Устанавливается автоматически при смене статуса на `scheduled`/`rejected`. Сбрасывается через `/mark-called` или при закрытии (`/close`).

---

### POST /connection-requests

Создать новую заявку. Статус автоматически `pending`.

```json
{
  "name":            "Петров Пётр Петрович",
  "phone":           "+79991112233",
  "address_string":  "ул. Советская, 10, кв. 5",
  "description":     "Хочет интернет и кабельное ТВ",
  "territory_id":    1,
  "service_type_id": 1
}
```

**Валидация:** `name` макс. 100 · `phone` макс. 30 · `address_string` макс. 255 · `territory_id` обязателен · `service_type_id` опционален при создании, но **обязателен до закрытия с материалами** (см. `/close` ниже) — лучше запрашивать его сразу в форме создания, чтобы не возвращаться к этому на этапе закрытия.

**201:** полный объект `ConnectionRequest`

---

### PUT /connection-requests/{id}

Обновить данные или статус. Все поля опциональны.

**Назначить дату (→ scheduled):**
```json
{
  "status": "scheduled",
  "scheduled_at": "2026-06-15T14:00:00+03:00",
  "notes": "Договорились на 14:00"
}
```

**Отклонить (→ rejected):**
```json
{
  "status": "rejected",
  "notes": "Технически невозможно — нет кабеля в доме"
}
```

**Изменить контактные данные:**
```json
{
  "name": "Иванов И.И.",
  "phone": "+79991234567",
  "address_string": "ул. Ленина, 5, кв. 10",
  "description": "Уточнённое описание",
  "territory_id": 2,
  "service_type_id": 1
}
```

> При смене статуса на `scheduled` или `rejected` сервер автоматически ставит `needs_callback = true`.  
> Закрытие через PUT **недоступно** — используй `/close`.

**200:** полный объект `ConnectionRequest` (включая `materials`)

---

### POST /connection-requests/{id}/close

Завершить подключение. Устанавливает статус `closed`.

Если переданы `materials` — сервер создаёт полноценный **Акт** (см. «Акты» ниже)
со статусом `pending_foreman`, типом `regular` и автоматически сгенерированным
номером (`in-YYMMDDNN` для интернета / `cn-YYMMDDNN` для КТВ — берётся из
`service_type_id` заявки). **Номер акта на вход не принимается.**

```json
{
  "notes": "Проложили кабель на 3 этаж, настроили роутер.",
  "materials": [
    { "material_id": 5, "quantity": 2 },
    { "material_id": 8, "quantity": 1 }
  ]
}
```

- `notes` и `materials` опциональны.
- **Если переданы `materials`, у заявки должен быть заполнен `service_type_id`**
  (см. `POST`/`PUT` выше) — иначе сервер вернёт `422` с полем `service_type_id` в `errors`.
  Приложению стоит не показывать форму материалов вовсе, пока участок не выбран,
  либо запросить его прямо на экране закрытия.
- Материалы попадают в `ActMaterial` акта, а не пишутся на саму заявку —
  `GET /connection-requests/{id}` их больше не хранит после закрытия, смотрите `act`.

**200:** полный объект `ConnectionRequest` (поле `act` заполнено, если были материалы).

**422 (нет участка, но переданы материалы):**
```json
{
  "message": "У заявки не указан участок (тип услуги) — укажите его перед закрытием с материалами.",
  "errors": { "service_type_id": ["У заявки не указан участок (тип услуги)."] }
}
```

---

### POST /connection-requests/{id}/mark-called

Отметить, что клиент прозвонен — сбрасывает `needs_callback`. Тело пустое.

**200:**
```json
{ "message": "Отмечено: прозвонили" }
```

---

### DELETE /connection-requests/{id}

Удалить заявку.

**200:**
```json
{ "message": "Заявка удалена" }
```

## Акты

Акт — документ о выполненных работах с использованными материалами. Создаётся
автоматически при закрытии заявки (`/tickets/{id}/close`) или заявки на
подключение (`/connection-requests/{id}/close`) **с материалами** — см. выше.

**Полный workflow акта** (веб + мобильное): `pending_foreman` → `approved` →
(office-звенья ПЭО/Логистика, только веб) → `pending_subscriber_dept` →
`completed`. **В мобильном API доступна только полевая часть** — от создания
до `approved`. Остальные звенья (ПЭО/Логистика/Абонотдел) — офисные роли,
работают исключительно через веб-версию, в приложении им делать нечего.

**Роли на поле:**
- **Монтажник** (`technician`) — создаёт акт при закрытии заявки/подключения.
  Не может редактировать акт, который сам создал (нечего согласовывать
  с самим собой) — только смотреть и, если бригадир его поправил, подтвердить (`acknowledge`).
- **Бригадир** (`foreman`) — утверждает акт (`approve`) и может, пока акт в
  статусе `pending_foreman`, поправить состав материалов (добавить/изменить
  количество/удалить). После правки на акте выставляется `materials_changed_at`,
  и монтажнику стоит показать значок «есть изменения — подтвердите» на карточке
  заявки/акта, пока он не вызовет `acknowledge`.

### GET /acts/{id}

**200:**
```json
{
  "id": 77,
  "number": "ir-26071601",
  "type": "regular",
  "status": "pending_foreman",
  "ticket_id": 123,
  "connection_request_id": null,
  "created_at": "2026-07-16T14:00:00+03:00",
  "creator": "Монтажник Петров И.В.",
  "foreman_reviewed_at": null,
  "foreman_reviewed_by": null,
  "materials_changed_at": null,
  "materials": [
    {
      "id": 501,
      "material_id": 5,
      "name": "Коннектор RJ-45",
      "code": "RJ45",
      "unit": "шт",
      "price_at_time": 15.00,
      "quantity": 2.0,
      "total": 30.00
    }
  ],
  "history": [
    {
      "id": 9001,
      "user": "Монтажник Петров И.В.",
      "action": "created",
      "field": null,
      "old_value": null,
      "new_value": null,
      "acknowledged_at": null,
      "created_at": "2026-07-16T14:00:00+03:00"
    }
  ],
  "can": {
    "foreman_review": true,
    "edit_materials": false,
    "acknowledge": false
  }
}
```

`can` — три флага под текущего пользователя (сервер уже применил ActPolicy с
учётом роли/бригады/территории/статуса акта) — используйте их напрямую вместо
того, чтобы дублировать логику ролей в приложении:
- `foreman_review` — можно вызвать `/approve` (бригадир, статус `pending_foreman`)
- `edit_materials` — можно добавлять/менять/удалять материалы (бригадир, статус
  `pending_foreman`, и это не тот акт, который бригадир создал сам себе)
- `acknowledge` — есть неподтверждённые правки бригадира, можно вызвать `/acknowledge`
  (монтажник-создатель акта, `materials_changed_at` не `null`)

`action` в `history`: `created`, `approved`, `material_added`, `material_changed`,
`material_removed`, `acknowledged` (плюс office-действия `peo_processed`/
`logistics_processed`/`completed` — попадаются в истории старых/дальше‑ушедших
по workflow актов, мобильному приложению их достаточно просто отображать текстом).

### POST /acts/{id}/approve

Бригадир утверждает акт. Доступно только из `pending_foreman`. Тело пустое.

**200:** полный объект акта (статус сменится на `approved`, `foreman_reviewed_at/by` заполнятся).  
**403:** не бригадир этой бригады / акт не в `pending_foreman`.

### POST /acts/{id}/materials

Бригадир добавляет позицию материала. Доступно только пока акт `pending_foreman`
и это не акт, который бригадир создал сам.

```json
{ "material_id": 5, "quantity": 2 }
```

**201:** полный объект акта.

### PUT /acts/{id}/materials/{materialId}

Изменить количество существующей позиции.

```json
{ "quantity": 5 }
```

**200:** полный объект акта.

### DELETE /acts/{id}/materials/{materialId}

Удалить позицию из акта. Тело пустое.

**200:** полный объект акта.

> Любое из трёх действий выше (добавить/изменить/удалить материал) выставляет
> `materials_changed_at` на акте — **кроме случая**, когда акт редактирует тот
> же пользователь, который его создал (не бывает на практике: создателю
> `edit_materials` вообще запрещён политикой), поэтому на практике флаг
> поднимается всегда, когда бригадир правит акт монтажника.

### POST /acts/{id}/acknowledge

Монтажник подтверждает, что увидел правки бригадира. Доступно, только если
`materials_changed_at` не `null` и вызывающий — создатель акта. Тело пустое.

**200:** полный объект акта (`materials_changed_at` сбрасывается в `null`,
непрочитанные записи `history` получают `acknowledged_at`).

---

## Коды ошибок

| Код | Причина |
|-----|---------|
| 401 | Нет или неверный токен |
| 403 | Нет прав на операцию (чужая заявка / не та бригада) |
| 404 | Заявка не найдена |
| 422 | Ошибка валидации — тело содержит `errors` |
| 500 | Серверная ошибка |

**422 пример:**
```json
{
  "message": "The attachments.0 field must be a file of type: jpeg, jpg, png, gif, pdf.",
  "errors": {
    "attachments.0": ["The attachments.0 field must be a file of type: ..."]
  }
}
```

---

## Заметки по реализации на Android

- Для multipart в Retrofit используй `@Multipart` + `@Part("attachments[]") List<MultipartBody.Part>`
- Файл создаётся через `MultipartBody.Part.createFormData("attachments[]", filename, requestBody)`
- `url` в ответе — абсолютный, строить полный URL не нужно
- Токен храни в `EncryptedSharedPreferences`; при повторном логине старый токен `mobile` удаляется
- Все даты в ISO 8601, таймзона сервера — Москва (+03:00)
- Акт — отдельная сущность от заявки/подключения, но всегда однозначно к одной
  из них привязан (`ticket_id` xor `connection_request_id`). Проще всего
  открывать карточку акта по `ticket.act.id` / `connectionRequest.act.id` и
  дальше работать с `/acts/{id}` независимо от того, откуда акт появился.
- Бэйдж «есть непрочитанные правки акта» — считать по `act.materials_changed_at !== null`
  на заявках/подключениях текущего пользователя (уже приходит в списках
  `GET /tickets` и `GET /connection-requests`, отдельный запрос не нужен).
