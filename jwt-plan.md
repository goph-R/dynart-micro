# JWT Authorization Plan

## Context

Add JWT-based authentication and attribute-driven authorization to dynart-micro. Controllers opt in with `#[Authorize('permission')]` and opt out with `#[AllowAnonymous]`. A `JwtUserInterface` object (`sub` + permissions) is available via `JwtAuthInterface`.

The framework does **not** handle database concerns. JWT tokens carry only `sub` (subject identifier). Mapping `sub` → roles → permissions is the application's responsibility, done through a **user resolver** callback that the application registers.

### Already implemented (prerequisite)

`EventServiceInterface` is registered in `App` constructor. Lifecycle events already exist:
- `App::EVENT_INIT_FINISHED` — emitted after middlewares run
- `WebApp::EVENT_ROUTE_MATCHED` — emitted after route match, before controller call, args: `[$callable, $params]` (callable is already resolved via `Micro::getCallable`)
- `CliApp::EVENT_COMMAND_MATCHED` — emitted before command call

---

## New files

### `src/JwtUserInterface.php`
```php
interface JwtUserInterface {
    public function sub(): string;
    public function permissions(): array;
    public function hasPermission(string $permission): bool;
}
```

### `src/JwtUser.php`
Default implementation — simple value object:
- `__construct(private string $sub, private array $permissions = [])`
- `sub(): string`
- `permissions(): array`
- `hasPermission(string $permission): bool` — checks `in_array`

### `src/JwtAuthInterface.php`
```php
interface JwtAuthInterface {
    public function setUserResolver(callable $resolver): void;
    public function setUser(JwtUserInterface $user): void;
    public function user(): ?JwtUserInterface;
    public function addClassAuthorization(string $className, string $permission): void;
    public function addMethodAuthorization(string $className, string $method, string $permission): void;
    public function addAllowAnonymous(string $className, string $method): void;
}
```

### `src/JwtAuth.php`
Implements `JwtAuthInterface`. Singleton service — holds the current user, authorization metadata, and user resolver.

**Event constants:**
- `EVENT_USER_SET = 'jwtauth.user_set'` — emitted when a user is set, args: `[JwtUserInterface]`
- `EVENT_AUTHORIZATION_GRANTED = 'jwtauth.authorization_granted'` — args: `[$callable]`
- `EVENT_AUTHORIZATION_DENIED = 'jwtauth.authorization_denied'` — args: `[$callable, int $code]`

**Properties:**
- `?JwtUserInterface $user = null`
- `?callable $userResolver = null` — `fn(string $sub, object $payload): JwtUserInterface`
- `array $classAuthorizations = []` — `['ClassName' => 'permission']`
- `array $methodAuthorizations = []` — `['ClassName::method' => 'permission']`
- `array $allowAnonymous = []` — `['ClassName::method' => true]`

**Constructor injection:** `ConfigInterface`, `EventServiceInterface` — reads `jwt.secret`, `jwt.algorithm` (default `HS256`), subscribes to `WebApp::EVENT_ROUTE_MATCHED`

**Methods:**
- `setUserResolver(callable $resolver): void` — sets the resolver
- `resolveUser(string $sub, object $payload): JwtUserInterface` — calls user resolver if set, otherwise returns `new JwtUser($sub)` (no permissions by default)
- `setUser(JwtUserInterface $user): void` — sets user, emits `EVENT_USER_SET`
- `user(): ?JwtUserInterface`
- `addClassAuthorization(string $className, string $permission): void`
- `addMethodAuthorization(string $className, string $method, string $permission): void`
- `addAllowAnonymous(string $className, string $method): void`
- `onRouteMatched(mixed $callable, array $params): void` — event handler, delegates to `checkAuthorization` only for array callables (class-method pairs)
- `checkAuthorization(array $callable): void` — authorization logic

**Constructor subscribes:**
```php
$this->eventService->subscribe(WebApp::EVENT_ROUTE_MATCHED, [self::class, 'onRouteMatched']);
```

