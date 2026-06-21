<?php

use Emuniq\FilamentBrowserNotifications\Support\ActionResolver;

it('resolves the url of a flat action', function () {
    $data = ['actions' => [
        ['url' => 'https://app.test/demo', 'shouldOpenUrlInNewTab' => false],
    ]];

    expect(ActionResolver::resolve($data))
        ->toBe(['url' => 'https://app.test/demo', 'openInNewTab' => false]);
});

it('resolves the url nested inside an ActionGroup', function () {
    // ActionGroup serialises with no top-level url; nested actions live under "actions".
    $data = ['actions' => [
        ['actions' => [
            ['url' => 'https://app.test/demo', 'shouldOpenUrlInNewTab' => true],
        ]],
    ]];

    expect(ActionResolver::resolve($data))
        ->toBe(['url' => 'https://app.test/demo', 'openInNewTab' => true]);
});

it('carries the open-in-new-tab flag from a flat action', function () {
    $data = ['actions' => [
        ['url' => 'https://app.test/demo', 'shouldOpenUrlInNewTab' => true],
    ]];

    expect(ActionResolver::resolve($data)['openInNewTab'])->toBeTrue();
});

it('falls back to action_url / url keys for hand-rolled payloads', function () {
    expect(ActionResolver::resolve(['action_url' => '/reports'])['url'])->toBe('/reports')
        ->and(ActionResolver::resolve(['url' => '/reports'])['url'])->toBe('/reports');
});

it('returns null when there is no action url', function () {
    expect(ActionResolver::resolve(['actions' => [['url' => null]]]))->toBeNull()
        ->and(ActionResolver::resolve([]))->toBeNull();
});

it('detects silent via a top-level key or viewData', function () {
    expect(ActionResolver::isSilent(['silent' => true]))->toBeTrue()
        ->and(ActionResolver::isSilent(['viewData' => ['silent' => true]]))->toBeTrue()
        ->and(ActionResolver::isSilent([]))->toBeFalse();
});
