<?php

use App\Http\Middleware\EnsurePlatformAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPermissionsTeam;
use App\Support\CrossTenantAccessGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            SetPermissionsTeam::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            SetPermissionsTeam::class,
        ]);

        $middleware->alias([
            'platform' => EnsurePlatformAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Trail cross-tenant access attempts (QA-0111); still resolves to a 404.
        // The handler has already mapped ModelNotFoundException to a 404, keeping
        // the original as the previous exception.
        $exceptions->render(function (NotFoundHttpException $e, Request $request): void {
            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                CrossTenantAccessGuard::log($previous, $request);
            }
        });
    })->create();
