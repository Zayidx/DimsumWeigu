<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\ApplyUserLocale;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\RegistrationEnabled;
use App\Http\Middleware\RequireActiveShift;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [AuthenticateSession::class, ApplyUserLocale::class, EnforceAbsoluteSessionLifetime::class, AddSecurityHeaders::class]);
        $middleware->api(append: [AddSecurityHeaders::class]);
        $middleware->alias([
            'account.active' => EnsureActiveAccount::class,
            'password.confirm' => RequirePassword::class,
            'registration.enabled' => RegistrationEnabled::class,
            'shift.active' => RequireActiveShift::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 403 || $request->is('api/*') || ! $request->hasSession()) {
                return $response;
            }

            if ($request->expectsJson() || $request->hasHeader('X-Livewire') || (app()->runningUnitTests() && $request->is('livewire-unit-test-endpoint/*'))) {
                return $response;
            }

            // Do not send a successful login back to the forbidden page.
            $request->session()->forget('url.intended');

            return redirect()->route('login');
        });
    })->create();
