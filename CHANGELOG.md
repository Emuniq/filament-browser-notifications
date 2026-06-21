# Changelog

All notable changes to `filament-browser-notifications` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Fixed

- Clicking a browser notification now opens the notification's action URL instead of always landing on the dashboard. Action URLs nested inside an `ActionGroup` are now resolved (previously only top-level actions were read, so grouped actions fell back to `/admin`).
- The grouped/throttled push and the no-action fallback no longer hardcode `/admin`; they open the default panel's path.
- `silent` notifications now work with Filament v5: the flag is read from `->viewData(['silent' => true])` (Filament's `Notification` has no `->data()` method). README updated accordingly.

### Added

- Browser notifications now respect `->openUrlInNewTab()` on actions — the service worker opens a new window/tab instead of navigating the existing one.

## [1.1.1] - 2026-06-08

### Fixed

- The profile section now renders on simple profile pages (`->profile(isSimple: true)`). It is registered on both the `PAGE_END` and `SIMPLE_PAGE_END` render hooks, scoped to the panel's profile page, so it appears consistently for both simple and standard profile pages.
- `browser-notifications:install` now applies the `HasPushSubscriptions` trait correctly when `Notifiable` is declared alongside other traits (e.g. `use Notifiable, HasRoles;` or `use HasApiTokens, Notifiable;`). Previously the trait was imported but never added to the class body, causing `BadMethodCallException` on `User::pushSubscriptions()`.
- The install command's idempotency guard now detects whether the trait is actually applied in the class body rather than merely imported, so a half-finished install can be completed by re-running the command instead of reporting "already present".
- The profile section no longer sits flush against the profile form; it now has top spacing.
- The install command no longer throws a raw filesystem exception when the User model is read-only (e.g. some Docker setups). It now degrades gracefully, skipping the trait step and printing instructions to add `HasPushSubscriptions` by hand. The write-permission requirement is documented in the README.

### Added

- Test suite (Pest) covering the User model trait patcher across `Notifiable` arrangements and idempotency.
