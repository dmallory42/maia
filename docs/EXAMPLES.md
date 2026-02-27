# Maia Framework Usage Examples

This document shows practical patterns for building a new app with Maia.

## 1) Bootstrap A New App

```bash
# Scaffold a new project from the framework workspace.
php bin/maia new my-app

# Enter the generated app directory.
cd my-app

# Create local environment config from template.
cp .env.example .env

# Create the default SQLite database file expected by config/database.php.
touch database/database.sqlite
```

## 2) Wire App Bootstrap And Route Registration

Use `routes/api.php` as your route registration entrypoint.

`public/index.php`:

```php
<?php

// Load Composer autoloader for Maia and app classes.
require __DIR__ . '/../vendor/autoload.php';

use Maia\Core\App;
use Maia\Core\Http\Request;

// Build app with config and .env loaded.
$app = App::create(__DIR__ . '/../config', __DIR__ . '/../.env');

// Route registration is explicit: load and execute the closure.
$registerRoutes = require __DIR__ . '/../routes/api.php';
$registerRoutes($app);

// Capture inbound HTTP request and send framework response.
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
    // Register infra dependencies needed by middleware/controllers.
    $app->container()->instance(
        JwtService::class,
        new JwtService((string) Env::get('JWT_SECRET', 'change-me-please-change-me-please!'))
    );
    $app->container()->instance(Validator::class, new Validator());

    // Register controller classes so their route attributes are discoverable.
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

// Controller prefix applies to all route paths in this class.
#[Controller('/users')]
// Class-level middleware protects all endpoints in this controller.
#[Middleware(JwtMiddleware::class)]
class UserController
{
    // GET /users
    #[Route('/', method: 'GET')]
    public function index(): Response
    {
        return Response::json([
            'users' => User::query()->orderBy('id')->get(),
        ]);
    }

    // GET /users/{id} with typed route parameter casting.
    #[Route('/{id}', method: 'GET')]
    public function show(int $id): Response
    {
        $user = User::find($id);
        if ($user === null) {
            // Consistent JSON error response shape.
            return Response::error('User not found', 404);
        }

        return Response::json([
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    // POST /users with JSON payload.
    #[Route('/', method: 'POST')]
    public function create(Request $request): Response
    {
        $payload = $request->body();
        $data = is_array($payload) ? $payload : [];

        $user = User::create([
            'email' => (string) ($data['email'] ?? ''),
        ]);

        // Return 201 for resource creation.
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

// Map this model to the "users" database table.
#[Table('users')]
class User extends Model
{
    // Public typed properties are hydrated from DB rows.
    public int $id;
    public string $email;
}
```

Create migration:

```bash
# Generate a timestamped migration skeleton.
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
        // Apply schema changes for this migration.
        $schema->create('users', function (Table $table): void {
            // Auto-incrementing primary key.
            $table->id();
            // Unique index prevents duplicate emails.
            $table->string('email')->unique();
        });
    }

    public function down(Schema $schema): void
    {
        // Reverse the up() changes for rollback safety.
        $schema->drop('users');
    }
};
```

Run migrations:

```bash
# Execute all pending migrations.
vendor/bin/maia migrate
```

## 5) Global Middleware

You can add global middleware in bootstrap:

```php
// CORS: limit browser origins allowed to call your API.
$app->addMiddleware(new Maia\Auth\CorsMiddleware(['https://app.example.com']));

// API key auth for service-to-service requests.
$app->addMiddleware(new Maia\Auth\ApiKeyMiddleware(['local-dev-key']));

// Basic in-memory rate limiting to reduce abuse in dev/small deployments.
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
        // Register controllers under test for this test case.
        return [UserController::class];
    }

    public function testGetUsersReturns200(): void
    {
        // High-level HTTP assertion using Maia test helpers.
        $this->get('/users')->assertStatus(200);
    }
}
```

Run tests:

```bash
# Run unit/integration tests.
vendor/bin/phpunit
```
