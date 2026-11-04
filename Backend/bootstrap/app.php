<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(App\Http\Middleware\ResponseHeaders::class);
        $middleware->redirectGuestsTo('/admin/connexion');
        $middleware->alias(['staff' => App\Http\Middleware\StaffAccess::class, 'admin' => App\Http\Middleware\AdminAccess::class, 'owner' => App\Http\Middleware\OwnerAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
