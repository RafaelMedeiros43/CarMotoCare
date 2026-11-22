<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Contract paths have no /api prefix (e.g. /health, /users).
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This service only serves JSON, so errors are always rendered as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
