# Команды для запуска на сервере

## После загрузки файлов из archives

```bash
cd /var/www/sites/helpdesk

# 1. ОБЯЗАТЕЛЬНО добавить в .env если ещё нет:
grep APP_TIMEZONE .env || echo "APP_TIMEZONE=Europe/Moscow" >> .env

# 2. Запустить новые миграции
php artisan migrate

# 3. Очистить весь кэш
php artisan config:clear
php artisan cache:clear
php artisan route:clear
rm -f bootstrap/cache/*.php

# 4. Пересобрать фронт
npm run build

# 5. Перезапустить php-fpm
systemctl restart php8.2-fpm
```

## Если логин не отображается у существующих пользователей

Для каждого пользователя нужно задать логин через Settings → Пользователи → Редактировать.
Либо вручную через MySQL:
```sql
UPDATE users SET login = email WHERE login IS NULL;
```
