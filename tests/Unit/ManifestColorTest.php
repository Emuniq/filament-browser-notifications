<?php

use Emuniq\FilamentBrowserNotifications\Support\Manifest;

it('converts an rgb triplet (Filament v3) to hex', function () {
    expect(Manifest::colorToHex('16, 185, 129'))->toBe('#10b981');
});

it('passes a hex value through, normalised', function () {
    expect(Manifest::colorToHex('#10B981'))->toBe('#10b981')
        ->and(Manifest::colorToHex('10B981'))->toBe('#10b981');
});

it('converts an oklch color (Filament v4/v5) to sRGB hex', function () {
    // Tailwind v4 / Filament v5 emerald-500.
    expect(Manifest::colorToHex('oklch(0.696 0.17 162.48)'))->toBe('#00bc7d');
});

it('handles oklch with a percentage lightness', function () {
    expect(Manifest::colorToHex('oklch(69.6% 0.17 162.48)'))->toBe('#00bc7d');
});

it('returns null for an unparseable color', function () {
    expect(Manifest::colorToHex('not-a-color'))->toBeNull();
});
