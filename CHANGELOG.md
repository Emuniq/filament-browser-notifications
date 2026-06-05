# Changelog

All notable changes to `filament-browser-notifications` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Fixed

- The profile section now renders on simple profile pages (`->profile(isSimple: true)`). It is registered on both the `PAGE_END` and `SIMPLE_PAGE_END` render hooks, scoped to the panel's profile page, so it appears consistently for both simple and standard profile pages.
