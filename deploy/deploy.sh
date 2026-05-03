#!/bin/bash
set -e

SITE_DIR="/var/www/sites/helpdesk"
DOMAIN="helpdesk.browsersdnr.ru"   # <-- замените на ваш домен
PHP="php8.2"
apt install -y php8.2-redis


echo "======================================"
echo " HelpDesk Deploy Script"
echo "======================================"

cd "$SITE_DIR"

# ── 1. Права на директории ─────────────────────────────────────────────
echo "[1/8] Права на storage и bootstrap/cache..."
chown -R www-data:www-data "$SITE_DIR"
chmod -R 755 "$SITE_DIR"
chmod -R 775 "$SITE_DIR/storage"
chmod -R 775 "$SITE_DIR/bootstrap/cache"

# ── 2. Composer зависимости ────────────────────────────────────────────
echo "[2/8] Composer install..."
COMPOSER_ALLOW_SUPERUSER=1 composer require predis/predis
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# ── 3. NPM сборка ──────────────────────────────────────────────────────
echo "[3/8] NPM build..."
npm ci --silent
npm run build

# ── 4. Laravel оптимизация ────────────────────────────────────────────
echo "[4/8] Laravel optimize..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache 2>/dev/null || true

# ── 5. Миграции ────────────────────────────────────────────────────────
echo "[5/8] Migrations..."
php artisan migrate --force --seed

# ── 6. Storage link ────────────────────────────────────────────────────
echo "[6/8] Storage link..."
php artisan storage:link 2>/dev/null || true

# ── 7. nginx ──────────────────────────────────────────────────────────
echo "[7/8] nginx config..."
cp "$SITE_DIR/deploy/nginx.conf" "/etc/nginx/sites-available/helpdesk"
sed -i "s/helpdesk.example.com/$DOMAIN/g" /etc/nginx/sites-available/helpdesk
ln -sf /etc/nginx/sites-available/helpdesk /etc/nginx/sites-enabled/helpdesk
nginx -t && systemctl reload nginx

# ── 8. Supervisor ──────────────────────────────────────────────────────
echo "[8/8] Supervisor..."
cp "$SITE_DIR/deploy/supervisor-helpdesk.conf" /etc/supervisor/conf.d/helpdesk.conf
supervisorctl reread
supervisorctl update
supervisorctl start helpdesk-worker:*
supervisorctl start helpdesk-scheduler

echo ""
echo "======================================"
echo " Деплой завершён!"
echo " Сайт: https://$DOMAIN"
echo "======================================"
