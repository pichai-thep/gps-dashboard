<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class NotificationService
{
    public function getRecentByLogin(
        string $redisConnection,
        string $login,
        int $limit = 50
    ): array {
        $key = $this->userNotifyKey($login);

        try {
            $redis = Redis::connection($redisConnection);

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
            Log::error("Redis error [{$redisConnection}] : " . $e->getMessage());
            return [];
        }
    }

    public function pushToUser(
        string $redisConnection,
        string $login,
        array $data,
        int $keep = 100
    ): void {
        $key = $this->userNotifyKey($login);

        try {
            $redis = Redis::connection($redisConnection);

            $redis->lpush(
                $key,
                json_encode($data, JSON_UNESCAPED_UNICODE)
            );

            $redis->ltrim($key, 0, $keep - 1);
            $redis->expire($key, 86400);

        } catch (\Throwable $e) {
            Log::error("Redis error [{$redisConnection}] : " . $e->getMessage());
        }
    }

    public function getUnreadCount(string $redisConnection, string $login): int
    {
        try {
            $redis = Redis::connection($redisConnection);

            return (int) $redis->get("notify:user:" . strtolower($login) . ":unread");

        } catch (\Throwable $e) {
            Log::error("Redis error [{$redisConnection}] : " . $e->getMessage());
            return 0;
        }
    }

    public function markRead(
        string $redisConnection,
        string $login
    ): void {
        try {
            $redis = Redis::connection($redisConnection);

            $redis->set("notify:user:" . strtolower($login) . ":unread", 0);

        } catch (\Throwable $e) {
            Log::error("Redis error [{$redisConnection}] : " . $e->getMessage());
        }
    }

    private function userNotifyKey(string $login): string
    {
        return "notify:user:" . strtolower($login);
    }
}
