# Changelog

All notable changes to **dynart-micro** are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/).

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
