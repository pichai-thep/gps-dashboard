<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDeviceController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:191',
            'platform' => 'required|in:android,ios',
            'push_provider' => 'required|in:fcm,apns',
            'push_token' => 'required|string',
            'app_version' => 'nullable|string|max:50',
        ]);

        $user = $request->attributes->get('mobile_user');

        DB::connection('auth_db')
            ->table('mobile_devices')
            ->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'device_id' => $request->device_id,
                    'platform' => $request->platform,
                ],
                [
                    'login' => $user->login,
                    'server_name' => $user->server_name,
                    'push_provider' => $request->push_provider,
                    'push_token' => $request->push_token,
                    'app_version' => $request->app_version,
                    'notification_enabled' => 1,
                    'last_seen_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'Device registered',
        ]);
    }

    public function unregister(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:191',
            'platform' => 'required|in:android,ios',
        ]);

        $user = $request->attributes->get('mobile_user');

        DB::connection('auth_db')
            ->table('mobile_devices')
            ->where('user_id', $user->id)
            ->where('device_id', $request->device_id)
            ->where('platform', $request->platform)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device unregistered',
        ]);
    }
}
