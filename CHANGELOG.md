# Changelog

All notable changes to `filament-browser-notifications` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Fixed

- The profile section now renders on simple profile pages (`->profile(isSimple: true)`). It is registered on both the `PAGE_END` and `SIMPLE_PAGE_END` render hooks, scoped to the panel's profile page, so it appears consistently for both simple and standard profile pages.
- `browser-notifications:install` now applies the `HasPushSubscriptions` trait correctly when `Notifiable` is declared alongside other traits (e.g. `use Notifiable, HasRoles;` or `use HasApiTokens, Notifiable;`). Previously the trait was imported but never added to the class body, causing `BadMethodCallException` on `User::pushSubscriptions()`.
- The install command's idempotency guard now detects whether the trait is actually applied in the class body rather than merely imported, so a half-finished install can be completed by re-running the command instead of reporting "already present".
- The profile section no longer sits flush against the profile form; it now has top spacing.

### Added

- Test suite (Pest) covering the User model trait patcher across `Notifiable` arrangements and idempotency.
