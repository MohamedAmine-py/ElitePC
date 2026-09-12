<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class OptionalChatAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = null;
        if ($request->headers->has('Authorization')) {
            if (! preg_match('/^Bearer\s+\S+$/i', $request->header('Authorization', ''))) {
                throw new AuthenticationException;
            }
            $user = Auth::guard('sanctum')->user();
            // Chat accepts bearer authentication, never a session fallback for an invalid token.
            if (! $user || ! $user->currentAccessToken() instanceof PersonalAccessToken) {
                throw new AuthenticationException;
            }
        }
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
