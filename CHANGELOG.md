# Changelog

All notable changes to **dynart-micro** are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/).

---

## [0.14.0] &ndash; 2026-08-04

### Added
- **Catch-all routes** — `*` as the last segment of a route matches the whole remainder of the path and passes it as one parameter, so `/docs/*` matching `/docs/guide/install` yields `guide/install`. Needed for hierarchical page paths, which `?` cannot express because it matches exactly one segment. It requires at least one segment, so `/docs/*` does not match `/docs`.
- `Router::hasCatchAll()`, and the `Router::SEGMENT` / `Router::CATCH_ALL` constants

### Changed
- **Exact and segment routes are matched before catch-all ones**, regardless of the order they were added. Without that, a `/*` registered early would swallow `/login` and route precedence would silently depend on registration order.

---

## [0.13.0] &ndash; 2026-08-04

Found by rendering real pages for the first time. The first two are why a form and a layout could never be combined.

### Fixed
- **`View::fetch()` was not re-entrant: a nested fetch inherited the caller's layout.** A partial rendered from inside a template that uses a layout would see that layout still set, and return the *whole page* as its own output. `Form::fetch()` does exactly that, so any form rendered inside a laid-out template produced garbage. Each fetch now starts with no layout of its own and restores the caller's afterwards.
- **Blocks leaked between independent renders.** `endBlock()` appends on purpose, so several templates can fill one block — but blocks were never cleared, so anything rendered earlier in the same request (a mail, a partial fetched from a service) was still in the block when the next page rendered, and appeared inside it. They are now cleared when a *top level* fetch starts; accumulation within one render is unchanged.

### Added
- **`View::NAMESPACE_MICRO`** — the framework's own views are registered under `micro:` automatically, so its partials resolve wherever the package sits. `Form` now fetches `micro:form-errors` / `micro:form-field` / `micro:form-input` through the new `Form::VIEW_*` constants. Previously those were namespace-less and resolved through `view.default_folder`, so they were unfindable unless an application copied them into its own view folder.
- **`Translation::NAMESPACE_MICRO`** — the framework's own translations are registered under `micro` automatically, so `Form`'s built-in messages can actually be translated. An application overrides them with `add('micro', $itsOwnFolder)`.

### Changed
- A theme overrides the form partials under `<theme>/micro/` now, rather than by shadowing them in the default view folder

---

## [0.12.0] &ndash; 2026-08-04

### Added
- **`View::exists()`** — is there a template at this path, resolved the same way `fetch()` resolves it, theme override included. For optional templates: catching the exception from `fetch()` would also swallow a `MicroException` thrown from *inside* a template that does exist, so a missing optional template and a broken one would look identical.

### Changed
- `ViewInterface` gained `exists()` — a breaking change for anything implementing it

---

## [0.11.0] &ndash; 2026-08-04

### Added
- **`JwtCookieReader` middleware** — lifts a JWT out of a cookie into the `Authorization` header, so `JwtValidator` and every `#[Authorize]` attribute keep working for a browser navigating to a server-rendered page, where there is nowhere to put that header. Must run *before* `JwtValidator` (a lower priority number). An `Authorization` header that is already present always wins, so an API client is never overridden by a stale cookie. Cookie name from `jwt.cookie_name`, default `token`.
- **Cookie support on `Response`** — `setCookie()`, `clearCookie()`, `cookie()`, `cookies()`, `clearCookies()`. Options are merged into `Response::DEFAULT_COOKIE_OPTIONS`, which sets `httponly` and `samesite=Lax` but leaves `secure` off: turning it on by default would make cookies silently vanish on a plain HTTP development site, which is far harder to diagnose than a missing flag. Turn it on from the application config in production. `send()` emits the cookies before the headers.

### Changed
- `ResponseInterface` gained the six cookie methods — a breaking change for anything implementing it

---

## [0.10.0] &ndash; 2026-08-04

### Fixed
- **`CliCommands::matchCurrent()` returned `null` for an unknown command**, while `CliApp::process()` destructures the result with `list()`. Every unrecognised or missing command therefore emitted two "Trying to access array offset on value of type null" warnings before printing its error — on the exact path a user hits by mistyping. It now returns the `CliCommands::COMMAND_NOT_FOUND` constant (`[null, null]`), mirroring `Router::ROUTE_NOT_FOUND`.

### Added
- `CliCommands::has()` — lets an application check whether a command exists before dispatching, so it can offer help instead of an error

### Changed
- **`CliCommandsInterface` gained `has()` and `matchCurrent()` is now `array` rather than `?array`** — a breaking change for anything implementing the interface

---

## [0.9.0] &ndash; 2026-08-04

### Fixed
- **`Form` input names did not round trip.** `form-input.phtml` rendered `name="formname_field"` while `Form::bind()` reads `$_REQUEST[formname]` as an array, which PHP only populates from `name="formname[field]"`. Every named form bound an empty value set and never redisplayed what the user typed. The template now renders through the new `Form::inputName()`, so the rendered name and `bind()` share one definition.
- **`Form::generateCsrf()` cleared all bound values.** It called `setValues()`, and `process()` calls `generateCsrf()` last, so a CSRF enabled form lost every value when redisplayed after a failed validation. It now uses `addValues()`.
- **`Form::idByNameAndField()` had an inverted condition.** It returned the generated id when an explicit `id` was set, and an undefined array index when it was not. Explicit ids now work and no notice is emitted.
- **`Form::addFields()` marked whole batches as required.** The `$required` parameter applied to every field in the call, so a mixed batch could not be expressed. Individual fields may now carry their own `required` key, which takes precedence.

