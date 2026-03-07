# 📖 Maia Framework Usage Examples

Practical patterns for building apps with Maia.

## 1) 🏗️ Bootstrap A New App

```bash
php bin/maia new my-app
cd my-app
cp .env.example .env
touch database/database.sqlite
```

## 2) 🔌 Wire App Bootstrap And Route Registration

Use `routes/api.php` as your route registration entrypoint.

`public/index.php`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Orm\Connection;

$app = App::create(__DIR__ . '/../config', __DIR__ . '/../.env');
$app->container()->instance(
    Connection::class,
    Connection::sqlite(__DIR__ . '/../database/database.sqlite', [
        'foreign_keys' => true,
        'busy_timeout' => 5000,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
    ])
);

// Route registration is explicit — keeps startup behavior predictable.
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
    // Register infra dependencies needed by middleware and controllers.
    $app->container()->instance(
        JwtService::class,
        new JwtService((string) Env::get('JWT_SECRET', 'change-me-please-change-me-please!'))
    );
    $app->container()->instance(Validator::class, new Validator());

    $app->registerController(UserController::class);
};
```

## 3) 🎯 Controller + Attribute Routes + DI

`app/Controllers/UserController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Maia\Auth\JwtMiddleware;
use Maia\Auth\Validator;
use Maia\Core\Exceptions\ValidationException;
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
    public function create(Request $request, Validator $validator): Response
    {
        $payload = $request->body();
        $data = is_array($payload) ? $payload : [];

        // Validate before persisting.
        $errors = $validator->validate($data, [
            'email' => 'required|email|max:255',
        ]);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = User::create([
            'email' => (string) $data['email'],
        ]);

        return Response::json([
            'id' => $user->id,
            'email' => $user->email,
        ], 201);
    }
}
```

Builtin scalar route params are validated before controller dispatch. For example, `/users/not-a-number` will not call `show(int $id)`; it returns `404` instead.

## 4) 🗃️ Model + Migration

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

Generate and edit a migration:

```bash
vendor/bin/maia create:migration create_users_table
```

`database/migrations/*.php`:

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

```bash
vendor/bin/maia migrate
```

### Aggregate Queries And Upserts

```php
$rows = User::query()
    ->select('users.id', 'users.email', 'COUNT(posts.id) AS post_count')
    ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
    ->groupBy('users.id', 'users.email')
    ->having('COUNT(posts.id)', 0, '>')
    ->orderBy('post_count', 'desc')
    ->get();

QueryBuilder::table('users', $connection)->upsert([
    'email' => 'mal@example.com',
    'name' => 'Mal',
], ['email']);
```

## 5) 🔗 Relationships

Models support `HasMany` and `BelongsTo` via attributes. Relationships are lazy-loaded on access and can be eager-loaded with `with()`.

`app/Models/User.php` (with posts):

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Maia\Orm\Attributes\HasMany;
use Maia\Orm\Attributes\Table;
use Maia\Orm\Model;

#[Table('users')]
class User extends Model
{
    public int $id;
    public string $email;

    // Infers foreign key "user_id" on the posts table.
    #[HasMany(Post::class)]
    public array $posts;
}
```

`app/Models/Post.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Maia\Orm\Attributes\BelongsTo;
use Maia\Orm\Attributes\Table;
use Maia\Orm\Model;

#[Table('posts')]
class Post extends Model
{
    public int $id;
    public int $user_id;
    public string $title;

    // Infers foreign key "user_id" on this model.
    #[BelongsTo(User::class)]
    public ?User $user;
}
```

Querying:

```php
// Lazy load — each access runs a query.
$user = User::find(1);
$posts = $user->posts;

// Eager load — one query per relation, avoids N+1.
$users = User::query()->with('posts')->get();
```

## 6) ✅ Validation

The `Validator` validates arrays against pipe-delimited rules. Inject it via the container (registered in `routes/api.php` above).

```php
$errors = $validator->validate($data, [
    'email'    => 'required|email|max:255',
    'name'     => 'required|string|min:2|max:100',
    'age'      => 'integer|min:0',
    'agree'    => 'required|boolean',
]);

// $errors is [] on success, or keyed by field:
// ['email' => ['The email field must be a valid email address.']]
```

Available rules: `required`, `string`, `email`, `integer`, `boolean`, `min:{n}`, `max:{n}`, `unique:{table}`.

## 7) 🛡️ Global Middleware

Add global middleware in your bootstrap:

```php
// CORS — limit which origins can call your API.
$app->addMiddleware(new Maia\Auth\CorsMiddleware(['https://app.example.com']));

// API key auth for service-to-service requests.
$app->addMiddleware(new Maia\Auth\ApiKeyMiddleware(['local-dev-key']));

// Rate limiting to reduce abuse.
$app->addMiddleware(Maia\Auth\RateLimit::perMinute(60));
```

Response caching can be wired the same way:

```php
use Maia\Core\Cache\FilesystemResponseCacheStore;
use Maia\Core\Middleware\ResponseCacheMiddleware;

$app->addMiddleware(new ResponseCacheMiddleware(
    new FilesystemResponseCacheStore(__DIR__ . '/../storage/cache/responses'),
    ttlSeconds: 60,
    namespace: 'api'
));
```

## 8) 🧪 HTTP Testing

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

```bash
vendor/bin/phpunit
```
