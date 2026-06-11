<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileAuth
{
    public function handle(Request $request, Closure $next)
    {
//        logger()->info('MobileAuth middleware called');

        $header = $request->header('Authorization');

        if (!$header || !preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $msg = 'Unauthorized';
//            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        $plainToken = trim($matches[1]);
        $tokenHash = hash('sha256', $plainToken);

        $token = DB::connection('auth_db')
            ->table('mobile_access_tokens')
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$token) {
            $msg = 'Unauthorized, invalid token';
//            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        if ($token->expires_at && strtotime($token->expires_at) < time()) {
            $msg = 'Unauthorized, expired token';
//            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        $user = DB::connection('auth_db')
            ->table('users')
            ->where('id', $token->user_id)
            ->where('active', 1)
            ->first();

        if (!$user) {
            $msg = 'Unauthorized, user not found';
//            logger()->info($msg);
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 401);
        }

        DB::connection('auth_db')
            ->table('mobile_access_tokens')
            ->where('id', $token->id)
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('mobile_user', $user);
        $request->attributes->set('mobile_token', $token);

        return $next($request);
    }
}
