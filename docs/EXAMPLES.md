# Maia Framework Usage Examples

This document shows practical patterns for building a new app with Maia.

## 1) Bootstrap A New App

```bash
# from the framework repo
php bin/maia new my-app

cd my-app
cp .env.example .env
touch database/database.sqlite
```

## 2) Wire App Bootstrap And Route Registration

Use `routes/api.php` as your route registration entrypoint.

`public/index.php`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Maia\Core\App;
use Maia\Core\Http\Request;

$app = App::create(__DIR__ . '/../config', __DIR__ . '/../.env');

$registerRoutes = require __DIR__ . '/../routes/api.php';
$registerRoutes($app);

$request = Request::capture();
$response = $app->handle($request);
$response->send();
```

`routes/api.php`:

```php
<?php

declare(strict_types=1);

use App\Controllers\UserController;
use Maia\Auth\JwtService;
use Maia\Auth\Validator;
use Maia\Core\App;
use Maia\Core\Config\Env;

return static function (App $app): void {
    $app->container()->instance(
        JwtService::class,
        new JwtService((string) Env::get('JWT_SECRET', 'change-me-please-change-me-please!'))
    );
    $app->container()->instance(Validator::class, new Validator());

    $app->registerController(UserController::class);
};
```

## 3) Controller + Attribute Routes + DI

`app/Controllers/UserController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Maia\Auth\JwtMiddleware;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\MiddlewareAttribute as Middleware;
use Maia\Core\Routing\Route;

#[Controller('/users')]
#[Middleware(JwtMiddleware::class)]
class UserController
{
    #[Route('/', method: 'GET')]
    public function index(): Response
    {
        return Response::json([
            'users' => User::query()->orderBy('id')->get(),
        ]);
    }

    #[Route('/{id}', method: 'GET')]
    public function show(int $id): Response
    {
        $user = User::find($id);
        if ($user === null) {
            return Response::error('User not found', 404);
        }

        return Response::json([
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    #[Route('/', method: 'POST')]
    public function create(Request $request): Response
    {
        $payload = $request->body();
        $data = is_array($payload) ? $payload : [];

        $user = User::create([
            'email' => (string) ($data['email'] ?? ''),
        ]);

        return Response::json([
            'id' => $user->id,
            'email' => $user->email,
        ], 201);
    }
}
```

## 4) Model + Migration

`app/Models/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Maia\Orm\Attributes\Table;
use Maia\Orm\Model;

#[Table('users')]
class User extends Model
{
    public int $id;
    public string $email;
}
```

Create migration:

```bash
vendor/bin/maia create:migration create_users_table
```

Edit generated file in `database/migrations/*.php`:

```php
<?php

declare(strict_types=1);

use Maia\Orm\Migration;
use Maia\Orm\Schema\Schema;
use Maia\Orm\Schema\Table;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Table $table): void {
            $table->id();
            $table->string('email')->unique();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('users');
    }
};
```

Run migrations:

```bash
vendor/bin/maia migrate
```

## 5) Global Middleware

You can add global middleware in bootstrap:

```php
$app->addMiddleware(new Maia\Auth\CorsMiddleware(['https://app.example.com']));
$app->addMiddleware(new Maia\Auth\ApiKeyMiddleware(['local-dev-key']));
$app->addMiddleware(Maia\Auth\RateLimit::perMinute(60));
```

## 6) HTTP Testing

`tests/UserControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\UserController;
use Maia\Core\Testing\TestCase;

class UserControllerTest extends TestCase
{
    protected function controllers(): array
    {
        return [UserController::class];
    }

    public function testGetUsersReturns200(): void
    {
        $this->get('/users')->assertStatus(200);
    }
}
```

Run tests:

```bash
vendor/bin/phpunit
```
