<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: ['telegram/webhook', 'sync/ticket', 'sync/legacy-ticket', 'pbx/queue-status', 'pbx/ivr-log', 'pbx/dnd-log', 'pbx/alert']);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        // Единая понятная страница ошибок (403/404/419/429/500/503) на русском
        // вместо стандартной англоязычной страницы Laravel — см. память
        // project-acts-feature (найдено по жалобе пользователя на частые "403
        // This action is unauthorized" при клике на недоступное действие).
        // API (/api/*) не трогаем — ForceJsonResponse уже форсирует Accept:
        // application/json для этих маршрутов, expectsJson() отсекает их сама.
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (!$request->expectsJson() && in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return Inertia::render('Errors/Show', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();