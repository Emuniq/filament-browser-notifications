<?php

namespace Emuniq\FilamentBrowserNotifications\Listeners;

use Emuniq\FilamentBrowserNotifications\Jobs\SendDatabaseNotificationWebPush;
use Emuniq\FilamentBrowserNotifications\Jobs\SendGroupedWebPush;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class SendWebPushOnDatabaseNotification
{
    public static function boot(): void
    {
        Event::listen('eloquent.created: ' . DatabaseNotification::class, static::class);
    }

    public function handle(DatabaseNotification $notification): void
    {
        $notifiable = $notification->notifiable;

        if (! $notifiable || ! method_exists($notifiable, 'pushSubscriptions')) {
            return;
        }

        if ($notifiable->pushSubscriptions()->count() === 0) {
            return;
        }

        $data = $notification->data ?? [];

        if (! empty($data['silent'])) {
            return;
        }

        $throttle = (int) config('browser-notifications.throttle_seconds', 5);
        $userId = $notifiable->getKey();

        if ($throttle > 0) {
            $this->handleThrottled($notifiable, $data, $throttle, $userId);
        } else {
            $this->dispatchImmediate($notifiable, $data);
        }
    }

    protected function handleThrottled(mixed $notifiable, array $data, int $throttle, mixed $userId): void
    {
        $cacheKey = "bn_throttle:{$userId}";

        if (Cache::add($cacheKey, 1, $throttle)) {
            $title = $data['title'] ?? $data['subject'] ?? config('app.name', 'Notification');
            $body = $data['body'] ?? $data['message'] ?? '';
            $actionUrl = $this->extractActionUrl($data);

            SendDatabaseNotificationWebPush::dispatch(
                $notifiable,
                $title,
                $body,
                $actionUrl,
            )->delay(now()->addSeconds($throttle));
        }
    }

    protected function dispatchImmediate(mixed $notifiable, array $data): void
    {
        $title = $data['title'] ?? $data['subject'] ?? config('app.name', 'Notification');
        $body = $data['body'] ?? $data['message'] ?? '';
        $actionUrl = $this->extractActionUrl($data);

        SendDatabaseNotificationWebPush::dispatch(
            $notifiable,
            $title,
            $body,
            $actionUrl,
        );
    }

    protected function extractActionUrl(array $data): ?string
    {
        $actions = $data['actions'] ?? [];

        foreach ($actions as $action) {
            $url = $action['url'] ?? null;
            if ($url) {
                return parse_url($url, PHP_URL_PATH) ?: $url;
            }
        }

        return $data['action_url'] ?? $data['url'] ?? null;
    }
}
