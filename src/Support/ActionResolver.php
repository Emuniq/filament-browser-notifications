<?php

namespace Emuniq\FilamentBrowserNotifications\Support;

class ActionResolver
{
    /**
     * Find the first action URL in a Filament database-notification payload,
     * descending into ActionGroups (whose nested actions live under an
     * "actions" key rather than carrying a "url" themselves).
     *
     * @param  array<string, mixed>  $data
     * @return array{url: string, openInNewTab: bool}|null
     */
    public static function resolve(array $data): ?array
    {
        $found = static::search($data['actions'] ?? []);

        if ($found !== null) {
            return $found;
        }

        // Fallbacks for hand-rolled notification payloads.
        $url = $data['action_url'] ?? $data['url'] ?? null;

        return is_string($url) && $url !== ''
            ? ['url' => $url, 'openInNewTab' => false]
            : null;
    }

    /**
     * Whether the notification opted out of a browser push. Supports both a
     * top-level "silent" key and Filament's `->viewData(['silent' => true])`,
     * since the Notification class has no `->data()` method.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isSilent(array $data): bool
    {
        return (bool) ($data['silent'] ?? ($data['viewData']['silent'] ?? false));
    }

    /**
     * @param  array<int, mixed>  $actions
     * @return array{url: string, openInNewTab: bool}|null
     */
    protected static function search(array $actions): ?array
    {
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            // ActionGroup: its actions are nested, recurse before checking url.
            if (! empty($action['actions']) && is_array($action['actions'])) {
                $nested = static::search($action['actions']);

                if ($nested !== null) {
                    return $nested;
                }
            }

            $url = $action['url'] ?? null;

            if (is_string($url) && $url !== '') {
                return [
                    'url' => $url,
                    'openInNewTab' => (bool) ($action['shouldOpenUrlInNewTab'] ?? false),
                ];
            }
        }

        return null;
    }
}
