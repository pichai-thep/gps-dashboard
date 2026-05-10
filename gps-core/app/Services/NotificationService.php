<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class NotificationService
{
    public function getRecentByLogin(string $login, int $limit = 50): array
    {
        $key = $this->userNotifyKey($login);

        $items = Redis::connection('default')
            ->lrange($key, 0, $limit - 1);

        return collect($items)
            ->map(fn ($json) => json_decode($json, true))
            ->filter()
            ->values()
            ->all();
    }

    public function pushToUser(string $login, array $data, int $keep = 100): void
    {
        $key = $this->userNotifyKey($login);

        Redis::connection('default')->lpush(
            $key,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        Redis::connection('default')->ltrim($key, 0, $keep - 1);
        Redis::connection('default')->expire($key, 86400);
    }

    private function userNotifyKey(string $login): string
    {
        return "notify:user:$login";
    }

    public function getUnreadCount(string $login): int
    {
        return (int) Redis::connection('default')
            ->get("notify:user:$login:unread");
    }

    public function markRead(string $login): void
    {
        Redis::connection('default')
            ->set("notify:user:$login:unread", 0);
    }
}
