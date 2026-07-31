<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleLocale;
use App\Services\Reception\ReceptionSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('HELMDESK_TRUSTED_PROXIES');
        if ($trustedProxies !== null) {
            $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : explode(',', $trustedProxies));
        }

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale', 'mercureAuthorization', ReceptionSession::COOKIE_PREFIX.'*']);
        $middleware->validateCsrfTokens(except: ['api/visitor/attachments/*', 'webhook/telegram/*', 'webhook/wechat/*', 'mock-business-system/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            HandleLocale::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($exception instanceof BusinessException) {
                if ($request->header('X-Inertia')) {
                    return back()->withErrors(['toast' => $exception->getMessage()]);
                }

                return response()->json(['message' => $exception->getMessage()], 422);
            }

            if ($exception instanceof ValidationException) {
                if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                    $errors = $exception->errors();
                    $firstField = array_key_first($errors);
                    $firstError = $errors[$firstField][0];

                    return response()->json([
                        'message' => $firstField.' '.$firstError,
                        'errors' => $errors,
                    ], 422)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
                }
            }

            return $response;
        });
    })->create();

$app->useStoragePath(env('LARAVEL_STORAGE_PATH', dirname(__DIR__).'/storage'));

return $app;
