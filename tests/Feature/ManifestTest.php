<?php

use Emuniq\FilamentBrowserNotifications\Support\Manifest;

it('uses the configured start_url and theme_color overrides', function () {
    config()->set('browser-notifications.manifest.start_url', '/backoffice');
    config()->set('browser-notifications.manifest.theme_color', '#123456');

    $manifest = Manifest::build();

    expect($manifest['start_url'])->toBe('/backoffice')
        ->and($manifest['theme_color'])->toBe('#123456');
});

it('falls back to sensible defaults when no panel and no overrides are present', function () {
    config()->set('browser-notifications.manifest.start_url', null);
    config()->set('browser-notifications.manifest.theme_color', null);

    expect(Manifest::startUrl())->toBe('/')
        ->and(Manifest::themeColor())->toBe('#d97706');
});

it('no longer hardcodes /admin as the start url', function () {
    config()->set('browser-notifications.manifest.start_url', null);

    expect(Manifest::startUrl())->not->toBe('/admin');
});
