<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureGateStation;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\UseCloudflareClientIp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare Tunnel (cloudflared) berjalan di server yang sama: hanya proxy lokal yang dipercaya
        // untuk X-Forwarded-*, supaya URL dibuat dengan https tanpa membiarkan pengunjung memalsukan IP.
        $middleware->trustProxies(at: UseCloudflareClientIp::TRUSTED_PROXIES);
        $middleware->prepend(UseCloudflareClientIp::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'gate.station' => EnsureGateStation::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A form left open past the session lifetime: go back with a message
        // instead of showing "419 Page Expired". The scan queue handles its own 419.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back()->with('error', 'Halaman sudah terlalu lama dibuka, jadi tadi belum tersimpan. Silakan coba lagi.');
            }

            return $response;
        });
    })->create();
