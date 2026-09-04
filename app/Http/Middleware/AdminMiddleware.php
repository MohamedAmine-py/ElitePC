<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/*
 * AdminMiddleware — blocks non-admin users from accessing admin routes.
 *
 * HOW MIDDLEWARE WORKS:
 * Every request passes through middleware before reaching the controller.
 * If the user is not admin, we stop the request here and return 403.
 * If they are admin, we let the request continue ($next($request)).
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Access denied — administrators only'], 403);
        }

        return $next($request);
    }
}
