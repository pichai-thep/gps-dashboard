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
        /*
         * TODO:
         * ภายหลังใช้ auth_user จริง
         */
        $login = 'mcap';

        $items = $this->notificationService
            ->getRecentByLogin($login);

        return response()->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }
}
