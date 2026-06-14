<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDeviceController extends Controller
{
    public function register(Request $request)
    {
        logger()->info('Registering device');

        $user = $request->attributes->get('mobile_user');
        $gpsConnection = $user->server_name;
        $serverIp = config("database.connections.{$gpsConnection}.host");

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validate([
            'device_id' => 'required|string|max:191',
            'platform' => 'required|in:android,ios',
            'push_provider' => 'required|in:fcm,apns',
            'push_token' => 'required|string',
            'app_version' => 'nullable|string|max:50',
            'device_brand' => 'nullable|string|max:100',
            'device_model' => 'nullable|string|max:150',
            'os_version' => 'nullable|string|max:50',
        ]);

        $now = now();

        DB::connection('auth_db')
            ->table('mobile_devices')
            ->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'device_id' => $validated['device_id'],
                    'push_provider' => $validated['push_provider'],
                ],
                [
                    'login' => $user->login,
                    'server_name' => $serverIp ?? null,
                    'platform' => $validated['platform'],
                    'push_token' => $validated['push_token'],
                    'app_version' => $validated['app_version'] ?? null,
                    'device_brand' => $validated['device_brand'] ?? null,
                    'device_model' => $validated['device_model'] ?? null,
                    'os_version' => $validated['os_version'] ?? null,
                    'notification_enabled' => 1,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

        logger()->info('Device registered');
        return response()->json([
            'success' => true,
            'message' => 'Device registered',
        ]);
    }

    public function unregister(Request $request)
    {
        logger()->info('Unregistering device');
        $user = $request->attributes->get('mobile_user');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validate([
            'device_id' => 'required|string|max:191',
            'push_provider' => 'required|in:fcm,apns',
        ]);

        DB::connection('auth_db')
            ->table('mobile_devices')
            ->where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->where('push_provider', $validated['push_provider'])
            ->delete();

        logger()->info('Device unregistered');
        return response()->json([
            'success' => true,
            'message' => 'Device unregistered',
        ]);
    }
}
