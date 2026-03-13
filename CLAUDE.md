# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Composer library (`lthn/php-plug-business`) providing business platform integrations for the Plug framework. Currently implements Google My Business API. Licensed under EUPL-1.2.

## Commands

```bash
composer install          # Install dependencies
composer dump-autoload    # Regenerate autoloader after adding classes
```

No test suite is configured in this repository. No linter/formatter config present.

## Architecture

This is a **Plug provider package** — part of the `lthn/php` (core Plug framework) ecosystem. Each platform integration follows a consistent class-per-operation pattern.

### Plug Framework Pattern

Every operation class uses three traits from `Core\Plug\Concern\` and implements one contract from `Core\Plug\Contract\`:

| Trait | Purpose |
|-------|---------|
| `BuildsResponse` | Wraps results into `Core\Plug\Response` objects (`ok()`, `error()`, `fromHttp()`) |
| `ManagesTokens` | Stores/retrieves OAuth tokens via `withToken()` / `accessToken()` |
| `UsesHttp` | Provides Laravel HTTP client via `http()` |

| Contract | Method | Used by |
|----------|--------|---------|
| `Authenticable` | `getAuthUrl()`, `requestAccessToken()` | Auth |
| `Refreshable` | `refresh()` | Auth |
| `Postable` | `publish()` | Post |
| `Listable` | `listEntities()` | Locations |
| `Readable` | `get()`, `me()`, `list()` | Read |
| `Deletable` | `delete()` | Delete |

### Adding a New Platform

Create a new directory under `src/` (e.g., `src/Yelp/`) with classes following the same trait+contract pattern. Each class should use `BuildsResponse`, `ManagesTokens`, and `UsesHttp`, and implement the appropriate contract.

### Namespace

`Core\Plug\Business\` maps to `src/` via PSR-4 autoloading. Requires PHP 8.2+.

## Conventions

- `declare(strict_types=1)` on every file
- All public API methods return `Core\Plug\Response` (except static helpers like `getAuthUrl()`, `externalAccountUrl()`)
- Token-dependent methods check `$this->accessToken()` first and return `$this->error()` if missing
- Google API responses are normalized through `$this->fromHttp($response, fn ($data) => [...])` callbacks
- Uses Laravel HTTP client (via `UsesHttp` trait) and `Illuminate\Support\Collection` for media
