<?php

use App\Http\Middleware\AddRobotsTag;
use App\Http\Middleware\EndSuspendedSessions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AddRobotsTag::class);
        $middleware->redirectUsersTo(fn (Request $request): string => route($request->user()->landingRoute()));
        $middleware->web(append: EndSuspendedSessions::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EndSuspendedSessions::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['old_password', 'delete_password', 'token']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return redirect()->route('home')->with('error', __('app.errors.session_expired'));
            }

            return $response;
        });
    })->create();
