# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**dynart-micro** is a micro PHP framework (v0.6.0) providing dependency injection, routing, templating, form handling, i18n, and CLI support. PHP 8.0+, namespace `Dynart\Micro`, PSR-4 autoload from `src/`.

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
- `Micro::run(App $app)` — bootstrap: sets app, calls `fullInit()` then `fullProcess()`

Classes with a `postConstruct()` method get it called automatically after instantiation (used for lazy init that depends on other services being available).

**Micro callables**: `[ClassName::class, 'method']` — the class instance is resolved through DI automatically.

### Application Lifecycle

```
Micro::run(App)
  → App::fullInit()
      → Config loaded, Logger created
      → App::init() (subclass hook)
      → Middlewares run in order
  → App::fullProcess()
      → App::process() (subclass hook)
      → finish()
```

Two app types: `WebApp` (HTTP: router + views + sessions + error pages) and `CliApp` (CLI: argv parsing + exit codes).

### Routing

Routes registered via `Router::add(path, callable, method)` or `@route` PHPDoc annotations / `#[Route]` PHP 8 attributes. Path variables use `?` wildcards (e.g., `/users/?/posts/?`). Controller methods returning a string send HTML; returning an array sends JSON.

`WebApp::useRouteAnnotations()` enables annotation-based routing by adding the `AnnotationProcessor` middleware.

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

## Test Project Structure (`../dynart-micro-test/`)

- `tests/` — PHPUnit test files (one per framework class, e.g., `MicroTest.php`, `RouterTest.php`)
- `src/ResettableMicro.php` — extends `Micro` to reset the static DI state between tests
- `configs/` — test `.ini` files used by ConfigTest, ViewTest, etc.
- `views/` — test templates including theme/namespace examples
- `translations/` — test locale files (`en.ini`, `hu.ini`)
- `errors/` — test error page HTML
- `example/` — working example application with controller, config, and `.htaccess`
