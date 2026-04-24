<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {

        if (! $request->user() || ! $request->user()->hasRole($role))
        {
            abort(403, 'You\'re not authorised to access this page. Please contact your administrator if you think this is a mistake.');
        }

        return $next($request);
    }
}