**`onRouteMatched($callable, $params)` logic:**
```php
if (is_array($callable)) {
    $this->checkAuthorization($callable);
}
// Closures have no attributes — skip authorization
```

**`checkAuthorization([$instance, $methodName])` logic:**

The callable is already resolved (instance, not class name), so use `get_class($callable[0])` to get the class name.

1. Build key: `$className = get_class($callable[0])`, `$key = $className.'::'.$callable[1]`
2. If `allowAnonymous[$key]` exists → return (allow)
3. If `methodAuthorizations[$key]` exists → check that permission
4. Else if `classAuthorizations[$className]` exists → check that permission
5. Else → return (no attribute = no auth required)

"Check permission" means:
- If `$user` is null → emit `EVENT_AUTHORIZATION_DENIED`, throw `AuthorizationException(401)`
- If permission is non-empty and `!$user->hasPermission($permission)` → emit `EVENT_AUTHORIZATION_DENIED`, throw `AuthorizationException(403)`
- Otherwise → emit `EVENT_AUTHORIZATION_GRANTED`

### `src/AuthorizationException.php`
Extends `MicroException`. Constructor takes `int $code` (401 or 403), passes to parent so `getCode()` works.

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
- Implements `AttributeHandlerInterface`
- Constructor injection: `JwtAuthInterface`
- `attributeClass()` → `Authorize::class`
- `targets()` → `[TARGET_CLASS, TARGET_METHOD]`
- `handle()`:
  - If `$subject` is `ReflectionClass` → `$jwtAuth->addClassAuthorization($className, $attribute->permission)`
  - If `$subject` is `ReflectionMethod` → `$jwtAuth->addMethodAuthorization($className, $subject->getName(), $attribute->permission)`

### `src/AttributeHandler/AllowAnonymousAttributeHandler.php`
- Implements `AttributeHandlerInterface`
- Constructor injection: `JwtAuthInterface`
- `attributeClass()` → `AllowAnonymous::class`
- `targets()` → `[TARGET_METHOD]`
- `handle()` → `$jwtAuth->addAllowAnonymous($className, $subject->getName())`

### `src/Middleware/JwtValidator.php`
- Implements `MiddlewareInterface`
- Constructor injection: `RequestInterface`, `JwtAuthInterface`, `ConfigInterface`
- `run()`:
  1. Get `Authorization` header from Request
  2. If no header or not `Bearer ...` → return (no user, authorization check happens later)
  3. Extract token string after `Bearer `
  4. Decode with `Firebase\JWT\JWT::decode($token, new Key($secret, $algorithm))`
  5. On success: `$user = $jwtAuth->resolveUser($payload->sub, $payload)`, then `$jwtAuth->setUser($user)`
  6. On JWT exceptions → throw `AuthorizationException(401)`

---

## Modified files

### `src/WebApp.php`
Add `useJwtAuth()` setup method (mirrors `useRouteAttributes()`):
```php
public function useJwtAuth(): void {
    $this->addMiddleware(JwtValidator::class);
    Micro::add(JwtAuthInterface::class, JwtAuth::class);
    Micro::add(AuthorizeAttributeHandler::class);
    Micro::add(AllowAnonymousAttributeHandler::class);
    // AttributeProcessor must already be added (via useRouteAttributes)
    $processor = Micro::get(AttributeProcessor::class);
    $processor->add(AuthorizeAttributeHandler::class);
    $processor->add(AllowAnonymousAttributeHandler::class);
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

        // Application provides the sub → permissions mapping
        $jwtAuth = Micro::get(JwtAuthInterface::class);
        $jwtAuth->setUserResolver(function(string $sub, object $payload): JwtUserInterface {
            // Query your database: sub → roles → permissions
            $userService = Micro::get(UserService::class);
            $permissions = $userService->getPermissionsForSub($sub);
            return new JwtUser($sub, $permissions);
        });
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

Config (`config.ini`):
```ini
jwt.secret = {{JWT_SECRET}}
jwt.algorithm = HS256
```

JWT payload (only `sub` is required by the framework — everything else is application-specific):
```json
{
    "sub": "user-123",
    "exp": 1234567890
}
```

---

## Permissions flow

The framework deliberately separates concerns:

1. **JWT token** carries only identity (`sub`) and standard claims (`exp`, `iat`, etc.)
2. **JwtValidator** (middleware) decodes the token and calls `JwtAuth::resolveUser($sub, $payload)`
3. **User resolver** (application callback) maps `sub` → roles → permissions via the application's database
4. **JwtAuth** receives the fully-resolved `JwtUserInterface` and checks permissions against `#[Authorize]` requirements