### Added
- `Form::setTranslation()` — the built in `Required.` and `CSRF token is invalid.` messages are looked up in the `micro` translation namespace, falling back to English when no translation is set or the id is missing
- `translations/en.ini` with the built in form messages
- `Form` message constants: `MESSAGE_REQUIRED`, `MESSAGE_CSRF_INVALID`, `DEFAULT_MESSAGE_REQUIRED`, `DEFAULT_MESSAGE_CSRF_INVALID`, `TRANSLATION_NAMESPACE`
- `Form::setName()` and `Form::setCsrf()` / `Form::csrf()` — the name was previously constructor only, which blocked building forms through a factory
- `Form::inputName()` and `Form::inputId()` — the HTML name and id for a field, in one place
- `Form::beforeValidate()` and `Form::afterValidate()` — overridable hooks around validation in `process()`
- `Form::addFieldError()`, `Form::errors()`, `Form::formErrors()`, `Form::hasErrors()`
- `Form::validate()` split into overridable `validateCsrfValue()`, `validateRequiredFields()` and `runValidators()`

### Changed
- **Form level errors moved out of `errors['_form']`** into their own `formErrors` list. `Form::error()` now always returns a `?string` for a field; use `formErrors()` for the form itself. `form-errors.phtml` updated accordingly.
- `Form::bind()` ignores a non-array request value for a named form instead of assigning it

---

## [0.8.0] &ndash; 2026-02-09

### Added
- Native PHP 8.0 types: typed properties, return types, and parameter types across all source files
- Constructor property promotion (`UploadedFile`, `Router`, `LocaleResolver`)
- `#[Route]` PHP 8 attribute replacing `@route` PHPDoc annotations
- PSR-style interfaces for all service classes (`ConfigInterface`, `LoggerInterface`, `RouterInterface`, `RequestInterface`, `ResponseInterface`, `SessionInterface`, `ViewInterface`, `TranslationInterface`, `EventServiceInterface`, `MiddlewareInterface`, `CliCommandsInterface`, `CliOutputInterface`, `AttributeHandlerInterface`)
- `AttributeProcessor` middleware and `RouteAttributeHandler` for PHP 8 attribute processing
- `WebApp::useRouteAttributes()` convenience method
- Lifecycle events: `App::EVENT_INIT_FINISHED`, `WebApp::EVENT_ROUTE_MATCHED`, `CliApp::EVENT_COMMAND_MATCHED`
- Form rendering: `fetch()`, `fetchErrors()`, `fetchField()`, `fetchInput()`, `idByNameAndField()` methods
- Split form view templates: `form-errors.phtml`, `form-field.phtml`, `form-input.phtml`

### Changed
- Minimum PHP version bumped from 7.1 to 8.0
- PHPDoc moved from implementations to interfaces
- Removed redundant PHPDoc where native types suffice

### Removed
- `@route` PHPDoc annotation support (replaced by `#[Route]` attribute)
- Monolithic `form.phtml` (replaced by split partials)

---

## [0.7.0] &ndash; 2023-07-17

### Added
- `Micro` static DI container with reflection-based auto-wiring, circular dependency detection
- `Micro::getCallable()` for DI-compatible `[Class::class, 'method']` callables
- `EventService` pub/sub with `subscribe()`, `unsubscribe()`, `emit()`
- `CliApp` for CLI applications with command matching, argument/flag parsing, exit codes
- `CliOutput` with optional color support (`setUseColor()`)
- `Config::getFullPath()` with `~` alias expansion to `app.root_path`
- `Config::getArray()` for hierarchical dot-notation array parsing
- `Session::id()`
- `Router::prefixVariables()` getter
- `WebApp::useRouteAnnotations()` for annotation-based routing
- `PdoBuilder` fluent PDO factory
- Exception handling: circular dependency detection, original exception in Config/Logger failure messages
- Error pages in non-production environments show exception details

### Changed
- DI system moved to static `Micro` class (previously instance-based on `App`)
- `Micro::instance()` renamed to `Micro::app()`
- `App` refactored: `fullInit()` / `fullProcess()` lifecycle, `finish()` supports test mode
- `Request::method()` renamed to `Request::httpMethod()`
- Database-related classes extracted to separate `dynart-micro-entities` package
- Annotation processing refactored with `AnnotationProcessor::addNamespace()`

### Removed
- Built-in `Database`, `MariaDatabase`, `Repository` classes (moved to dynart-micro-entities)
- Built-in `Mailer` (removed along with PHPMailer dependency)

---

## [0.6.0] &ndash; 2023-03-20

### Added
- `Router` with wildcard path variables (`?`), URL generation, prefix variables
- `Pager` pagination helper with URL generation
- `Form` with CSRF protection, field binding, validators, error tracking
- `Validator` abstract base class
- `View` PHP template engine (`.phtml`) with namespaces, layouts, blocks, theme overrides
- `Translation` INI-based i18n with variable substitution
- `LocaleResolver` middleware for Accept-Language detection and URL prefix locale
- `Request` with `body()`, `bodyAsJson()`, `server()`, `uploadedFile()`
- `UploadedFile` wrapper
- `Session` wrapper with `get()`, `set()`, `has()`, `destroy()`
- `Config` INI-based configuration with dot-notation, `{{ENV_VAR}}` substitution, caching
- `Logger` PSR-3 compatible logging via KLogger
- `App` / `WebApp` application base with middleware pipeline
- View helper functions: `esc_html()`, `esc_attr()`

### Notes
- Initial public release
- PHP >= 7.1
- Namespace: `Dynart\Micro`, PSR-4 autoload from `src/`

---

## [0.1.0] &ndash; 2021-08-15

Initial commit with basic DI, routing, and view scaffolding.
