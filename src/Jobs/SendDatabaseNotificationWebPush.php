<?php

namespace Emuniq\FilamentBrowserNotifications\Jobs;

use Emuniq\FilamentBrowserNotifications\Notifications\GenericWebPushNotification;
use Emuniq\FilamentBrowserNotifications\Support\Panels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendDatabaseNotificationWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public Model $notifiable,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public bool $openInNewTab = false,
    ) {
        $this->onConnection(config('browser-notifications.queue_connection'));
        $this->onQueue(config('browser-notifications.queue_name'));
    }

    public function handle(): void
    {
        $throttle = (int) config('browser-notifications.throttle_seconds', 5);

        if ($throttle > 0) {
            $this->sendWithGrouping($throttle);
        } else {
            $this->sendSingle($this->title, $this->body, $this->actionUrl, $this->openInNewTab);
        }
    }

    protected function sendWithGrouping(int $throttle): void
    {
        $recentCount = DatabaseNotification::query()
            ->where('notifiable_type', get_class($this->notifiable))
            ->where('notifiable_id', $this->notifiable->getKey())
            ->where('read_at', null)
            ->where('created_at', '>=', now()->subSeconds($throttle + 1))
            ->count();

        if ($recentCount <= 1) {
            $this->sendSingle($this->title, $this->body, $this->actionUrl, $this->openInNewTab);
            return;
        }

        $title = trans_choice('filament-browser-notifications::prompt.grouped_title', $recentCount, ['count' => $recentCount]);
        $body = trans_choice('filament-browser-notifications::prompt.grouped_body', $recentCount, ['count' => $recentCount]);

        // Grouped pushes have no single action; open the panel where the
        // notifications live rather than a hardcoded "/admin".
        $this->sendSingle($title, $body, Panels::defaultPath(), false);
    }

    protected function sendSingle(string $title, string $body, ?string $actionUrl, bool $openInNewTab = false): void
    {
        try {
            Notification::send(
                $this->notifiable,
                new GenericWebPushNotification($title, $body, $actionUrl, $openInNewTab),
            );
        } catch (\Throwable $e) {
            Log::warning('WebPush delivery failed', [
                'notifiable' => $this->notifiable->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
