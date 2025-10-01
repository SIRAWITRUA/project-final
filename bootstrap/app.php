<?php

use App\Http\Middleware\EmployeeAuthMiddleware;
use App\Http\Middleware\EmployeeGuestMiddleware;
use App\Http\Middleware\UserGuestMiddleware;
use App\Http\Middleware\UserAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'user.auth' => UserAuthMiddleware::class,
            'user.guest' => UserGuestMiddleware::class,
            'employee.auth' => EmployeeAuthMiddleware::class,
            'employee.guest' => EmployeeGuestMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
