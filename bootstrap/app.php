<?php

use App\Http\Middleware\RecruitmentSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('ajax/*') || $request->expectsJson());
        $exceptions->render(function (HttpException $e, Request $request) {
            // Laravel converts token failures into HTTP exceptions before render callbacks.
            if ($e->getPrevious() instanceof TokenMismatchException && $request->is('ajax/*')) {
                $response = fn () => response()->json(['error' => 'Please refresh the page and try again.'], 419);
                // Laravel rejects the token before the recruitment middleware runs.
                // Revalidate the role and record the declined request as in the PHP portal.
                if (config('database.default') === 'mysql' && $request->hasSession()) {
                    try {
                        return app(RecruitmentSession::class)->handle($request, $response);
                    } catch (Throwable $auditError) {
                        report($auditError);
                    }
                }

                return $response();
            }
        });
    })->create();
