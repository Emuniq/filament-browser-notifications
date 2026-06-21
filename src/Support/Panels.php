<?php

namespace Emuniq\FilamentBrowserNotifications\Support;

use Filament\Facades\Filament;

class Panels
{
    /**
     * The default panel's path with a leading slash (e.g. "/admin",
     * "/backoffice"), or "/" for a root panel or when no panel is resolvable.
     * Used as the click target when a notification has no action URL.
     */
    public static function defaultPath(): string
    {
        try {
            $path = trim(Filament::getDefaultPanel()->getPath(), '/');

            return $path === '' ? '/' : '/' . $path;
        } catch (\Throwable) {
            return '/';
        }
    }
}
