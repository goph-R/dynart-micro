# JWT Authorization Plan

## Context

Add JWT-based authentication and attribute-driven authorization to dynart-micro. Controllers opt in with `#[Authorize('permission')]` and opt out with `#[AllowAnonymous]`. A `User` object (id + permissions) is created from the JWT payload and made available via a `JwtAuth` service.

---

## New files

### `src/User.php`
Simple value object with constructor property promotion:
- `__construct(private string $id, private array $permissions = [])`
- `id(): string`
- `permissions(): array`
- `hasPermission(string $permission): bool` — checks `in_array`

### `src/JwtAuth.php`
Singleton service — holds the current user and authorization metadata.

**Properties:**
- `?User $user = null` — set by JwtValidator after token validation
- `array $classAuthorizations = []` — `['ClassName' => 'permission']` from class-level `#[Authorize]`
- `array $methodAuthorizations = []` — `['ClassName::method' => 'permission']` from method-level `#[Authorize]`
- `array $allowAnonymous = []` — `['ClassName::method' => true]` from `#[AllowAnonymous]`

**Constructor injection:** `Config` — reads `jwt.secret`, `jwt.algorithm` (default `HS256`)

**Methods:**
- `setUser(User $user): void`
- `user(): ?User`
- `addClassAuthorization(string $className, string $permission): void`
- `addMethodAuthorization(string $className, string $method, string $permission): void`
- `addAllowAnonymous(string $className, string $method): void`
- `checkAuthorization(array $callable): void` — called from `WebApp::process()`, see logic below

**`checkAuthorization([ClassName, methodName])` logic:**
1. If `allowAnonymous['ClassName::methodName']` exists → return (allow)
2. If `methodAuthorizations['ClassName::methodName']` exists → check that authorization
3. Else if `classAuthorizations['ClassName']` exists → check that authorization
4. Else → return (open by default, no attribute = no auth required)

"Check authorization" means:
- If `$user` is null → throw `AuthorizationException` with code 401
- If permission is non-empty and `!$user->hasPermission($permission)` → throw `AuthorizationException` with code 403

### `src/AuthorizationException.php`
Extends `MicroException`. Has a `$code` property (401 or 403) so `WebApp` can send the right HTTP status.

### `src/Attribute/Authorize.php`
```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Authorize {
    public function __construct(public string $permission = '') {}
}
```

### `src/Attribute/AllowAnonymous.php`
```php
#[Attribute(Attribute::TARGET_METHOD)]
class AllowAnonymous {}
```

### `src/AttributeHandler/AuthorizeAttributeHandler.php`
- Implements `AttributeHandler`
- Constructor injection: `JwtAuth`
- `attributeClass()` → `Authorize::class`
- `targets()` → `[TARGET_CLASS, TARGET_METHOD]`
- `handle()`:
  - If `$subject` is `ReflectionClass` → `$jwtAuth->addClassAuthorization($className, $attribute->permission)`
  - If `$subject` is `ReflectionMethod` → `$jwtAuth->addMethodAuthorization($className, $subject->getName(), $attribute->permission)`

### `src/AttributeHandler/AllowAnonymousAttributeHandler.php`
- Implements `AttributeHandler`
- Constructor injection: `JwtAuth`
- `attributeClass()` → `AllowAnonymous::class`
- `targets()` → `[TARGET_METHOD]`
- `handle()` → `$jwtAuth->addAllowAnonymous($className, $subject->getName())`

### `src/Middleware/JwtValidator.php`
- Implements `Middleware`
- Constructor injection: `Request`, `JwtAuth`, `Config`
- `run()`:
  1. Get `Authorization` header from Request
  2. If no header or not `Bearer ...` → return (no user, authorization check happens later)
  3. Extract token string after `Bearer `
  4. Decode with `Firebase\JWT\JWT::decode($token, new Key($secret, $algorithm))`
  5. On success: create `User($payload->sub, $payload->permissions ?? [])`, call `$jwtAuth->setUser($user)`
  6. On `Firebase\JWT\ExpiredException` or other JWT exceptions → throw `AuthorizationException` with code 401

---

## Modified files

