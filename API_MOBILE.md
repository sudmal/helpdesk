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

---

## Закрытие заявки

### POST /tickets/{id}/close

Принимает **JSON** или **multipart/form-data** (если нужно приложить фото).

**JSON (без фото):**
```
Content-Type: application/json

{
  "close_notes": "Заменили роутер",
  "act_number": "А-123",
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
act_number                = "А-123"
materials[0][material_id] = 5
materials[0][quantity]    = 2
attachments[]             = <file: photo1.jpg>
attachments[]             = <file: photo2.jpg>
```

Все поля опциональны. `act_number` — если не передан или пустой, сервер подставляет `"б/а"`.

**200:** полный объект ticket (включая вложения).

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
