<?php

namespace Emuniq\FilamentBrowserNotifications\Support;

use Filament\Facades\Filament;
use Filament\Panel;

class Manifest
{
    /**
     * Build the PWA manifest, deriving the start URL and theme color from the
     * active Filament panel (with optional config overrides).
     *
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $name = config('app.name', 'App');

        return [
            'name' => $name,
            'short_name' => $name,
            'start_url' => static::startUrl(),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => static::themeColor(),
            'icons' => static::icons(),
        ];
    }

    /**
     * The PWA launch URL. Defaults to the default panel's path so installed
     * apps open the panel rather than a hardcoded "/admin".
     */
    public static function startUrl(): string
    {
        $configured = config('browser-notifications.manifest.start_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $panel = static::defaultPanel();

        if ($panel) {
            $path = trim($panel->getPath(), '/');

            return $path === '' ? '/' : '/' . $path;
        }

        return '/';
    }

    /**
     * The browser UI color for the installed PWA, derived from the panel's
     * primary color so it matches the app's Filament theme.
     */
    public static function themeColor(): string
    {
        $configured = config('browser-notifications.manifest.theme_color');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $panel = static::defaultPanel();

        if ($panel) {
            try {
                $primary = $panel->getColors()['primary'] ?? null;

                // A palette is an array keyed by shade (50–950); shade 500 is
                // the representative mid tone. A plain string is used as-is.
                $value = is_array($primary)
                    ? ($primary[500] ?? reset($primary))
                    : $primary;

                if (is_string($value) && ($hex = static::colorToHex($value))) {
                    return $hex;
                }
            } catch (\Throwable) {
                // Color resolution differs across Filament majors; fall back.
            }
        }

        return '#d97706';
    }

    /**
     * Normalise a Filament color value to a #rrggbb hex string. Handles the
     * formats used across Filament majors: an "r, g, b" triplet (v3), an
     * oklch() string (v4/v5), and a plain hex value. Returns null when the
     * value cannot be parsed.
     */
    public static function colorToHex(string $color): ?string
    {
        $color = trim($color);

        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $color, $m)) {
            return '#' . strtolower($m[1]);
        }

        if (preg_match('/^(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})$/', $color, $m)) {
            return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
        }

        if (preg_match('/oklch\(\s*([0-9.]+%?)\s+([0-9.]+)\s+([0-9.]+)/i', $color, $m)) {
            return static::oklchToHex(
                str_contains($m[1], '%') ? ((float) $m[1]) / 100 : (float) $m[1],
                (float) $m[2],
                (float) $m[3],
            );
        }

        return null;
    }

    /**
     * Convert an OKLCH color to a #rrggbb sRGB hex string.
     */
    protected static function oklchToHex(float $L, float $C, float $H): string
    {
        $h = deg2rad($H);
        $a = $C * cos($h);
        $b = $C * sin($h);

        // OKLab -> LMS (cubed) -> linear sRGB.
        $l_ = ($L + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m_ = ($L - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s_ = ($L - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $r = 4.0767416621 * $l_ - 3.3077115913 * $m_ + 0.2309699292 * $s_;
        $g = -1.2684380046 * $l_ + 2.6097574011 * $m_ - 0.3413193965 * $s_;
        $bl = -0.0041960863 * $l_ - 0.7034186147 * $m_ + 1.7076147010 * $s_;

        $toByte = function (float $c): int {
            // Linear sRGB -> gamma-encoded sRGB.
            $c = $c <= 0.0031308 ? 12.92 * $c : 1.055 * ($c ** (1 / 2.4)) - 0.055;

            return max(0, min(255, (int) round($c * 255)));
        };

        return sprintf('#%02x%02x%02x', $toByte($r), $toByte($g), $toByte($bl));
    }

    /**
     * Build the manifest icon set from the default panel's favicon.
     *
     * @return array<int, array<string, string>>
     */
    protected static function icons(): array
    {
        try {
            $favicon = static::defaultPanel()?->getFavicon();

            if (! $favicon) {
                return [];
            }

            $ext = strtolower(pathinfo(parse_url($favicon, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                'ico' => 'image/x-icon',
                default => 'image/png',
            };

            return [
                ['src' => $favicon, 'sizes' => '192x192', 'type' => $mime, 'purpose' => 'any'],
                ['src' => $favicon, 'sizes' => '512x512', 'type' => $mime, 'purpose' => 'any'],
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function defaultPanel(): ?Panel
    {
        try {
            return Filament::getDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }
}
