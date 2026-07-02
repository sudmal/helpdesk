PHP файлы (app/, routes/, config/, database/ и т.д.)

Сохранил файл → сразу работает на тесте (Z:\ это прямой маунт). Затем:


php artisan cache:clear        # если трогал настройки/роуты
git add -u
git commit -m "..."
git push origin master
На проде:


git pull
/usr/local/bin/php8.2 artisan cache:clear
Vue / CSS / JS файлы (resources/js/, resources/css/)

После сохранения нужна сборка — браузер видит только public/build/:


npm run build
git add -f public/build/
git add -u
git commit -m "..."
git push origin master
На проде:


git pull
(Node.js на проде нет — сборка только на тесте, public/build/ коммитится в git)

Миграции (database/migrations/)


php artisan migrate
git add -u
git commit -m "..."
git push origin master
На проде:


git pull
/usr/local/bin/php8.2 artisan migrate
Правило: если правишь только PHP — npm run build не нужен. Если правишь Vue/CSS — без сборки изменения не появятся в браузере.
