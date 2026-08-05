# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**dynart-micro** is a micro PHP framework (v0.19.0) providing dependency injection, routing, templating, form handling, i18n, CLI support, and JWT-based authentication. PHP 8.0+, namespace `Dynart\Micro`, PSR-4 autoload from `src/`. Requires `firebase/php-jwt ^7.0`.

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

`#[Route]` is repeatable, so one action can answer `GET` and `POST`.

**Class-level attributes are inherited.** PHP's are not, so `AttributeProcessor` walks up to the nearest ancestor that declares one and hands the handler the concrete class name. Without it an `#[Authorize]` on an abstract base controller applies to nothing — silently, and failing *open*. A subclass declaring its own wins.

### Config

INI-based (`parse_ini_file`), dot-notation keys (e.g., `app.base_url`). Supports:
- Environment variable override via `{{VAR_NAME}}` syntax
- Path alias: `~` expands to `app.root_path`
- Comma-separated values and hierarchical array parsing (`items.0.name`)

`getCommaSeparatedValues()` caches into its **own** map. It used to write the list it built under the name `get()` caches the raw value under, so asking twice exploded an array and fataled, and a `get()` in between returned a list where a string belonged. A missing setting is `[]`, not `['']`.

### The client address

`Request::ip()` is `REMOTE_ADDR` **unless the request came from an address in `request.trusted_proxies`**, in which case it is the rightmost `X-Forwarded-For` entry that is not itself a trusted proxy. It used to return the header whenever it was present, which meant anything counting or blocking by address could be handed a new one on every request — or somebody else's.

Empty by default, so an installation not behind a proxy cannot be told that it is. **Behind one, set it**, or every visitor arrives as the proxy and shares one identity.

### View / Templating

PHP templates (`.phtml`), namespace folders (`view->addFolder('ns', 'path')` → `view->fetch('ns:template')`), layout/block system, theme overrides.

**A namespace can refuse to be themed**: `addFolder('ns', 'path', false)`. Without it a theme overriding one template reaches every template in every namespace, which for an administration area is not a restyled page but somebody locked out of their own site. `exists()` honours the flag too, or it would disagree with `fetch()`. Helper functions loaded from `views/functions.php`.

A template body is `include`d **inside `View::fetch()`** and shares that method's scope, so a variable named like one of its locals would overwrite it. `fetch()` unsets the reserved names before extracting; still, never pass `get_defined_vars()` from a template to a nested fetch — it hands down the path of the file being included, and the template ends up including itself.

### Middleware

Implement `Middleware` interface (single `run()` method), register with `$app->addMiddleware()`. Runs after `init()`, before `process()`.

### Key Components

- **Form**: CSRF protection, field binding from request, validators, error tracking. `validateCsrf()` fails when the session holds no token — it used to compare loosely, and `null == ''` let any form the visitor had not rendered be posted from another site with an empty `_csrf`. `bind()` also binds uploaded files for `file` fields, since an upload arrives in `$_FILES` and a required file field could otherwise never be satisfied; read them with `uploadedFile()`. Field errors (`error()`, `errors()`) are kept separately from form-level errors (`addError()`, `formErrors()`). Built-in messages resolve through `setTranslation()` in the `micro` namespace with English fallbacks. `inputName()` produces `formname[field]` — it must stay in sync with `bind()`, which reads `$_REQUEST[formname]` as an array. `process()` calls the overridable `beforeValidate()` / `afterValidate()` hooks, and `validate()` is split into `validateCsrfValue()` / `validateRequiredFields()` / `runValidators()` for subclasses.
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
