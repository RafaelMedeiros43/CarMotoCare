<?php

use App\Exceptions\ProblemRenderer;
use App\Http\Middleware\AuthenticateJwt;
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
        $middleware->alias(['auth.jwt' => AuthenticateJwt::class]);

        // Do not trim passwords (the camelCase names of the contract are not in Laravel's default list).
        $middleware->trimStrings(except: ['password', 'currentPassword', 'newPassword']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This service only serves JSON, so errors are always rendered as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Every error is returned as RFC 7807 Problem Details (application/problem+json).
        $exceptions->render(new ProblemRenderer);
    })->create();
