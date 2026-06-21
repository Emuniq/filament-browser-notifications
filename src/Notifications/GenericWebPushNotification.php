<?php

namespace Emuniq\FilamentBrowserNotifications\Notifications;

use Emuniq\FilamentBrowserNotifications\Support\Panels;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class GenericWebPushNotification extends Notification
{
    public function __construct(
        protected string $title,
        protected string $body,
        protected ?string $actionUrl = null,
        protected bool $openInNewTab = false,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $icon = $this->resolveIcon();

        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($icon)
            ->badge($icon);

        $message->data([
            'action_url' => $this->actionUrl ?? Panels::defaultPath(),
            'open_in_new_tab' => $this->openInNewTab,
        ]);

        return $message;
    }

    protected function resolveIcon(): string
    {
        try {
            $favicon = filament()->getDefaultPanel()->getFavicon();

            if ($favicon) {
                return $favicon;
            }
        } catch (\Throwable) {
            //
        }

        return '/favicon.ico';
    }
}
