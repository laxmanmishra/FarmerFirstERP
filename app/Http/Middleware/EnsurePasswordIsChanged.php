<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users created or reset by an administrator must set their own password first.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.change', 'logout', 'livewire.*')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
