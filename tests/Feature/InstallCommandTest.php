<?php

it('registers the install command', function () {
    expect(array_keys(app(Illuminate\Contracts\Console\Kernel::class)->all()))
        ->toContain('browser-notifications:install');
});
