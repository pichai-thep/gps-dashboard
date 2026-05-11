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
        /*
         * TODO:
         * ภายหลังใช้ auth_user จริง
         */
        $login = 'mcap';

        $items = $this->notificationService
            ->getRecentByLogin($login);

        Log::debug($items);

        return response()->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $login = 'mcap';

        return response()->json([
            'success' => true,
            'count' => $this->notificationService->getUnreadCount($login),
        ]);
    }

    public function markRead(Request $request)
    {
        $login = 'mcap';

        $this->notificationService->markRead($login);

        return response()->json([
            'success' => true,
            'count' => 0,
        ]);
    }
}
