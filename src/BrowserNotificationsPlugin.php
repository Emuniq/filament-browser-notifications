<?php

namespace Emuniq\FilamentBrowserNotifications;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
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
    }

    public function boot(Panel $panel): void
    {
        if (! $this->showProfileSection) {
            return;
        }

        // Resolved in boot() so the panel is fully configured regardless of the
        // order plugins() and profile() are chained. Null means no profile page
        // is enabled, so there is nothing to attach the section to.
        $profilePage = $panel->getProfilePage();

        if (! $profilePage) {
            return;
        }

        // Registered via FilamentView (not $panel->renderHook()) because the panel
        // flushes its own render hooks before plugins boot, so a $panel->renderHook()
        // call here would be silently dropped.
        //
        // Standard profile pages render PAGE_END; simple ones (profile(isSimple: true))
        // render SIMPLE_PAGE_END instead. A page is only ever one of the two, so the
        // section is rendered exactly once. Scoping keeps it on the profile page only.
        foreach ([PanelsRenderHook::PAGE_END, PanelsRenderHook::SIMPLE_PAGE_END] as $hook) {
            FilamentView::registerRenderHook(
                $hook,
                fn () => auth()->check()
                    ? view('filament-browser-notifications::profile-section')->render()
                    : '',
                scopes: $profilePage,
            );
        }
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

        return '<meta name="vapid-public-key" content="' . e($key) . '">'
            . "\n" . '<link rel="manifest" href="/manifest.json">'
            . "\n" . '<meta name="apple-mobile-web-app-capable" content="yes">'
            . "\n" . '<meta name="apple-mobile-web-app-status-bar-style" content="default">'
            . "\n" . '<meta name="apple-mobile-web-app-title" content="' . e(config('app.name', 'App')) . '">';
    }
}
