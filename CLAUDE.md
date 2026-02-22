# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**dynart-micro** is a micro PHP framework (v0.8.0) providing dependency injection, routing, templating, form handling, i18n, CLI support, and JWT-based authentication. PHP 8.0+, namespace `Dynart\Micro`, PSR-4 autoload from `src/`. Requires `firebase/php-jwt ^7.0`.

The test suite lives in a **separate repository** at `../dynart-micro-test/`. That project symlinks this library via a Composer path repository (`vendor/dynart/micro` → `../dynart-micro`). Always treat both folders as a single codebase.

## Running Tests

Tests are run from the **test project directory** (`../dynart-micro-test/`), not from this repo:

```bash
# All tests with coverage (from dynart-micro-test/)
php vendor/bin/phpunit --coverage-html reports/coverage-html --stderr

# Single test file
php vendor/bin/phpunit tests/RouterTest.php --stderr

# Single test method
php vendor/bin/phpunit --filter testMethodName --stderr

# Windows shortcut
unittest.bat
```

PHPUnit 9.5, config in `phpunit.xml.dist`. The `--stderr` flag is required because some tests exercise stdout. Coverage targets `vendor/dynart/micro/src` (symlinked to this repo).

## Architecture

### DI Container (`Micro`)

`Micro` is a static service locator / DI container. All services are singletons resolved via reflection-based constructor auto-wiring.

- `Micro::add(Interface::class, ConcreteClass::class)` — register a mapping
- `Micro::add(Class::class)` — register a concrete class (interface = class)
- `Micro::get(Interface::class)` — get or create singleton
- `Micro::create(Class::class)` — create a new (non-singleton) instance
- `Micro::run(AbstractApp $app)` — bootstrap: sets app, calls `fullInit()` then `fullProcess()`

Classes with a `postConstruct()` method get it called automatically after instantiation (used for lazy init that depends on other services being available).

**Micro callables**: `[ClassName::class, 'method']` — the class instance is resolved through DI automatically.

### Application Lifecycle

```
Micro::run(AbstractApp)
  → AbstractApp::fullInit()
      → Config loaded, Logger created
      → AbstractApp::init() (subclass hook)
      → Middlewares run in order
  → AbstractApp::fullProcess()
      → AbstractApp::process() (subclass hook)
      → finish()
```

Two app types: `WebApp` (HTTP: router + views + sessions + error pages) and `CliApp` (CLI: argv parsing + exit codes).

### Routing

Routes registered via `Router::add(path, callable, method)` or `@route` PHPDoc annotations / `#[Route]` PHP 8 attributes. Path variables use `?` wildcards (e.g., `/users/?/posts/?`). Controller methods returning a string send HTML; returning an array sends JSON.

`WebApp::useRouteAttributes()` enables attribute-based routing by adding the `AttributeProcessor` middleware.

### Config

INI-based (`parse_ini_file`), dot-notation keys (e.g., `app.base_url`). Supports:
- Environment variable override via `{{VAR_NAME}}` syntax
- Path alias: `~` expands to `app.root_path`
- Comma-separated values and hierarchical array parsing (`items.0.name`)

### View / Templating

PHP templates (`.phtml`), namespace folders (`view->addFolder('ns', 'path')` → `view->fetch('ns:template')`), layout/block system, theme overrides. Helper functions loaded from `views/functions.php`.

### Middleware

Implement `Middleware` interface (single `run()` method), register with `$app->addMiddleware()`. Runs after `init()`, before `process()`.

### Key Components

- **Form**: CSRF protection, field binding from request, validators, error tracking
- **Translation**: INI-based i18n, `add(namespace, folder)`, variable substitution in translations
- **LocaleResolver**: Middleware for Accept-Language header detection
- **EventService**: Pub/sub observer pattern
- **CliCommands**: CLI argument/flag parsing with named params (`-name value`) and boolean flags
- **Pager**: Pagination helper with URL generation
- **JwtAuth**: JWT authorization service — decodes Bearer tokens, resolves `sub` → `JwtUserInterface` via user resolver callback, enforces `#[Authorize]`/`#[AllowAnonymous]` attributes on controllers
- **JwtValidator**: Middleware that extracts and validates the `Authorization: Bearer` header using `firebase/php-jwt`
- **AuthorizationException**: Thrown by `JwtAuth::checkAuthorization()`, carries HTTP code (401 or 403); caught in `WebApp::handleException()` before logging

### JWT Auth Implementation Notes

- `JwtAuth` subscribes to `WebApp::EVENT_ROUTE_MATCHED` using `[$this, 'onRouteMatched']`, **not** `[self::class, 'onRouteMatched']`. The singleton is registered under `JwtAuthInterface::class` in the DI container; using `self::class` would cause `Micro::getCallable()` to look up `JwtAuth::class` which is not a registered key, and fail.
- `callable` cannot be used as a typed property in PHP — use `mixed` for nullable callable properties.
- `WebApp::useJwtAuth()` calls `$this->addMiddleware(AttributeProcessor::class)` itself (idempotent), so `useRouteAttributes()` does not need to be called first, though it typically is for route registration.
- `firebase/php-jwt` v6 has a security advisory (`PKSA-y2cr-5h3j-g3ys`); use `^7.0`. v7 also enforces a minimum 32-byte key for HS256.

## Test Project Structure (`../dynart-micro-test/`)

- `tests/` — PHPUnit test files (one per framework class, e.g., `MicroTest.php`, `RouterTest.php`)
- `src/ResettableMicro.php` — extends `Micro` to reset the static DI state between tests
- `configs/` — test `.ini` files used by ConfigTest, ViewTest, etc.
- `views/` — test templates including theme/namespace examples
- `translations/` — test locale files (`en.ini`, `hu.ini`)
- `errors/` — test error page HTML
- `example/` — working example application with controller, config, and `.htaccess`
