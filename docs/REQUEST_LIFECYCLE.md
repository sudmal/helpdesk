# Путь запроса: от браузера до готовой страницы

Этот документ разбирает пошагово, что происходит между "пользователь
кликнул/открыл ссылку" и "на экране готовая страница", на конкретных
примерах из проекта. Предполагается, что вы уже прочитали
[ARCHITECTURE.md](ARCHITECTURE.md) и знаете, что такое Laravel/Vue/Inertia
на базовом уровне.

В этом приложении бывает **четыре принципиально разных типа запроса**, и
важно уметь их различать — путь у них разный:

| Тип | Пример | Что возвращает сервер |
|---|---|---|
| **A. Первая загрузка страницы** | вбили URL в адресную строку, обновили страницу (F5) | Полный HTML-документ |
| **B. Переход внутри уже открытого сайта** | клик по ссылке/пункту меню после того как сайт уже загрузился | Чистый JSON (Inertia) |
| **C. "Живой" AJAX-запрос внутри страницы** | поиск, автодополнение, кнопка без перезагрузки блока | Чистый JSON (обычный, не Inertia) |
| **D. Запрос от мобильного приложения / PWA** | Android-приложение, `app.vega8.ru` | Чистый JSON (REST API) |

Разберём каждый на реальном примере.

---

## A. Первая загрузка страницы: `GET /addresses`

Представим, что пользователь набирает `https://vega8.ru/addresses` в адресной
строке браузера и жмёт Enter.

### 1. nginx

nginx получает HTTPS-запрос. Смотрит на путь `/addresses` — это не файл с
расширением `.js/.css/.png` и не существующий файл на диске, значит по
правилу `try_files $uri $uri/ /index.php?$query_string` запрос уходит в
`index.php` с исходной строкой запроса как query string. nginx передаёт его
дальше в PHP-FPM по FastCGI (`fastcgi_pass unix:/var/run/php/php8.2-fpm.sock`).

### 2. `public/index.php` — точка входа

Единственный файл, который PHP реально исполняет напрямую для веб-запросов.
Он подключает автозагрузчик Composer и запускает `bootstrap/app.php`, которое
собирает объект приложения Laravel: регистрирует три группы маршрутов
(`routes/web.php`, `routes/api.php`, `routes/console.php`), middleware (см.
ниже) и провайдеры. Дальше приложение обрабатывает текущий HTTP-запрос и
отправляет ответ — это единственное, что видит внешний мир из всей огромной
папки `app/`.

### 3. Роутер сопоставляет URL с контроллером

Laravel проходит по `routes/web.php` сверху вниз и ищет совпадение метода +
пути. Находит:

```php
// routes/web.php, внутри Route::middleware(['auth', 'active'])->group(...)
Route::prefix('addresses')->name('addresses.')->group(function () {
    Route::get('/', [AddressController::class, 'index'])->name('addresses.index');
    // ...
});
```

То есть `GET /addresses` → метод `index()` класса `AddressController`.

### 4. Middleware — проверки до контроллера

Прежде чем выполнится сам `AddressController::index()`, запрос проходит
цепочку middleware (каждый может остановить запрос раньше, вернув свой
ответ — например, редирект на `/login`):

1. Стандартные Laravel middleware веб-группы: сессии/куки, CSRF-токен и т.д.
2. `auth` — если пользователь не залогинен (нет валидной сессии), редирект на
   `/login`, дальше запрос не идёт.
3. `active` (алиас `EnsureUserIsActive`, `app/Http/Middleware/EnsureUserIsActive.php`)
   — если пользователя деактивировали (уволен и т.п.), разлогинивает и
   отправляет на `/login`.
4. `HandleInertiaRequests` (`app/Http/Middleware/HandleInertiaRequests.php`) —
   собирает "общие для всех страниц" данные: кто залогинен (`auth.user`),
   flash-сообщения (`flash.success/error`), счётчики для бейджей в меню
   (`connectionAlerts`, `actsAlerts` и т.д.). Они будут доступны в **любом**
   Vue-компоненте через `usePage().props`, не только в том, что вернул именно
   этот контроллер — не нужно прокидывать их вручную из каждого метода.

