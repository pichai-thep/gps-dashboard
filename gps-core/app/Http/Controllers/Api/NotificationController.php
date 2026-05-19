<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function recent(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        $login = $user->login;
        $serverName = strtolower($user->server_name);

        logger()->info("Api NotificationController recent", [
            'login' => $login,
            'server_name' => $serverName,
        ]);

        $items = $this->notificationService
            ->getRecentByLogin($connection, $login);

        return response()->json([
            'success' => true,
            'server_name' => $serverName,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        $login = $user->login;
        $serverName = strtolower($user->server_name);

        return response()->json([
            'success' => true,
            'server_name' => $serverName,
            'count' => $this->notificationService
                ->getUnreadCount($connection, $login),
        ]);
    }

    public function markRead(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $connection = $request->attributes->get('gps_connection');

        $login = $user->login;
        $serverName = strtolower($user->server_name);

        $this->notificationService
            ->markRead($connection, $login);

        return response()->json([
            'success' => true,
            'server_name' => $serverName,
            'count' => 0,
        ]);
    }
}
