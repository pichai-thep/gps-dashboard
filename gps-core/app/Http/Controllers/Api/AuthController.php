<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = DB::connection('auth_db')
            ->table('users')
            ->where('login', $request->username)
            ->where('active', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        // 🔥 password check (รองรับหลายแบบ)
        if (!$this->checkPassword($request->password, $user->pwd)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 401);
        }

        // ⚡ ดึง role
        $roles = DB::connection('auth_db')
            ->table('group_role_user as gru')
            ->join('group_role as gr', 'gr.id', '=', 'gru.group_role_id')
            ->where('gru.users_id', $user->id)
            ->pluck('gr.group_role_name');

        return response()->json([
            'token' => 'dev-token-' . $user->id,
            'user' => [
                'id' => $user->id,
                'username' => $user->login,
                'server_name' => $user->server_name,
                'roles' => $roles,
            ],
        ]);
    }

    public function me(Request $request)
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

        $roles = DB::connection('auth_db')
            ->table('group_role_user as gru')
            ->join('group_role as gr', 'gr.id', '=', 'gru.group_role_id')
            ->where('gru.users_id', $user->id)
            ->pluck('gr.group_role_name')
            ->values();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->login,
                'server_name' => $user->server_name,
                'roles' => $roles,
            ],
        ]);
    }

    public function logout()
    {
        return response()->json([
            'message' => 'Logged out',
        ]);
    }

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