```
JWT token → JwtValidator → resolveUser(sub, payload) → Application DB lookup → JwtUserInterface(sub, permissions)
                                                                                      ↓
                                              JwtAuth::checkAuthorization() ← EVENT_ROUTE_MATCHED
```

If no user resolver is set, `resolveUser()` returns `new JwtUser($sub)` with an empty permissions array. This means `#[Authorize]` (no permission) passes (user is authenticated), but `#[Authorize('admin')]` fails (no permissions).

---

## Request lifecycle with JWT auth

```
Micro::run(WebApp)
  → fullInit()
    → Config loaded (jwt.secret, jwt.algorithm)
    → Logger initialized
    → init()
      → useRouteAttributes()
      → useJwtAuth()
        → JwtAuth created → subscribes to WebApp::EVENT_ROUTE_MATCHED
        → Application sets user resolver
    → runMiddlewares()
      → JwtValidator::run()       ← decode token, call resolveUser(sub, payload)
                                     → user resolver queries DB for permissions
                                     → setUser(JwtUserInterface) → emits EVENT_USER_SET
      → AttributeProcessor::run() ← register routes + store auth requirements
    → emit App::EVENT_INIT_FINISHED
  → fullProcess()
    → process()
      → Router::matchCurrentRoute()              ← find callable
      → Micro::getCallable()                     ← resolve to [instance, method]
      → emit WebApp::EVENT_ROUTE_MATCHED         ← triggers JwtAuth::onRouteMatched()
        → JwtAuth::checkAuthorization($callable) ← check JwtUserInterface vs requirements
          → emits EVENT_AUTHORIZATION_GRANTED or EVENT_AUTHORIZATION_DENIED
      → call_user_func_array($callable)          ← execute controller method
```

---

## File summary

| File | Action |
|------|--------|
| `src/JwtUserInterface.php` | New |
| `src/JwtUser.php` | New |
| `src/JwtAuthInterface.php` | New |
| `src/JwtAuth.php` | New |
| `src/AuthorizationException.php` | New |
| `src/Attribute/Authorize.php` | New |
| `src/Attribute/AllowAnonymous.php` | New |
| `src/AttributeHandler/AuthorizeAttributeHandler.php` | New |
| `src/AttributeHandler/AllowAnonymousAttributeHandler.php` | New |
| `src/Middleware/JwtValidator.php` | New |
| `src/WebApp.php` | Modified (add `useJwtAuth()`, modify `handleException()`) |
| `composer.json` | Modified (add `firebase/php-jwt`) |

---

## Verification

```bash
cd /c/Users/gopher/Projects/dynart-micro-test
composer update
php vendor/bin/phpunit --stderr
```

New tests to write:
- `JwtUserTest.php` — test value object, `hasPermission` logic
- `JwtAuthTest.php` — test authorization logic (class/method/anonymous combinations), user resolver, verify events emitted
- `JwtValidatorTest.php` — test token extraction, valid/invalid/expired tokens, resolver integration
- `AuthorizeAttributeHandlerTest.php` — test attribute processing stores correct metadata
- `AllowAnonymousAttributeHandlerTest.php` — test attribute processing
- `WebAppTest.php` — add integration tests for 401/403 responses
