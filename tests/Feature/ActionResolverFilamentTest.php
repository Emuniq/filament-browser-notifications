<?php

use Emuniq\FilamentBrowserNotifications\Support\ActionResolver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;

it('resolves the url from a real Filament notification with a flat action', function () {
    $data = Notification::make()
        ->title('New notification')
        ->actions([
            Action::make('view')->label('View')->url('https://app.test/demo'),
        ])
        ->getDatabaseMessage();

    expect(ActionResolver::resolve($data)['url'])->toBe('https://app.test/demo');
});

it('resolves the url from a real Filament notification using an ActionGroup', function () {
    $data = Notification::make()
        ->title('New notification')
        ->actions([
            ActionGroup::make([
                Action::make('view')->label('View')->url('https://app.test/demo')->openUrlInNewTab(),
            ]),
        ])
        ->getDatabaseMessage();

    expect(ActionResolver::resolve($data))
        ->toBe(['url' => 'https://app.test/demo', 'openInNewTab' => true]);
});

it('treats viewData silent on a real notification as silent', function () {
    $data = Notification::make()
        ->title('Low priority')
        ->viewData(['silent' => true])
        ->getDatabaseMessage();

    expect(ActionResolver::isSilent($data))->toBeTrue();
});
