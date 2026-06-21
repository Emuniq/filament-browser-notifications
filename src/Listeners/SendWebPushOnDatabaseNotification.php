<?php

namespace Emuniq\FilamentBrowserNotifications\Listeners;

use Emuniq\FilamentBrowserNotifications\Jobs\SendDatabaseNotificationWebPush;
use Emuniq\FilamentBrowserNotifications\Jobs\SendGroupedWebPush;
use Emuniq\FilamentBrowserNotifications\Support\ActionResolver;
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

        if (ActionResolver::isSilent($data)) {
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
            [$title, $body, $action] = $this->extract($data);

            SendDatabaseNotificationWebPush::dispatch(
                $notifiable,
                $title,
                $body,
                $action['url'] ?? null,
                $action['openInNewTab'] ?? false,
            )->delay(now()->addSeconds($throttle));
        }
    }

    protected function dispatchImmediate(mixed $notifiable, array $data): void
    {
        [$title, $body, $action] = $this->extract($data);

        SendDatabaseNotificationWebPush::dispatch(
            $notifiable,
            $title,
            $body,
            $action['url'] ?? null,
            $action['openInNewTab'] ?? false,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string, 2: array{url: string, openInNewTab: bool}|null}
     */
    protected function extract(array $data): array
    {
        $title = $data['title'] ?? $data['subject'] ?? config('app.name', 'Notification');
        $body = $data['body'] ?? $data['message'] ?? '';

        return [$title, $body, ActionResolver::resolve($data)];
    }
}