### 5. Контроллер выполняется

```php
// app/Http/Controllers/AddressController.php
public function index(Request $request)
{
    // ... читает query-параметры (?city=, ?street=...),
    // через Eloquent достаёт нужный уровень иерархии адресов из БД
    return Inertia::render('Addresses/Index', [
        'territories' => Territory::all(...),
        'cityList'    => $cityList,
        // ...
    ]);
}
```

Контроллер обращается к моделям (`Address::...`, `Territory::...`), которые
идут в MySQL за данными через Eloquent (никакого сырого SQL). Собирает всё
нужное в массив и передаёт в `Inertia::render(компонент, пропсы)`.

### 6. `Inertia::render()` — развилка

Это ключевой момент, ради которого стоит прочитать этот раздел: один и тот
же вызов `Inertia::render()` ведёт себя по-разному в зависимости от того,
есть ли в запросе специальный заголовок `X-Inertia: true`.

**В нашем случае (первая загрузка, обычный переход по URL) заголовка нет** —
значит это "тип A", и Inertia рендерит **обычную Blade-страницу**
(`resources/views/app.blade.php`):

```blade
<!DOCTYPE html>
<html lang="ru">
<head>
    ...
    @routes            {{-- Ziggy: список всех маршрутов в JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia           {{-- <div id="app" data-page="{...JSON...}"></div> --}}
</body>
</html>
```

