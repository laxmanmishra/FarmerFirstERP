<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error($e->getMessage(), 'validation_error', 422, $e->errors()),
                $e instanceof BusinessRuleException => ApiResponse::error($e->getMessage(), 'business_rule_error', 422, ['rule' => [$e->rule]]),
                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 'unauthenticated', 401),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException,
                $e instanceof HttpExceptionInterface && $e->getStatusCode() === 403 => ApiResponse::error('You do not have permission to perform this action.', 'permission_error', 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('The requested resource was not found.', 'not_found', 404),
                $e instanceof ThrottleRequestsException => ApiResponse::error('Too many requests. Please slow down.', 'rate_limited', 429),
                $e instanceof HttpExceptionInterface => ApiResponse::error($e->getMessage() ?: 'Request failed.', 'http_error', $e->getStatusCode()),
                default => config('app.debug')
                    ? null
                    : ApiResponse::error('An unexpected error occurred. Quote the request id when reporting it.', 'system_error', 500),
            };
        });
    })->create();
