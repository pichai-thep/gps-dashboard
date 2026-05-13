<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class NotificationService
{
    public function getRecentByLogin(
        string $serverName,
        string $login,
        int $limit = 50
    ): array {

        $key = $this->userNotifyKey($login);

//        dd($serverName);

        $items = Redis::connection($serverName)
            ->lrange($key, 0, $limit - 1);

        return collect($items)
            ->map(fn ($json) => json_decode($json, true))
            ->filter()
            ->values()
            ->all();
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

        return (int) Redis::connection($serverName)
            ->get("notify:user:$login:unread");
    }

    public function markRead(
        string $serverName,
        string $login
    ): void {

        Redis::connection($serverName)
            ->set("notify:user:$login:unread", 0);
    }
}
