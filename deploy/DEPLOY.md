# HelpDesk — Руководство по деплою

## Быстрый старт (исправление текущей проблемы "Not Found")

Проблема: nginx не перенаправляет запросы в index.php.

```bash
# 1. Скопировать конфиг
cp /var/www/sites/helpdesk/deploy/nginx.conf /etc/nginx/sites-available/helpdesk

# 2. Заменить домен
nano /etc/nginx/sites-available/helpdesk
# Замените helpdesk.example.com на ваш домен или IP

# 3. Активировать
ln -sf /etc/nginx/sites-available/helpdesk /etc/nginx/sites-enabled/helpdesk

# 4. Удалить дефолтный сайт если мешает
rm -f /etc/nginx/sites-enabled/default

# 5. Проверить и перезагрузить
nginx -t && systemctl reload nginx
```

## Если SSL ещё не настроен — временный HTTP конфиг

Замените содержимое `/etc/nginx/sites-available/helpdesk` на:

```nginx
server {
    listen 80;
    server_name _;          # или ваш IP/домен
    root /var/www/sites/helpdesk/public;
    index index.php;

    client_max_body_size 110M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        access_log off;
    }

    location ~ /\. { deny all; }
}
```

```bash
nginx -t && systemctl reload nginx
```

## Supervisor (очереди + планировщик)

```bash
# Установить supervisor если нет
apt install -y supervisor

# Скопировать конфиг
cp /var/www/sites/helpdesk/deploy/supervisor-helpdesk.conf /etc/supervisor/conf.d/helpdesk.conf

# Создать папку для логов
mkdir -p /var/log/supervisor

# Применить
supervisorctl reread
supervisorctl update

# Запустить воркеры
supervisorctl start helpdesk-worker:*
supervisorctl start helpdesk-scheduler

# Проверить статус
supervisorctl status
```

## Cron (альтернатива supervisor для планировщика)

Если не используете supervisor для scheduler:

```bash
crontab -e -u www-data
# Добавить:
* * * * * cd /var/www/sites/helpdesk && php artisan schedule:run >> /dev/null 2>&1
```

## Права доступа

```bash
chown -R www-data:www-data /var/www/sites/helpdesk
chmod -R 755 /var/www/sites/helpdesk
chmod -R 775 /var/www/sites/helpdesk/storage
chmod -R 775 /var/www/sites/helpdesk/bootstrap/cache
```

## .env для продакшна

```bash
APP_ENV=production
APP_DEBUG=false           # ОБЯЗАТЕЛЬНО false в продакшне!
APP_URL=https://ваш-домен

SESSION_DRIVER=database   # или redis
QUEUE_CONNECTION=redis    # для очередей уведомлений
CACHE_DRIVER=redis
```

## Оптимизация Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## Обновление (git pull + пересборка)

```bash
cd /var/www/sites/helpdesk
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart helpdesk-worker:*
```

## Проверка работоспособности

```bash
# Очереди работают?
supervisorctl status helpdesk-worker:*

# Планировщик работает?
php artisan schedule:list

# Логи ошибок
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/helpdesk.error.log

# Тест отправки уведомления
php artisan helpdesk:daily-summary --date=$(date +%Y-%m-%d)
```
