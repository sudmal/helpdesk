# Установка SSL (Let's Encrypt)

## 1. Установить certbot

```bash
apt update
apt install -y certbot python3-certbot-nginx
```

## 2. Сначала — nginx без SSL (для прохождения challenge)

Временно замените конфиг на простой HTTP-only:

```nginx
server {
    listen 80;
    server_name helpdesk.example.com;
    root /var/www/sites/helpdesk/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```bash
nginx -t && systemctl reload nginx
```

## 3. Получить сертификат

```bash
certbot --nginx -d helpdesk.example.com
```

Certbot сам:
- Получит сертификат
- Пропишет пути в nginx конфиг
- Настроит автообновление

## 4. Переключиться на финальный конфиг

```bash
cp /var/www/sites/helpdesk/deploy/nginx.conf /etc/nginx/sites-available/helpdesk
# Отредактируйте домен если нужно
nano /etc/nginx/sites-available/helpdesk
nginx -t && systemctl reload nginx
```

## 5. Проверить автообновление

```bash
certbot renew --dry-run
```

## Автообновление через cron (если не настроилось автоматически)

```bash
crontab -e
# Добавить:
0 3 * * * certbot renew --quiet && systemctl reload nginx
```
