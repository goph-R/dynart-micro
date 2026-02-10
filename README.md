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

[API Docs](https://micro.dynart.net/docs/api/) &#x2022; [Coverage Report](https://micro.dynart.net/reports/coverage-html/) &#x2022; [Test Repo](https://github.com/goph-R/dynart-micro-test)

</td>
</tr>
</table>

---

## &#x1F4E6; Installation

```bash
composer require dynart/micro
```

## &#x26A1; Quick Start

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
namespace MyApp\Controllers;

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
            "MyApp\\": "."
        }
    }
}
```

### config.ini.php

```ini
;<?php /*
app.root_path = /full/path/to/your/webapp
app.base_url = http://url/to/your/webapp
app.scan_namespaces = MyApp
```

## &#x1F3D7;&#xFE0F; Architecture

```
Micro::run(App)
  ├── fullInit()
  │   ├── Config loaded (INI files)
  │   ├── Logger created
  │   ├── App::init()           ← register routes, services
  │   ├── Middlewares run        ← locale, attributes, custom
  │   └── emit init_finished
  └── fullProcess()
      └── App::process()        ← dispatch route / CLI command
```

## &#x1F9F1; Components

### &#x1F4A1; DI Container — `Micro`

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

### &#x1F6E3;&#xFE0F; Routing — `Router`

Path variables use `?` wildcards. Controller methods return `string` for HTML or `array` for JSON.

```php
$router->add('/users/?/posts/?', [UserController::class, 'posts']);
$router->add('/api/login', [AuthController::class, 'login'], 'POST');
$router->add('/search', [SearchController::class, 'index'], 'BOTH'); // GET + POST
```

**PHP 8 attributes:**

```php
class BookController {

    #[Route('GET', '/books')]
    public function list(): string { /* ... */ }

    #[Route('GET', '/books/?')]
    public function show(string $id): array { /* ... */ }
}
```

### &#x2699;&#xFE0F; Configuration — `Config`

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

### &#x1F3A8; View / Templating — `View`

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

### &#x1F4DD; Forms — `Form`

CSRF protection, field binding, validators, error tracking.

```php
$form = new Form($request, $session, 'login');
$form->add('email', '');
$form->add('password', '');
$form->addValidator('email', new EmailValidator());
$form->generateCsrf();

if ($form->bind() && $form->validate()) {
    // $form->value('email'), $form->value('password')
}
```

### &#x1F310; Internationalization — `Translation`

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

### &#x1F4E1; Events — `EventService`

Pub/sub observer pattern with DI-compatible callables.

```php
$events->subscribe('user.created', [NotificationService::class, 'onUserCreated']);
$events->emit('user.created', [$user]);
```

Built-in events: `app.init_finished`, `webapp.route_matched`, `cliapp.command_matched`

### &#x1F4BB; CLI Support — `CliApp`

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

### &#x1F50C; Middleware

Implement `MiddlewareInterface` and register. Runs after `init()`, before `process()`.

```php
class AuthMiddleware implements MiddlewareInterface {
    public function run(): void {
        // check auth, redirect if needed
    }
}

// In App::init():
$this->addMiddleware(AuthMiddleware::class);
```

## &#x1F5C2;&#xFE0F; Project Structure

```
src/
├── Micro.php                 DI container
├── App.php                   Abstract application base
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
├── Validator.php             Abstract validator base
├── UploadedFile.php          File upload wrapper
├── Attribute/
│   └── Route.php             #[Route] attribute
├── AttributeHandler/
│   └── RouteAttributeHandler.php
└── Middleware/
    ├── AttributeProcessor.php
    └── LocaleResolver.php
```

## &#x1F50D; Related Packages

| Package | Description |
|---------|-------------|
| [dynart-micro-entities](https://github.com/goph-R/dynart-micro-entities) | ORM / entity layer with PDO, query builder, dirty tracking |

## &#x1F4C4; License

[Apache 2.0](LICENSE)
