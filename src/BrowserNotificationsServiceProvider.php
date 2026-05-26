<?php

namespace Emuniq\FilamentBrowserNotifications;

use Emuniq\FilamentBrowserNotifications\Commands\InstallCommand;
use Emuniq\FilamentBrowserNotifications\Commands\TestCommand;
use Emuniq\FilamentBrowserNotifications\Listeners\SendWebPushOnDatabaseNotification;
use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BrowserNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/browser-notifications.php', 'browser-notifications');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-browser-notifications');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-browser-notifications');

        if (config('webpush.vapid.public_key')) {
            SendWebPushOnDatabaseNotification::boot();
        }

        $this->registerRoutes();

        Panel::configureUsing(function (Panel $panel) {
            if (! $panel->hasPlugin('browser-notifications')) {
                $panel->plugin(BrowserNotificationsPlugin::make());
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, TestCommand::class]);

            $this->publishes([
                __DIR__ . '/../stubs/sw.js' => public_path('sw.js'),
            ], 'browser-notifications-sw');

            $this->publishes([
                __DIR__ . '/../config/browser-notifications.php' => config_path('browser-notifications.php'),
            ], 'browser-notifications-config');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/filament-browser-notifications'),
            ], 'browser-notifications-lang');
        }
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/webpush/subscribe', function (Request $request) {
                if (! method_exists($request->user(), 'updatePushSubscription')) {
                    return response()->json(['error' => 'HasPushSubscriptions trait not added to User model'], 500);
                }

                $request->user()->updatePushSubscription(
                    endpoint: $request->input('endpoint'),
                    key: $request->input('keys.p256dh'),
                    token: $request->input('keys.auth'),
                    contentEncoding: $request->input('contentEncoding', 'aesgcm'),
                );

                return response()->json(['success' => true]);
            })->name('webpush.subscribe');

            Route::delete('/webpush/unsubscribe', function (Request $request) {
                if (! method_exists($request->user(), 'deletePushSubscription')) {
                    return response()->json(['error' => 'HasPushSubscriptions trait not added to User model'], 500);
                }

                $request->user()->deletePushSubscription(
                    $request->input('endpoint'),
                );

                return response()->json(['success' => true]);
            })->name('webpush.unsubscribe');

            Route::get('/sw.js', function () {
                return response(
                    file_get_contents(__DIR__ . '/../stubs/sw.js'),
                    200,
                    [
                        'Content-Type' => 'application/javascript',
                        'Cache-Control' => 'no-cache',
                        'Service-Worker-Allowed' => '/',
                    ]
                );
            })->withoutMiddleware(['web', 'auth'])->name('webpush.sw');

            Route::get('/manifest.json', function () {
                $name = config('app.name', 'App');
                $manifest = [
                    'name' => $name,
                    'short_name' => $name,
                    'start_url' => '/admin',
                    'scope' => '/',
                    'display' => 'standalone',
                    'background_color' => '#ffffff',
                    'theme_color' => '#d97706',
                    'icons' => [],
                ];

                try {
                    $favicon = filament()->getDefaultPanel()->getFavicon();
                    if ($favicon) {
                        $ext = strtolower(pathinfo(parse_url($favicon, PHP_URL_PATH), PATHINFO_EXTENSION));
                        $mime = match ($ext) {
                            'png' => 'image/png',
                            'jpg', 'jpeg' => 'image/jpeg',
                            'svg' => 'image/svg+xml',
                            'webp' => 'image/webp',
                            'ico' => 'image/x-icon',
                            default => 'image/png',
                        };
                        $manifest['icons'] = [
                            ['src' => $favicon, 'sizes' => '192x192', 'type' => $mime, 'purpose' => 'any'],
                            ['src' => $favicon, 'sizes' => '512x512', 'type' => $mime, 'purpose' => 'any'],
                        ];
                    }
                } catch (\Throwable) {
                    //
                }

                return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json']);
            })->withoutMiddleware(['web', 'auth'])->name('webpush.manifest');
        });
    }
}
