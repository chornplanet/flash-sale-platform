<?php

use App\Http\Middleware\LogDatabaseQueries;
use App\Support\WindowsFilesystem;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$basePath = dirname(__DIR__);
$application = Application::configure(basePath: $basePath);
$dockerEnvironmentPath = $basePath.'/docker/environment/app.env';

if (! is_file($basePath.'/.env') && is_file($dockerEnvironmentPath)) {
    $application->create()
        ->useEnvironmentPath(dirname($dockerEnvironmentPath))
        ->loadEnvironmentFrom(basename($dockerEnvironmentPath));
}

return $application
    ->withSingletons(PHP_OS_FAMILY === 'Windows' ? [
        'files' => fn () => new WindowsFilesystem,
    ] : [])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : null);
        $middleware->api(append: [LogDatabaseQueries::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (AuthenticationException $exception, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();
