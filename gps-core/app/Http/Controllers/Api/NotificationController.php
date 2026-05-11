<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function recent(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $login = $user->login;

        logger()->info("Api NotificationController login: {$login}");


        $items = $this->notificationService
            ->getRecentByLogin(strtolower($login));

//        Log::debug($items);

        return response()->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $login = $user->login;

        return response()->json([
            'success' => true,
            'count' => $this->notificationService->getUnreadCount(strtolower($login)),
        ]);
    }

    public function markRead(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $login = $user->login;

        $this->notificationService->markRead(strtolower($login));

        return response()->json([
            'success' => true,
            'count' => 0,
        ]);
    }
}
