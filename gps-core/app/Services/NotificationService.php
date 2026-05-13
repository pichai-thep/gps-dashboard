<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class NotificationService
{
//    public function getRecentByLogin(
//        string $serverName,
//        string $login,
//        int $limit = 50
//    ): array {
//
//        $key = $this->userNotifyKey($login);
//
//        $items = Redis::connection($serverName)
//            ->lrange($key, 0, $limit - 1);
//
//        if ((empty($items)) || ($items==null)) {
//            return [];
//        }
//
//        return collect($items)
//            ->map(fn ($json) => json_decode($json, true))
//            ->filter()
//            ->values()
//            ->all();
//    }


    public function getRecentByLogin(
        string $serverName,
        string $login,
        int $limit = 50
    ): array {

        $key = $this->userNotifyKey($login);

        try {

            $redis = Redis::connection($serverName);

            // test connection
            $redis->ping();

            $items = $redis->lrange($key, 0, $limit - 1);

            if (empty($items)) {
                return [];
            }

            return collect($items)
                ->map(fn ($json) => json_decode($json, true))
                ->filter()
                ->values()
                ->all();

        } catch (\Throwable $e) {

            Log::error("Redis error [{$serverName}] : " . $e->getMessage());

            return [];
        }
    }

    public function pushToUser(
        string $serverName,
        string $login,
        array $data,
        int $keep = 100
    ): void {

        $key = $this->userNotifyKey($login);

        Redis::connection($serverName)->lpush(
            $key,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        Redis::connection($serverName)->ltrim(
            $key,
            0,
            $keep - 1
        );

        Redis::connection($serverName)->expire(
            $key,
            86400
        );
    }

    private function userNotifyKey(string $login): string
    {
        return "notify:user:$login";
    }

    public function getUnreadCount(
        string $serverName,
        string $login
    ): int {

        try{
            return (int) Redis::connection($serverName)
                ->get("notify:user:$login:unread");
        } catch (\Throwable $e) {
            Log::error("Redis error [{$serverName}] : " . $e->getMessage());
            return 0;
        }
    }

    public function markRead(
        string $serverName,
        string $login
    ): void {

        Redis::connection($serverName)
            ->set("notify:user:$login:unread", 0);
    }
}
