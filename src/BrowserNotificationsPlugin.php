<?php

namespace Emuniq\FilamentBrowserNotifications;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class BrowserNotificationsPlugin implements Plugin
{
    protected int $promptDelay = 2;

    protected int $dismissCooldownDays = 7;

    protected bool $showProfileSection = true;

    public function getId(): string
    {
        return 'browser-notifications';
    }

    public function promptDelay(int $seconds): static
    {
        $this->promptDelay = $seconds;

        return $this;
    }

    public function getPromptDelay(): int
    {
        return $this->promptDelay;
    }

    public function dismissCooldownDays(int $days): static
    {
        $this->dismissCooldownDays = $days;

        return $this;
    }

    public function getDismissCooldownDays(): int
    {
        return $this->dismissCooldownDays;
    }

    public function profileSection(bool $show = true): static
    {
        $this->showProfileSection = $show;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn () => $this->renderVapidMeta(),
        );

        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament-browser-notifications::webpush-prompt', [
                'plugin' => $this,
            ]),
        );

        if ($this->showProfileSection) {
            $panel->renderHook(
                PanelsRenderHook::PAGE_FOOTER_WIDGETS_BEFORE,
                function () {
                    if (! auth()->check()) {
                        return '';
                    }

                    $uri = request()->path();
                    if (! str_ends_with($uri, '/profile')) {
                        return '';
                    }

                    return view('filament-browser-notifications::profile-section')->render();
                },
            );
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }

    protected function renderVapidMeta(): string
    {
        $key = config('webpush.vapid.public_key');

        if (! $key) {
            return '';
        }

        return '<meta name="vapid-public-key" content="' . e($key) . '">';
    }
}
