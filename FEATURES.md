# Features

- **DI Container** — static service locator, reflection-based constructor auto-wiring, singletons, `postConstruct()` hook
- **Routing** — `?` wildcard paths, PHP 8 `#[Route]` attributes (on by default), HTML/JSON auto-detection
- **Configuration** — INI files, dot-notation keys, `{{ENV_VAR}}` substitution, `~` path alias
- **Templating** — PHP `.phtml` templates, namespace folders, layout/block system, theme overrides
- **Forms** — CSRF protection, request binding, validator pipeline, error tracking
- **i18n** — INI locale files, variable substitution, `LocaleResolver` middleware (Accept-Language + URL prefix)
- **Events** — pub/sub `EventService`, DI-compatible callables, built-in lifecycle events
- **JWT Auth** — `#[Authorize]` / `#[AllowAnonymous]` attributes, user resolver callback, 401/403 handling
- **Middleware** — `MiddlewareInterface`, ordered execution between `init()` and `process()`
- **CLI** — `CliApp`, named params (`-key value`) and boolean flags, exit codes
- **Session** — thin `$_SESSION` wrapper
- **Logging** — PSR-3 logger
- **Pagination** — `Pager` helper with URL generation
- **File uploads** — `UploadedFile` wrapper
