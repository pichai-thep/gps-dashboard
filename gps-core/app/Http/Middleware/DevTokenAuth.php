<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token || !str_starts_with($token, 'dev-token-')) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $userId = (int) str_replace('dev-token-', '', $token);

        $user = DB::connection('auth_db')
            ->table('users')
            ->where('id', $userId)
            ->where('active', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
