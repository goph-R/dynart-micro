# JWT Creation

The framework validates tokens but does not issue them — that is the application's responsibility.

Using `firebase/php-jwt` (already required by the framework):

```php
use Firebase\JWT\JWT;

$payload = [
    'sub' => $user->id,          // who the token identifies
    'iat' => time(),             // issued at
    'exp' => time() + 3600,      // expires in 1 hour
];

$token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
```

The framework's `JwtValidator` middleware decodes the token on each request and passes `$payload->sub` and the full `$payload` object to your user resolver:

```php
$jwtAuth->setUserResolver(function(string $sub, object $payload): JwtUserInterface {
    $permissions = Micro::get(UserService::class)->getPermissions($sub);
    return new JwtUser($sub, $permissions);
});
```

`sub` can be any value your `UserService` can look up (typically a user ID or email). The `exp` claim is validated automatically — an expired token causes a 401 response.
