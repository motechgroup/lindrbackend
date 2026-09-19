<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === UserStatus::Suspended) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
                'errors' => ['account' => ['Account is suspended']],
            ], 403);
        }

        return $next($request);
    }
}