Директива `@inertia` вставляет `<div id="app">` с атрибутом
`data-page`, в котором лежит **весь JSON**, который вернул контроллер:
имя компонента (`"Addresses/Index"`) + пропсы + текущий URL + версия
ассетов. `@vite(...)` вставляет `<script>`/`<link>` теги на собранные
JS/CSS-бандлы (через `public/build/manifest.json`, см.
[ARCHITECTURE.md](ARCHITECTURE.md#36-vite--сборка-фронтенда)).

Этот HTML целиком уходит в браузер. На этом заканчивается работа PHP для
этого запроса.

### 7. Браузер оживляет страницу

1. Браузер парсит HTML, видит `<script>` на `app-XXXXX.js`, загружает и
   выполняет его.
2. `resources/js/app.js` вызывает `createInertiaApp(...)`:
   ```js
   createInertiaApp({
       resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, ...),
       setup({ el, App, props, plugin }) {
           createApp({ render: () => h(App, props) }).use(plugin).use(ZiggyVue).mount(el)
       },
   })
   ```
   Inertia сама читает `data-page` из DOM, видит `component: "Addresses/Index"`,
   находит файл `resources/js/Pages/Addresses/Index.vue` (через
   `import.meta.glob`), и монтирует Vue-приложение в `<div id="app">`,
   передав пропсы из `data-page` как входные `props` компонента.
3. Компонент `Addresses/Index.vue` получает пропсы через `defineProps({...})`,
   его `<template>` рендерится в реальный DOM на основе этих пропсов и
   собственного локального состояния (`ref`/`reactive`), стили — из
   Tailwind-классов прямо в разметке.

**Итог**: пользователь видит полностью готовую страницу. Всё, что было в
адресной строке от `nginx` до этого момента, заняло обычно меньше секунды.

---

## B. Переход внутри уже загруженного сайта

Пользователь уже на странице `/addresses` (SPA загружено и работает). Он
кликает на город в списке — во Vue-коде это вызывает `navigate({ city })`,
которая внутри делает `router.get(route('addresses.index'), { city })`
(`router` — это Inertia-роутер на клиенте, не обычный `<a href>`).

Дальше происходит **ровно тот же путь**, что в разделе A, шаги 1–5: тот же
URL, тот же контроллер `AddressController::index()`, те же middleware
(`auth`, `active`, `HandleInertiaRequests`). Разница только в шаге 6:

- Клиентский Inertia-роутер делает не обычную навигацию браузера, а `fetch()`
  с заголовками `X-Inertia: true` и `X-Inertia-Version: <хэш>`.
- `Inertia::render()` на сервере видит этот заголовок и **не рендерит
  `app.blade.php`** — вместо HTML-страницы отдаёт **чистый JSON**:
  ```json
  { "component": "Addresses/Index", "props": {...}, "url": "/addresses?city=Донецк", "version": "..." }
  ```
- Браузер получает этот JSON, Inertia на клиенте меняет текущий компонент и
  пропсы (Vue сам перерисовывает то, что изменилось — реактивность), и
  вызывает `history.pushState`, чтобы адресная строка и кнопка "назад"
  работали как обычно.

**Итог**: страница обновилась без единой полной перезагрузки — не мигнул
браузерный таб, не перезагрузился JS, но по факту всё равно отработал тот же
самый Laravel-контроллер на сервере, просто ответ был компактнее (без HTML
вокруг).

Как это увидеть своими глазами: откройте DevTools → Network, кликните по
любой ссылке внутри уже открытого сайта — увидите запрос с заголовком
`X-Inertia: true` и ответом `Content-Type: application/json`, а не обычный
HTML-документ.

**Версионирование (`X-Inertia-Version`)**: если после деплоя новой версии
фронтенда (`npm run build`) хэш ассетов изменился, а у пользователя в
браузере всё ещё открыта старая вкладка — при следующей Inertia-навигации
сервер вернёт другой `version`, и Inertia на клиенте сама сделает полную
перезагрузку страницы (`window.location`), чтобы подтянуть новый JS. Поэтому
не нужно просить пользователей руками жать Ctrl+F5 после деплоя фронтенда —
это происходит само при следующем переходе.

---

## C. Обычный AJAX (axios), не через Inertia

Не все данные на странице должны приходить через `Inertia::render()`. Когда
кусок интерфейса должен обновляться сам по себе, без смены "страницы"
целиком (поиск, автодополнение, модалка с быстрым действием) — используется
обычный `axios`-запрос, и контроллер в этом случае отвечает не
`Inertia::render`, а напрямую `response()->json([...])`.

Пример — быстрый поиск по адресам (`AddressController::search`):

```php
// backend
public function search(Request $request)
{
    $results = Address::search($request->get('q'))->limit(20)->get();
    return response()->json($results);
}
```

```js
// frontend, где-то в Addresses/Index.vue
const { data } = await axios.get(route('addresses.search'), { params: { q: globalSearch.value } })
searchResults.value = data   // обычный ref — Vue сам перерисует список
```

Тут вообще нет Inertia в игре — это просто HTTP GET, который возвращает JSON,
и Vue-компонент сам решает, что с этим JSON сделать (записать в свой `ref`,
Vue отреагирует на изменение и перерисует нужный кусок DOM). Такие эндпоинты
в проекте используются везде, где нужна "живая" реакция без потери состояния
формы/скролла: `addresses.search`, `addresses.hierarchy`,
`calendar.events`, `dashboard.new-since`, `tickets.map-data`,
`brigades.schedule.generate`, все `settings/*/data`, `reports/*` и т.д. —
полный список в [API.md](API.md).

**Отличать от Inertia-навигации просто**: если во Vue-коде используется
`router.get/post/...` (или `<Link>`, или `useForm().post(...)`) — это Inertia
(тип B), меняет текущую "страницу". Если используется `axios.get/post/...`
напрямую — это обычный JSON-запрос (тип C), про Inertia ничего не знает и
текущую страницу не меняет, только то, что явно обработает JS-код.

---

## D. Запрос от мобильного приложения / PWA

Android-приложение и мобильная PWA (`app.vega8.ru`, отдельный Vue-проект в
`mobile/`) не открывают HTML-страницы этого Laravel-приложения вообще — они
работают только с `routes/api.php` как с чистым REST API.

Отличия от пути A/B/C:

1. **URL начинается с `/api/`** — попадает в `routes/api.php`, не в
   `routes/web.php`.
2. **Аутентификация — не сессия/кука, а Bearer-токен** в заголовке
   `Authorization: Bearer <токен>`. Токен получают через `POST
   /api/auth/login` (логин+пароль → токен, см. `Api\AuthController`), дальше
   он передаётся в каждом запросе. За это отвечает Laravel Sanctum
   (`auth:sanctum` middleware вместо `auth`).
3. **`ForceJsonResponse`** middleware (`app/Http/Middleware/ForceJsonResponse.php`,
   регистрируется в `bootstrap/app.php` для всей `api`-группы) гарантирует,
   что ответ всегда JSON, даже если что-то упало с ошибкой 500 — мобильное
   приложение никогда не получит случайную HTML-страницу с ошибкой вместо
   JSON, которую не сможет распарсить.
4. **Контроллеры — отдельные**, в `App\Http\Controllers\Api\*`
   (`Api\TicketController`, не путать с обычным веб-`TicketController`).
   Они возвращают `response()->json([...])` напрямую — про Inertia и Vue-
   компоненты тут речи вообще нет, мобильное приложение само рисует свой UI
   на Kotlin/Compose (Android) или на своём отдельном Vue-проекте (PWA).

Пример: `GET /api/tickets` → `Api\TicketController::index()` → JSON с
четырьмя списками заявок (`overdue`, `today`, `new_today`, `tomorrow`).
Полный справочник всех эндпоинтов мобильного API — в [API.md](API.md)
(раздел "Мобильный API").

---

## Сводная схема

```
Браузер: GET/переход/AJAX                    Мобильное/PWA: /api/*
        │                                              │
        ▼                                              ▼
      nginx ────────────────────────────────────────────
        │
        ▼
   PHP-FPM: public/index.php → bootstrap/app.php
        │
        ▼
   Роутер (routes/web.php ИЛИ routes/api.php по префиксу /api)
        │
        ▼
   Middleware: auth/auth:sanctum → active → (веб: HandleInertiaRequests
                                              API: ForceJsonResponse)
        │
        ▼
   Контроллер (App\Http\Controllers\* или App\Http\Controllers\Api\*)
        │             читает/пишет через Eloquent-модели
        ▼
   ┌─────────────────────┬──────────────────────┬────────────────────┐
   │ Inertia::render()    │ response()->json()   │ response()->json() │
   │ БЕЗ X-Inertia:       │ (обычный AJAX внутри  │ (весь мобильный    │
   │  → Blade HTML        │  Vue-страницы)        │  API)              │
   │ С X-Inertia: true    │                       │                    │
   │  → чистый JSON       │                       │                    │
   └──────────┬────────────┴───────────┬───────────┴──────────┬─────────┘
              ▼                        ▼                      ▼
     Браузер монтирует/       Vue сам обновляет         Android/PWA сами
     обновляет Vue-компонент  свой локальный state       рисуют свой UI
     (Pages/*.vue)
```

---

## Как самому проверить, какой путь идёт

Откройте DevTools → вкладку Network в браузере:
- Обычная перезагрузка страницы → запрос с `Content-Type: text/html`, полный
  HTML в ответе.
- Клик по ссылке в уже открытом SPA → запрос с заголовком запроса
  `X-Inertia: true`, ответ `Content-Type: application/json`, тело — `{component,
  props, url, version}`.
- Поиск/автодополнение/что-то без смены "страницы" → обычный
  `XHR`/`fetch`-запрос без `X-Inertia`, ответ — просто JSON нужной формы
  (без `component`/`props`-обёртки).

Дальше: [HOWTO.md](HOWTO.md) — что конкретно менять в каждом из этих слоёв,
когда нужно внести изменение.
