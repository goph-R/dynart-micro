# Micro PHP Framework

![PHP](https://img.shields.io/badge/PHP-8.0+-8892BF)
![License](https://img.shields.io/badge/License-Apache_2.0-D22128)
![Version](https://img.shields.io/badge/Version-0.8.0-2ea44f)

A lightweight PHP micro framework with dependency injection, routing, templating, i18n, form handling, and CLI support. No magic, no bloat — just the essentials.

<table>
<tr>
<td>

**Quick links**

</td>
<td>

[API Docs](https://micro.dynart.net/docs/api/) • [Coverage Report](https://micro.dynart.net/reports/coverage-html/) • [Test Repo](https://github.com/goph-R/dynart-micro-test)

</td>
</tr>
</table>

---

## 📦 Installation

```bash
composer require dynart/micro
```

## ⚡ Quick Start

### index.php

```php
<?php

use Dynart\Micro\Micro;
use Dynart\Micro\WebApp;

require 'vendor/autoload.php';

Micro::run(new WebApp(['config.ini.php']));
```

### Controllers/HomeController.php

```php
namespace App\Controllers;

use Dynart\Micro\Attribute\Route;

class HomeController {
    #[Route('GET', '/')]
    public function index(): string {
        return '<h1>Hello world!</h1>';
    }
}
```

### composer.json

```json
{
    "require": {
        "dynart/micro": "^0.8"
    },
    "autoload": {
        "psr-4": {
            "App\\": "."
        }
    }
}
```

### config.ini.php

```ini
;<?php /*
app.root_path = /full/path/to/your/webapp
app.base_url = http://url/to/your/webapp
app.scan_namespaces = App
```

## 🏗️ Architecture

```
Micro::run(App)
  ├── fullInit()
  │   ├── Config loaded (INI files)
  │   ├── Logger created
  │   ├── AbstractApp::init()           ← register routes, services
  │   ├── Middlewares run        ← locale, attributes, custom
  │   └── emit init_finished
  └── fullProcess()
      └── AbstractApp::process()        ← dispatch route / CLI command
```

## 🧱 Components

### 💡 DI Container — `Micro`

Static service locator with reflection-based auto-wiring. All services are singletons.

```php
// Register
Micro::add(ConfigInterface::class, Config::class);
Micro::add(MyService::class);

// Resolve (auto-creates with constructor injection)
$service = Micro::get(MyService::class);

// Factory (new instance each time)
$instance = Micro::create(MyService::class, ['extraParam']);
```

Classes with a `postConstruct()` method get it called automatically after creation.

### 🛣️ Routing — `Router`

Path variables use `?` wildcards. Controller methods return `string` for HTML or `array` for JSON.

```php
$router->add('/users/?/posts/?', [UserController::class, 'posts']);
$router->add('/api/login', [AuthController::class, 'login'], 'POST');
$router->add('/search', [SearchController::class, 'index'], 'BOTH'); // GET + POST
```

**PHP 8 attributes** (active by default, controlled by `app.use_route_attributes`):

```php
class BookController {

    #[Route('GET', '/books')]
    public function list(): string { /* ... */ }

    #[Route('GET', '/books/?')]
    public function show(string $id): array { /* ... */ }
}
```

### ⚙️ Configuration — `Config`

INI-based with dot-notation keys, environment variable substitution, and path aliases.

```ini
app.root_path = /var/www/myapp
app.base_url = https://myapp.com
app.environment = prod

; Environment variable override
database.password = {{DB_PASSWORD}}

; Path alias: ~ expands to app.root_path
view.default_folder = ~/views

; Comma-separated values
allowed.origins = http://localhost,https://example.com
```

```php
$config->get('app.base_url');                       // string
$config->getCommaSeparatedValues('allowed.origins'); // array
$config->getArray('database');                       // nested array
$config->getFullPath('~/uploads');                   // /var/www/myapp/uploads
```

### 🎨 View / Templating — `View`

PHP templates (`.phtml`) with namespaces, layouts, blocks, and theme overrides.

```php
// Register namespace folder
$view->addFolder('admin', '~/views/admin');

// Set theme (overrides any template)
$view->setTheme('~/themes/dark');

// Render
$html = $view->fetch('admin:dashboard', ['user' => $user]);
```

**Layout system:**

```php
<!-- views/layout.phtml -->
<html>
<body><?= $this->block('content') ?></body>
</html>
```

```php
<!-- views/page.phtml -->
<?php $this->useLayout('layout'); ?>
<?php $this->startBlock('content'); ?>
  <h1>Hello</h1>
<?php $this->endBlock(); ?>
```

### 📝 Forms — `Form`

CSRF protection, field binding, validators, error tracking.
*Work in progress*

```php
$form = new Form($request, $session, 'login');
$form->addFields([
    'email' => ['type' => 'text'],
    'password' => ['type' => 'password']
]);
$form->addValidator('email', new EmailValidator());
$form->generateCsrf();

if ($form->bind() && $form->validate()) {
    // $form->value('email'), $form->value('password')
}
```

### 🌐 Internationalization — `Translation`

INI-based locale files with variable substitution.

```ini
; translations/en.ini
greeting = Hello, {name}!
```

```php
$translation->add('app', '~/translations');
echo $translation->get('app:greeting', ['name' => 'World']); // Hello, World!
```

**Auto locale detection** via `LocaleResolver` middleware (Accept-Language header + URL prefix):

```php
$this->addMiddleware(LocaleResolver::class);
// Routes become: /en/books, /hu/books, etc.
```

### 📡 Events — `EventService`

Pub/sub observer pattern with DI-compatible callables.

```php
$events->subscribe('user:created', [NotificationService::class, 'onUserCreated']);
$events->emit('user:created', [$user]);
```

Built-in events: `app:init_finished`, `webapp:route_matched`, `cliapp:command_matched`

### 💻 CLI Support — `CliApp`

Argument parsing with named params and boolean flags.

```php
class MyCliApp extends CliApp {
    public function init(): void {
        $commands = Micro::get(CliCommandsInterface::class);
        $commands->add('migrate', [MigrateCommand::class, 'run']);
        $commands->add('seed', [SeedCommand::class, 'run']);
    }
}

// Usage: php cli.php migrate -step 5 --fresh
```

### 🔐 JWT Authentication — `JwtAuth`

Attribute-driven authorization backed by JWT tokens. Enable with `useJwtAuth()`. Route attributes are active by default — no extra setup needed.

```php
class App extends WebApp {
    public function init(): void {
        parent::init();
        $this->useJwtAuth();

        // Map sub → permissions (your DB lookup)
        Micro::get(JwtAuthInterface::class)->setUserResolver(
            function(string $sub, object $payload): JwtUserInterface {
                $perms = Micro::get(UserService::class)->getPermissionsForSub($sub);
                return new JwtUser($sub, $perms);
            }
        );
    }
}
```

**Controller annotations:**

```php
#[Authorize]               // class-level: all methods require authentication
class AdminController {

    #[Route('GET', '/api/admin/stats')]
    public function stats(): array { /* inherits class-level auth */ }

    #[Route('GET', '/api/admin/ping')]
    #[AllowAnonymous]                  // overrides class-level
    public function ping(): array { return ['ok' => true]; }
}

class ApiController {

    #[Route('GET', '/api/books')]
    #[Authorize]                       // any authenticated user
    public function list(): array { /* ... */ }

    #[Route('DELETE', '/api/books/?')]
    #[Authorize('admin')]              // requires 'admin' permission
    public function delete(string $id): array { /* ... */ }
}
```

**Config:**

```ini
jwt.secret = {{JWT_SECRET}}
jwt.algorithm = HS256
```

The framework only validates the token and resolves permissions. Issuing tokens (e.g. after OAuth with Google/Facebook) is the application's responsibility. Requires [`firebase/php-jwt`](https://github.com/firebase/php-jwt) `^7.0`.

**Events emitted by `JwtAuth`:** `jwtauth:user_set`, `jwtauth:authorization_granted`, `jwtauth:authorization_denied`

### 🔌 Middleware

Implement `MiddlewareInterface` and register. Runs after `init()`, before `process()`.

```php
class AuthMiddleware implements MiddlewareInterface {
    public function run(): void {
        // check auth, redirect if needed
    }
}

// In AbstractApp::init():
$this->addMiddleware(AuthMiddleware::class);
```

## 🗂️ Project Structure

```
src/
├── Micro.php                 DI container
├── AbstractApp.php                   Abstract application base
├── WebApp.php                HTTP application
├── CliApp.php                CLI application
├── Config.php                INI configuration
├── Router.php                URL routing
├── View.php                  Template engine
├── Form.php                  Form handling + CSRF
├── Translation.php           i18n
├── EventService.php          Pub/sub events
├── Session.php               Session wrapper
├── Request.php               HTTP request
├── Response.php              HTTP response
├── Logger.php                PSR-3 logging
├── Pager.php                 Pagination helper
├── AbstractValidator.php             Abstract validator base
├── UploadedFile.php          File upload wrapper
├── JwtAuth.php               JWT authorization service
├── JwtUser.php               Default JWT user value object
├── AuthorizationException.php  401/403 exception
├── Attribute/
│   ├── Route.php             #[Route] attribute
│   ├── Authorize.php         #[Authorize('permission')] attribute
│   └── AllowAnonymous.php    #[AllowAnonymous] attribute
├── AttributeHandler/
│   ├── RouteAttributeHandler.php
│   ├── AuthorizeAttributeHandler.php
│   └── AllowAnonymousAttributeHandler.php
└── Middleware/
    ├── AttributeProcessor.php
    ├── JwtValidator.php      Decodes Bearer token, resolves user
    └── LocaleResolver.php
```

## 🔍 Related Packages

| Package | Description |
|---------|-------------|
| [dynart-micro-entities](https://github.com/goph-R/dynart-micro-entities) | ORM / entity layer with PDO, query builder, dirty tracking |

## 📄 License

[Apache 2.0](LICENSE)
