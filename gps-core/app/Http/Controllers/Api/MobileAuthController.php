<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:191',
            'platform' => 'nullable|in:android,ios',
            'app_version' => 'nullable|string|max:50',
        ]);

        $user = DB::connection('auth_db')
            ->table('users')
            ->where('login', $request->username)
            ->where('active', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        if (!$this->checkPassword($request->password, $user->pwd)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        $plainToken = Str::random(80);

        DB::connection('auth_db')
            ->table('mobile_access_tokens')
            ->insert([
                'user_id' => $user->id,
                'login' => $user->login,
                'server_name' => $user->server_name,
                'token_hash' => hash('sha256', $plainToken),
                'device_id' => $request->device_id,
                'platform' => $request->platform,
                'app_version' => $request->app_version,
                'expires_at' => now()->addDays(90),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'token_type' => 'Bearer',
            'token' => $plainToken,
            'expires_at' => now()->addDays(90)->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'username' => $user->login,
                'server_name' => $user->server_name,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->attributes->get('mobile_user');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->login,
                'server_name' => $user->server_name,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $header = $request->header('Authorization');

        if (!$header || !preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $tokenHash = hash('sha256', trim($matches[1]));

        DB::connection('auth_db')
            ->table('mobile_access_tokens')
            ->where('token_hash', $tokenHash)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out',
        ]);
    }

//    private function checkPassword(string $plainPassword, string $storedPassword): bool
//    {
//        if (password_verify($plainPassword, $storedPassword)) {
//            return true;
//        }
//
//        return md5($plainPassword) === $storedPassword;
//    }

    private function checkPassword($input, $stored)
    {
        // 1. plain text
        if ($input === $stored) {
            return true;
        }

        // 2. md5
        if (md5($input) === $stored) {
            return true;
        }

        return false;
    }
}