### `src/WebApp.php`
Add `useJwtAuth()` setup method (mirrors `useRouteAttributes()`):
```php
public function useJwtAuth(): void {
    $this->addMiddleware(JwtValidator::class);
    Micro::add(JwtAuth::class);
    Micro::add(AuthorizeAttributeHandler::class);
    Micro::add(AllowAnonymousAttributeHandler::class);
    // AttributeProcessor must already be added (via useRouteAttributes)
    $processor = Micro::get(AttributeProcessor::class);
    $processor->add(AuthorizeAttributeHandler::class);
    $processor->add(AllowAnonymousAttributeHandler::class);
}
```

Modify `process()` — add authorization check between route match and callable invocation:
```php
public function process(): void {
    list($callable, $params) = $this->router->matchCurrentRoute();
    if ($callable) {
        $this->checkAuthorization($callable);  // NEW
        $callable = Micro::getCallable($callable);
        $content = call_user_func_array($callable, $params);
        $this->sendContent($content);
    } else {
        $this->sendError(404);
    }
}
```

Add `checkAuthorization()` helper:
```php
protected function checkAuthorization(callable|array $callable): void {
    if (Micro::hasInterface(JwtAuth::class)) {
        Micro::get(JwtAuth::class)->checkAuthorization($callable);
    }
}
```

Modify `handleException()` — catch `AuthorizationException` and send proper HTTP status:
```php
protected function handleException(\Exception $e): void {
    if ($e instanceof AuthorizationException) {
        $this->sendError($e->getCode());
        return;
    }
    parent::handleException($e);
    // ... existing 500 error logic
}
```

### `composer.json`
Add dependency:
```json
{
  "require": {
    "firebase/php-jwt": "^6.0"
  }
}
```

---

## Usage example

```php
class MyApp extends WebApp {
    public function init(): void {
        parent::init();
        $this->useRouteAttributes();
        $this->useJwtAuth();
    }
}

class BooksController {
    #[Route('GET', '/api/books')]
    #[Authorize]  // any authenticated user
    public function list(): array { ... }

    #[Route('DELETE', '/api/books/?')]
    #[Authorize('admin')]  // needs 'admin' permission
    public function delete(string $id): array { ... }

    #[Route('GET', '/api/health')]
    #[AllowAnonymous]  // no auth needed
    public function health(): array { ... }
}

#[Authorize]  // all methods require auth by default
class AdminController {
    #[Route('GET', '/api/admin/stats')]
    public function stats(): array { ... }  // inherits class-level auth

    #[Route('GET', '/api/admin/public-info')]
    #[AllowAnonymous]  // overrides class-level
    public function publicInfo(): array { ... }
}
```

Config (`config.ini.php`):
```ini
jwt.secret = {{JWT_SECRET}}
jwt.algorithm = HS256
```

Expected JWT payload:
```json
{
    "sub": "user-123",
    "permissions": ["admin", "editor"],
    "exp": 1234567890
}
```

---

## Request lifecycle with JWT auth

```
Micro::run(WebApp)
  → fullInit()
    → Config loaded (jwt.secret, jwt.algorithm)
    → Logger initialized
    → init() → useRouteAttributes() + useJwtAuth()
    → runMiddlewares()
      → JwtValidator::run()       ← extract token, create User if valid
      → AttributeProcessor::run() ← register routes + store auth requirements
  → fullProcess()
    → process()
      → Router::matchCurrentRoute()      ← find callable
      → checkAuthorization($callable)    ← check User vs requirements
      → call_user_func_array($callable)  ← execute controller method
```

---

## File summary

| File | Action |
|------|--------|
| `src/User.php` | New |
| `src/JwtAuth.php` | New |
| `src/AuthorizationException.php` | New |
| `src/Attribute/Authorize.php` | New |
| `src/Attribute/AllowAnonymous.php` | New |
| `src/AttributeHandler/AuthorizeAttributeHandler.php` | New |
| `src/AttributeHandler/AllowAnonymousAttributeHandler.php` | New |
| `src/Middleware/JwtValidator.php` | New |
| `src/WebApp.php` | Modified |
| `composer.json` | Modified |

---

## Verification

```bash
cd /c/Users/gopher/Projects/dynart-micro-test
composer update
php vendor/bin/phpunit --stderr
```

New tests to write:
- `JwtAuthTest.php` — unit test authorization logic (class/method/anonymous combinations)
- `JwtValidatorTest.php` — test token extraction, valid/invalid/expired tokens
- `AuthorizeAttributeHandlerTest.php` — test attribute processing stores correct metadata
- `WebAppTest.php` — add integration tests for 401/403 responses
