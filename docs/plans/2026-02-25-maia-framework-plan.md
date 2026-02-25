# Maia Framework Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build Maia, an opinionated API-first PHP 8.2+ framework with attribute-based routing, auto-wiring DI, lightweight ORM, JWT/API key auth, and an agent-friendly CLI.

**Architecture:** Micro-kernel mono-repo with four packages (`maia/core`, `maia/orm`, `maia/auth`, `maia/cli`) that ship together as `maia/framework`. Each package is independently testable. The DI container is the foundation — everything resolves through it.

**Tech Stack:** PHP 8.2+, Composer (mono-repo with path repositories), PHPUnit 10+, firebase/php-jwt, PDO/SQLite for testing.

**Design doc:** `docs/plans/2026-02-25-maia-framework-design.md`

---

## Phase 1: Foundation — Project Structure & Config

### Task 1: Scaffold mono-repo Composer structure

**Files:**
- Create: `composer.json` (root)
- Create: `src/Core/composer.json`
- Create: `src/Orm/composer.json`
- Create: `src/Auth/composer.json`
- Create: `src/Cli/composer.json`
- Create: `.gitignore`
- Create: `phpunit.xml`

**Step 1: Initialize git repo**

```bash
cd /Users/mal/projects/personal
mkdir maia && cd maia
git init
```

**Step 2: Create root composer.json**

```json
{
    "name": "maia/framework",
    "description": "An opinionated, API-first PHP framework",
    "type": "library",
    "license": "MIT",
    "minimum-stability": "stable",
    "require": {
        "php": "^8.2",
        "maia/core": "self.version",
        "maia/orm": "self.version",
        "maia/auth": "self.version",
        "maia/cli": "self.version"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "repositories": [
        {"type": "path", "url": "src/Core"},
        {"type": "path", "url": "src/Orm"},
        {"type": "path", "url": "src/Auth"},
        {"type": "path", "url": "src/Cli"}
    ],
    "autoload": {
        "psr-4": {
            "Maia\\Core\\": "src/Core/src/",
            "Maia\\Orm\\": "src/Orm/src/",
            "Maia\\Auth\\": "src/Auth/src/",
            "Maia\\Cli\\": "src/Cli/src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Maia\\Core\\Tests\\": "src/Core/tests/",
            "Maia\\Orm\\Tests\\": "src/Orm/tests/",
            "Maia\\Auth\\Tests\\": "src/Auth/tests/",
            "Maia\\Cli\\Tests\\": "src/Cli/tests/"
        }
    }
}
```

**Step 3: Create sub-package composer.json files**

Each sub-package (`src/Core/composer.json`, etc.) needs a minimal composer.json with its name, PSR-4 autoload, and PHP requirement. For example `src/Core/composer.json`:

```json
{
    "name": "maia/core",
    "description": "Maia framework core: router, DI container, middleware, request/response",
    "type": "library",
    "require": {
        "php": "^8.2"
    },
    "autoload": {
        "psr-4": {
            "Maia\\Core\\": "src/"
        }
    }
}
```

Repeat for `maia/orm` (depends on `maia/core`), `maia/auth` (depends on `maia/core`), `maia/cli` (depends on `maia/core`, `maia/orm`, `maia/auth`).

**Step 4: Create directory structure**

```bash
mkdir -p src/Core/{src,tests}
mkdir -p src/Orm/{src,tests}
mkdir -p src/Auth/{src,tests}
mkdir -p src/Cli/{src,tests}
```

**Step 5: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Core">
            <directory>src/Core/tests</directory>
        </testsuite>
        <testsuite name="Orm">
            <directory>src/Orm/tests</directory>
        </testsuite>
        <testsuite name="Auth">
            <directory>src/Auth/tests</directory>
        </testsuite>
        <testsuite name="Cli">
            <directory>src/Cli/tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src/Core/src</directory>
            <directory>src/Orm/src</directory>
            <directory>src/Auth/src</directory>
            <directory>src/Cli/src</directory>
        </include>
    </source>
</phpunit>
```

**Step 6: Create .gitignore**

```
/vendor/
composer.lock
.phpunit.result.cache
storage/logs/
.env
```

**Step 7: Run composer install**

```bash
composer install
```

**Step 8: Verify PHPUnit runs (empty suite)**

```bash
vendor/bin/phpunit
```

Expected: 0 tests, 0 assertions, no errors.

**Step 9: Commit**

```bash
git add -A
git commit -m "chore: scaffold mono-repo structure with four packages"
```

---

### Task 2: Config loader and env parser

**Files:**
- Create: `src/Core/src/Config/Env.php`
- Create: `src/Core/src/Config/Config.php`
- Test: `src/Core/tests/Config/EnvTest.php`
- Test: `src/Core/tests/Config/ConfigTest.php`

**Step 1: Write failing test for Env parser**

```php
// src/Core/tests/Config/EnvTest.php
namespace Maia\Core\Tests\Config;

use Maia\Core\Config\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir() . '/maia_test_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }
    }

    public function test_loads_env_file(): void
    {
        file_put_contents($this->envFile, "APP_NAME=Maia\nDEBUG=true\nDB_PORT=3306\n");
        Env::load($this->envFile);

        $this->assertEquals('Maia', Env::get('APP_NAME'));
        $this->assertEquals('true', Env::get('DEBUG'));
        $this->assertEquals('3306', Env::get('DB_PORT'));
    }

    public function test_returns_default_for_missing_key(): void
    {
        Env::load($this->envFile); // empty/missing file
        $this->assertEquals('fallback', Env::get('MISSING_KEY', 'fallback'));
        $this->assertNull(Env::get('MISSING_KEY'));
    }

    public function test_ignores_comments_and_blank_lines(): void
    {
        file_put_contents($this->envFile, "# comment\n\nAPP_NAME=Maia\n  # another comment\n");
        Env::load($this->envFile);

        $this->assertEquals('Maia', Env::get('APP_NAME'));
    }

    public function test_handles_quoted_values(): void
    {
        file_put_contents($this->envFile, "APP_NAME=\"My App\"\nSECRET='s3cret'\n");
        Env::load($this->envFile);

        $this->assertEquals('My App', Env::get('APP_NAME'));
        $this->assertEquals('s3cret', Env::get('SECRET'));
    }
}
```

**Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit src/Core/tests/Config/EnvTest.php -v
```

Expected: FAIL — class not found.

**Step 3: Implement Env**

```php
// src/Core/src/Config/Env.php
namespace Maia\Core\Config;

class Env
{
    private static array $values = [];

    public static function load(string $path): void
    {
        self::$values = [];
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            // Strip surrounding quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    public static function reset(): void
    {
        self::$values = [];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit src/Core/tests/Config/EnvTest.php -v
```

Expected: All PASS.

**Step 5: Write failing test for Config**

```php
// src/Core/tests/Config/ConfigTest.php
namespace Maia\Core\Tests\Config;

use Maia\Core\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        $this->configDir = sys_get_temp_dir() . '/maia_config_' . uniqid();
        mkdir($this->configDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->configDir . '/*.php'));
        rmdir($this->configDir);
    }

    public function test_loads_config_files(): void
    {
        file_put_contents($this->configDir . '/app.php', '<?php return ["name" => "Maia", "debug" => true];');
        $config = new Config($this->configDir);

        $this->assertEquals('Maia', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
    }

    public function test_returns_default_for_missing_key(): void
    {
        $config = new Config($this->configDir);
        $this->assertEquals('default', $config->get('missing.key', 'default'));
        $this->assertNull($config->get('missing.key'));
    }

    public function test_returns_entire_file_config(): void
    {
        file_put_contents($this->configDir . '/database.php', '<?php return ["host" => "localhost", "port" => 3306];');
        $config = new Config($this->configDir);

        $result = $config->get('database');
        $this->assertEquals(['host' => 'localhost', 'port' => 3306], $result);
    }
}
```

**Step 6: Run test to verify it fails**

```bash
vendor/bin/phpunit src/Core/tests/Config/ConfigTest.php -v
```

**Step 7: Implement Config**

```php
// src/Core/src/Config/Config.php
namespace Maia\Core\Config;

class Config
{
    private array $data = [];

    public function __construct(string $configDir)
    {
        if (!is_dir($configDir)) {
            return;
        }
        foreach (glob($configDir . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->data[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
```

**Step 8: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Config/ -v
```

Expected: All PASS.

**Step 9: Commit**

```bash
git add -A
git commit -m "feat(core): add Env parser and Config loader"
```

---

## Phase 2: Core — DI Container

### Task 3: DI Container with auto-wiring

**Files:**
- Create: `src/Core/src/Container/Container.php`
- Test: `src/Core/tests/Container/ContainerTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Container/ContainerTest.php
namespace Maia\Core\Tests\Container;

use Maia\Core\Container\Container;
use PHPUnit\Framework\TestCase;

// Test fixtures (defined at bottom of file or in separate fixtures file)
class SimpleClass {
    public function value(): string { return 'simple'; }
}

class DependentClass {
    public function __construct(public SimpleClass $dep) {}
}

class DeepDependencyClass {
    public function __construct(public DependentClass $dep) {}
}

class ContainerTest extends TestCase
{
    public function test_resolves_simple_class(): void
    {
        $container = new Container();
        $instance = $container->resolve(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
        $this->assertEquals('simple', $instance->value());
    }

    public function test_auto_wires_constructor_dependencies(): void
    {
        $container = new Container();
        $instance = $container->resolve(DependentClass::class);
        $this->assertInstanceOf(DependentClass::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->dep);
    }

    public function test_resolves_deep_dependency_chain(): void
    {
        $container = new Container();
        $instance = $container->resolve(DeepDependencyClass::class);
        $this->assertInstanceOf(DeepDependencyClass::class, $instance);
        $this->assertInstanceOf(DependentClass::class, $instance->dep);
        $this->assertInstanceOf(SimpleClass::class, $instance->dep->dep);
    }

    public function test_factory_binding(): void
    {
        $container = new Container();
        $container->factory(SimpleClass::class, fn() => new SimpleClass());
        $a = $container->resolve(SimpleClass::class);
        $b = $container->resolve(SimpleClass::class);
        $this->assertNotSame($a, $b);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class);
        $a = $container->resolve(SimpleClass::class);
        $b = $container->resolve(SimpleClass::class);
        $this->assertSame($a, $b);
    }

    public function test_singleton_with_factory(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class, fn() => new SimpleClass());
        $a = $container->resolve(SimpleClass::class);
        $b = $container->resolve(SimpleClass::class);
        $this->assertSame($a, $b);
    }

    public function test_throws_on_unresolvable_parameter(): void
    {
        $container = new Container();
        $this->expectException(\RuntimeException::class);
        // A class with a scalar param that can't be auto-wired
        $container->resolve(UnresolvableClass::class);
    }
}

class UnresolvableClass {
    public function __construct(public string $name) {}
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit src/Core/tests/Container/ContainerTest.php -v
```

**Step 3: Implement Container**

```php
// src/Core/src/Container/Container.php
namespace Maia\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Container
{
    private array $factories = [];
    private array $singletons = [];
    private array $instances = [];

    public function factory(string $class, Closure $factory): void
    {
        $this->factories[$class] = $factory;
    }

    public function singleton(string $class, ?Closure $factory = null): void
    {
        $this->singletons[$class] = true;
        if ($factory !== null) {
            $this->factories[$class] = $factory;
        }
    }

    public function instance(string $class, object $instance): void
    {
        $this->instances[$class] = $instance;
    }

    public function resolve(string $class): object
    {
        // Return cached singleton
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        // Use factory if registered
        if (isset($this->factories[$class])) {
            $instance = ($this->factories[$class])($this);
        } else {
            $instance = $this->autoWire($class);
        }

        // Cache if singleton
        if (isset($this->singletons[$class])) {
            $this->instances[$class] = $instance;
        }

        return $instance;
    }

    private function autoWire(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot auto-wire [{$class}]: not instantiable.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $params = array_map(
            fn(ReflectionParameter $param) => $this->resolveParameter($param, $class),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($params);
    }

    private function resolveParameter(ReflectionParameter $param, string $forClass): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->resolve($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot resolve parameter [{$param->getName()}] in [{$forClass}]: no type hint or default value."
        );
    }
}
```

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Container/ContainerTest.php -v
```

Expected: All PASS.

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(core): add DI container with auto-wiring, factories, and singletons"
```

---

## Phase 3: Core — HTTP Layer

### Task 4: Request object

**Files:**
- Create: `src/Core/src/Http/Request.php`
- Test: `src/Core/tests/Http/RequestTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Http/RequestTest.php
namespace Maia\Core\Tests\Http;

use Maia\Core\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function test_creates_from_globals(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/users/42',
            query: ['page' => '2'],
            headers: ['Content-Type' => 'application/json'],
            body: null,
            routeParams: []
        );

        $this->assertEquals('GET', $request->method());
        $this->assertEquals('/users/42', $request->path());
        $this->assertEquals('2', $request->query('page'));
        $this->assertNull($request->query('missing'));
        $this->assertEquals('default', $request->query('missing', 'default'));
    }

    public function test_json_body_parsing(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/users',
            query: [],
            headers: ['Content-Type' => 'application/json'],
            body: '{"name": "Mal", "email": "mal@test.com"}',
            routeParams: []
        );

        $this->assertEquals(['name' => 'Mal', 'email' => 'mal@test.com'], $request->body());
    }

    public function test_route_params(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/users/42',
            query: [],
            headers: [],
            body: null,
            routeParams: ['id' => '42']
        );

        $this->assertEquals('42', $request->param('id'));
    }

    public function test_bearer_token_extraction(): void
    {
        $request = new Request(
            method: 'GET',
            path: '/',
            query: [],
            headers: ['Authorization' => 'Bearer abc123'],
            body: null,
            routeParams: []
        );

        $this->assertEquals('abc123', $request->bearerToken());
    }

    public function test_with_attribute_returns_new_instance(): void
    {
        $request = new Request('GET', '/', [], [], null, []);
        $new = $request->withAttribute('user', ['id' => 1]);

        $this->assertNotSame($request, $new);
        $this->assertNull($request->attribute('user'));
        $this->assertEquals(['id' => 1], $new->attribute('user'));
    }

    public function test_user_shortcut(): void
    {
        $request = new Request('GET', '/', [], [], null, []);
        $request = $request->withAttribute('user', (object)['name' => 'Mal']);
        $this->assertEquals('Mal', $request->user()->name);
    }

    public function test_header_case_insensitive(): void
    {
        $request = new Request('GET', '/', [], ['Content-Type' => 'application/json'], null, []);
        $this->assertEquals('application/json', $request->header('content-type'));
        $this->assertEquals('application/json', $request->header('CONTENT-TYPE'));
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit src/Core/tests/Http/RequestTest.php -v
```

**Step 3: Implement Request**

The Request class should be immutable, with:
- Constructor taking method, path, query, headers, body (raw string), routeParams
- `method()`, `path()`, `query(key, default)`, `param(key)`, `body()` (auto JSON decode), `header(key)`, `bearerToken()`
- `withAttribute(key, value)` returns new instance (clone + set)
- `attribute(key)`, `user()` (shortcut for `attribute('user')`)
- `withRouteParams(array)` for the router to inject params
- Case-insensitive header lookup (lowercase all keys internally)

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Http/RequestTest.php -v
```

**Step 5: Also create a static `Request::capture()` method**

This creates a Request from PHP superglobals (`$_SERVER`, `$_GET`, `php://input`, `getallheaders()`). Test manually or with integration test later.

**Step 6: Commit**

```bash
git add -A
git commit -m "feat(core): add immutable Request object"
```

---

### Task 5: Response object

**Files:**
- Create: `src/Core/src/Http/Response.php`
- Test: `src/Core/tests/Http/ResponseTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Http/ResponseTest.php
namespace Maia\Core\Tests\Http;

use Maia\Core\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_json_response(): void
    {
        $response = Response::json(['name' => 'Mal'], 200);
        $this->assertEquals(200, $response->status());
        $this->assertEquals('{"name":"Mal"}', $response->body());
        $this->assertEquals('application/json', $response->header('Content-Type'));
    }

    public function test_error_response(): void
    {
        $response = Response::error('Not found', 404);
        $this->assertEquals(404, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertTrue($body['error']);
        $this->assertEquals('Not found', $body['message']);
    }

    public function test_empty_response(): void
    {
        $response = Response::empty(204);
        $this->assertEquals(204, $response->status());
        $this->assertEquals('', $response->body());
    }

    public function test_custom_headers(): void
    {
        $response = Response::json(['ok' => true])
            ->withHeader('X-Custom', 'value');
        $this->assertEquals('value', $response->header('X-Custom'));
    }

    public function test_default_json_status_is_200(): void
    {
        $response = Response::json(['ok' => true]);
        $this->assertEquals(200, $response->status());
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit src/Core/tests/Http/ResponseTest.php -v
```

**Step 3: Implement Response**

The Response class has:
- Private constructor with `int $status`, `string $body`, `array $headers`
- Static factories: `json(mixed $data, int $status = 200)`, `error(string $message, int $status)`, `empty(int $status = 204)`
- `status()`, `body()`, `header(string $name)`, `headers()`
- `withHeader(string $name, string $value)` — returns new instance
- `send()` — sends headers and body to output (for real HTTP responses)

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Http/ResponseTest.php -v
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(core): add Response object with json, error, and empty factories"
```

---

### Task 6: Router with attribute scanning

**Files:**
- Create: `src/Core/src/Routing/Route.php` (attribute class)
- Create: `src/Core/src/Routing/Controller.php` (attribute class)
- Create: `src/Core/src/Routing/Router.php`
- Test: `src/Core/tests/Routing/RouterTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Routing/RouterTest.php
namespace Maia\Core\Tests\Routing;

use Maia\Core\Http\Request;
use Maia\Core\Routing\Router;
use Maia\Core\Routing\Route;
use Maia\Core\Routing\Controller;
use PHPUnit\Framework\TestCase;

#[Controller('/users')]
class TestUserController
{
    #[Route('/', method: 'GET')]
    public function list(): string { return 'list'; }

    #[Route('/{id}', method: 'GET')]
    public function show(int $id): string { return "show:{$id}"; }

    #[Route('/', method: 'POST')]
    public function create(): string { return 'create'; }
}

#[Controller('/posts')]
class TestPostController
{
    #[Route('/{slug}', method: 'GET')]
    public function show(string $slug): string { return "post:{$slug}"; }
}

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->registerController(TestUserController::class);
        $this->router->registerController(TestPostController::class);
    }

    public function test_matches_simple_route(): void
    {
        $match = $this->router->match('GET', '/users');
        $this->assertNotNull($match);
        $this->assertEquals(TestUserController::class, $match->controller);
        $this->assertEquals('list', $match->method);
    }

    public function test_matches_parameterized_route(): void
    {
        $match = $this->router->match('GET', '/users/42');
        $this->assertNotNull($match);
        $this->assertEquals('show', $match->method);
        $this->assertEquals(['id' => '42'], $match->params);
    }

    public function test_matches_correct_http_method(): void
    {
        $match = $this->router->match('POST', '/users');
        $this->assertNotNull($match);
        $this->assertEquals('create', $match->method);
    }

    public function test_returns_null_for_no_match(): void
    {
        $match = $this->router->match('GET', '/nonexistent');
        $this->assertNull($match);
    }

    public function test_returns_null_for_wrong_method(): void
    {
        $match = $this->router->match('DELETE', '/users');
        $this->assertNull($match);
    }

    public function test_matches_string_params(): void
    {
        $match = $this->router->match('GET', '/posts/hello-world');
        $this->assertNotNull($match);
        $this->assertEquals(['slug' => 'hello-world'], $match->params);
    }

    public function test_lists_all_routes(): void
    {
        $routes = $this->router->routes();
        $this->assertCount(4, $routes);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit src/Core/tests/Routing/RouterTest.php -v
```

**Step 3: Create Route and Controller attributes**

```php
// src/Core/src/Routing/Route.php
namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public array $middleware = []
    ) {}
}
```

```php
// src/Core/src/Routing/Controller.php
namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(public string $prefix = '') {}
}
```

**Step 4: Create RouteMatch value object**

```php
// src/Core/src/Routing/RouteMatch.php
namespace Maia\Core\Routing;

class RouteMatch
{
    public function __construct(
        public string $controller,
        public string $method,
        public array $params = [],
        public array $middleware = []
    ) {}
}
```

**Step 5: Implement Router**

The Router should:
- `registerController(string $class)` — use reflection to read `#[Controller]` and `#[Route]` attributes, build an internal route table
- `match(string $method, string $path): ?RouteMatch` — iterate registered routes, match by HTTP method and path pattern, extract params from `{param}` segments
- `routes(): array` — return all registered routes for inspection
- Path matching: split on `/`, compare segments, `{name}` segments are wildcards that capture values
- Normalize paths: `/users` and `/users/` both match

**Step 6: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Routing/RouterTest.php -v
```

**Step 7: Commit**

```bash
git add -A
git commit -m "feat(core): add attribute-based router with param extraction"
```

---

### Task 7: Middleware pipeline

**Files:**
- Create: `src/Core/src/Middleware/Middleware.php` (interface)
- Create: `src/Core/src/Middleware/Pipeline.php`
- Create: `src/Core/src/Routing/MiddlewareAttribute.php` (the `#[Middleware]` attribute)
- Test: `src/Core/tests/Middleware/PipelineTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Middleware/PipelineTest.php
namespace Maia\Core\Tests\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;
use Maia\Core\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;

class AddHeaderMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        return $response->withHeader('X-Added', 'true');
    }
}

class ShortCircuitMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return Response::error('Blocked', 403);
    }
}

class ModifyRequestMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request = $request->withAttribute('modified', true);
        return $next($request);
    }
}

class PipelineTest extends TestCase
{
    public function test_executes_handler_with_no_middleware(): void
    {
        $pipeline = new Pipeline([]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn(Request $req) => Response::json(['ok' => true])
        );

        $this->assertEquals(200, $response->status());
    }

    public function test_middleware_can_modify_response(): void
    {
        $pipeline = new Pipeline([new AddHeaderMiddleware()]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn(Request $req) => Response::json(['ok' => true])
        );

        $this->assertEquals('true', $response->header('X-Added'));
    }

    public function test_middleware_can_short_circuit(): void
    {
        $pipeline = new Pipeline([
            new ShortCircuitMiddleware(),
            new AddHeaderMiddleware(),
        ]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn(Request $req) => Response::json(['ok' => true])
        );

        $this->assertEquals(403, $response->status());
        $this->assertNull($response->header('X-Added'));
    }

    public function test_middleware_can_modify_request(): void
    {
        $capturedRequest = null;
        $pipeline = new Pipeline([new ModifyRequestMiddleware()]);
        $response = $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            function (Request $req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return Response::json(['ok' => true]);
            }
        );

        $this->assertTrue($capturedRequest->attribute('modified'));
    }

    public function test_middleware_executes_in_order(): void
    {
        $order = [];
        $m1 = new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, Closure $next): Response {
                $this->order[] = 'before:1';
                $response = $next($request);
                $this->order[] = 'after:1';
                return $response;
            }
        };
        $m2 = new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, Closure $next): Response {
                $this->order[] = 'before:2';
                $response = $next($request);
                $this->order[] = 'after:2';
                return $response;
            }
        };

        $pipeline = new Pipeline([$m1, $m2]);
        $pipeline->run(
            new Request('GET', '/', [], [], null, []),
            fn(Request $req) => Response::json([])
        );

        $this->assertEquals(['before:1', 'before:2', 'after:2', 'after:1'], $order);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit src/Core/tests/Middleware/PipelineTest.php -v
```

**Step 3: Implement Middleware interface and Pipeline**

```php
// src/Core/src/Middleware/Middleware.php
namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
```

```php
// src/Core/src/Middleware/Pipeline.php
namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

class Pipeline
{
    /** @param Middleware[] $middleware */
    public function __construct(private array $middleware) {}

    public function run(Request $request, Closure $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn(Closure $next, Middleware $mw) => fn(Request $req) => $mw->handle($req, $next),
            $handler
        );

        return $pipeline($request);
    }
}
```

```php
// src/Core/src/Routing/MiddlewareAttribute.php
namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class MiddlewareAttribute
{
    public array $middleware;

    public function __construct(string ...$middleware)
    {
        $this->middleware = $middleware;
    }
}
```

Note: Name the class `MiddlewareAttribute` to avoid collision with the `Middleware` interface. The attribute usage is `#[MiddlewareAttribute(AuthMiddleware::class)]` or consider aliasing. Alternatively, name the attribute `Middleware` in a `Routing` namespace since it won't collide with the interface in the `Middleware` namespace. Decide based on import clarity — keeping it as `#[Middleware(...)]` is cleaner for users. Since PHP handles namespace imports, the interface `Maia\Core\Middleware\Middleware` and attribute `Maia\Core\Routing\Middleware` won't collide if imported carefully. Rename this file to `src/Core/src/Routing/Middleware.php` with class name `Middleware`.

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Middleware/PipelineTest.php -v
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(core): add middleware interface and pipeline"
```

---

### Task 8: Error handling — exceptions and handler

**Files:**
- Create: `src/Core/src/Exceptions/HttpException.php`
- Create: `src/Core/src/Exceptions/NotFoundException.php`
- Create: `src/Core/src/Exceptions/ValidationException.php`
- Create: `src/Core/src/Exceptions/UnauthorizedException.php`
- Create: `src/Core/src/Exceptions/ForbiddenException.php`
- Create: `src/Core/src/Exceptions/ConflictException.php`
- Create: `src/Core/src/Exceptions/ExceptionHandler.php`
- Test: `src/Core/tests/Exceptions/ExceptionHandlerTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Exceptions/ExceptionHandlerTest.php
namespace Maia\Core\Tests\Exceptions;

use Maia\Core\Exceptions\ExceptionHandler;
use Maia\Core\Exceptions\NotFoundException;
use Maia\Core\Exceptions\ValidationException;
use Maia\Core\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

class ExceptionHandlerTest extends TestCase
{
    public function test_not_found_returns_404(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $response = $handler->handle(new NotFoundException('User not found'));
        $this->assertEquals(404, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertTrue($body['error']);
        $this->assertEquals('User not found', $body['message']);
    }

    public function test_validation_returns_422_with_errors(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $errors = ['email' => ['The email field is required.']];
        $response = $handler->handle(new ValidationException($errors));
        $this->assertEquals(422, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals($errors, $body['errors']);
    }

    public function test_unauthorized_returns_401(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $response = $handler->handle(new UnauthorizedException('Invalid token'));
        $this->assertEquals(401, $response->status());
    }

    public function test_generic_exception_returns_500_in_production(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $response = $handler->handle(new \RuntimeException('DB exploded'));
        $this->assertEquals(500, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals('Internal server error', $body['message']);
        $this->assertArrayNotHasKey('trace', $body);
    }

    public function test_generic_exception_returns_details_in_debug(): void
    {
        $handler = new ExceptionHandler(debug: true);
        $response = $handler->handle(new \RuntimeException('DB exploded'));
        $this->assertEquals(500, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertEquals('DB exploded', $body['message']);
        $this->assertArrayHasKey('exception', $body);
        $this->assertArrayHasKey('file', $body);
        $this->assertArrayHasKey('trace', $body);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit src/Core/tests/Exceptions/ExceptionHandlerTest.php -v
```

**Step 3: Implement exception classes**

- `HttpException` — base class with `$status` and `$message`
- `NotFoundException(string $message = 'Not found')` — status 404
- `UnauthorizedException` — status 401
- `ForbiddenException` — status 403
- `ConflictException` — status 409
- `ValidationException(array $errors)` — status 422, carries structured errors
- `ExceptionHandler(bool $debug)` — `handle(\Throwable $e): Response`

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Exceptions/ExceptionHandlerTest.php -v
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(core): add HTTP exceptions and centralized exception handler"
```

---

### Task 9: Logger

**Files:**
- Create: `src/Core/src/Logging/Logger.php`
- Test: `src/Core/tests/Logging/LoggerTest.php`

**Step 1: Write failing tests**

```php
// src/Core/tests/Logging/LoggerTest.php
namespace Maia\Core\Tests\Logging;

use Maia\Core\Logging\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/maia_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function test_writes_json_log_entry(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('User created', ['id' => 42]);

        $line = trim(file_get_contents($this->logFile));
        $entry = json_decode($line, true);

        $this->assertEquals('info', $entry['level']);
        $this->assertEquals('User created', $entry['message']);
        $this->assertEquals(42, $entry['context']['id']);
        $this->assertArrayHasKey('timestamp', $entry);
    }

    public function test_respects_log_level(): void
    {
        $logger = new Logger($this->logFile, 'warning');
        $logger->info('This should be ignored');
        $logger->debug('This too');
        $logger->warning('This should appear');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('This should appear', $content);
        $this->assertStringNotContainsString('This should be ignored', $content);
    }

    public function test_all_log_levels(): void
    {
        $logger = new Logger($this->logFile, 'debug');
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');

        $lines = array_filter(explode("\n", trim(file_get_contents($this->logFile))));
        $this->assertCount(4, $lines);
    }

    public function test_null_channel_discards(): void
    {
        $logger = Logger::null();
        $logger->info('This goes nowhere');
        // No assertion needed — just verify no error
        $this->assertTrue(true);
    }

    public function test_stderr_channel(): void
    {
        $logger = Logger::stderr('info');
        // Just verify it can be created without error
        $this->assertInstanceOf(Logger::class, $logger);
    }
}
```

**Step 2: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Logging/LoggerTest.php -v
```

**Step 3: Implement Logger**

Logger with:
- Constructor: `__construct(string $path, string $level = 'info')`
- Methods: `debug()`, `info()`, `warning()`, `error()` — each takes `string $message, array $context = []`
- Each writes a JSON line: `{"timestamp": "...", "level": "...", "message": "...", "context": {...}}`
- Level hierarchy: debug < info < warning < error — only log if message level >= configured level
- Static factories: `Logger::null()` (discards), `Logger::stderr(string $level)` (writes to php://stderr)

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/Logging/LoggerTest.php -v
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(core): add JSON-structured file logger"
```

---

### Task 10: App bootstrap — ties core together

**Files:**
- Create: `src/Core/src/App.php`
- Test: `src/Core/tests/AppTest.php`

**Step 1: Write failing tests**

Test that the App:
- Boots from a config directory and env file
- Registers routes from controller classes
- Dispatches a request through middleware pipeline → router → controller → response
- Returns 404 for unmatched routes
- Handles exceptions via ExceptionHandler

```php
// src/Core/tests/AppTest.php
namespace Maia\Core\Tests;

use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Routing\Route;
use Maia\Core\Routing\Controller;
use PHPUnit\Framework\TestCase;

#[Controller('/test')]
class TestController
{
    #[Route('/', method: 'GET')]
    public function index(): Response
    {
        return Response::json(['message' => 'hello']);
    }

    #[Route('/error', method: 'GET')]
    public function error(): Response
    {
        throw new \Maia\Core\Exceptions\NotFoundException('Not here');
    }
}

class AppTest extends TestCase
{
    public function test_handles_matched_request(): void
    {
        $app = App::create();
        $app->registerController(TestController::class);

        $request = new Request('GET', '/test', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('hello', $response->body());
    }

    public function test_returns_404_for_unmatched_route(): void
    {
        $app = App::create();
        $request = new Request('GET', '/nothing', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function test_handles_exceptions_gracefully(): void
    {
        $app = App::create();
        $app->registerController(TestController::class);

        $request = new Request('GET', '/test/error', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
    }
}
```

**Step 2: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/AppTest.php -v
```

**Step 3: Implement App**

The App class is the kernel:
- `App::create(?string $configDir = null, ?string $envFile = null)` — static factory
- Internally creates Container, Router, Config, Logger, ExceptionHandler
- `registerController(string $class)` — registers with router
- `handle(Request $request): Response` — matches route, resolves controller from container, invokes method with injected params (Request, route params, auto-wired services), wraps in middleware pipeline, catches exceptions
- The controller method invocation should use reflection to inject the right params: if a param is typed `Request`, inject the request; if it matches a route param name, inject that; otherwise auto-wire from container

**Step 4: Run tests**

```bash
vendor/bin/phpunit src/Core/tests/AppTest.php -v
```

**Step 5: Run full Core test suite**

```bash
vendor/bin/phpunit --testsuite Core -v
```

**Step 6: Commit**

```bash
git add -A
git commit -m "feat(core): add App bootstrap that ties router, middleware, DI, and error handling together"
```

---

## Phase 4: ORM

### Task 11: Database connection wrapper

**Files:**
- Create: `src/Orm/src/Connection.php`
- Test: `src/Orm/tests/ConnectionTest.php`

**Step 1: Write failing tests**

Test with SQLite in-memory:
- Creates a connection
- Executes raw queries with prepared statements
- Fetches results as associative arrays
- Handles parameterized queries

**Step 2: Implement Connection**

Thin PDO wrapper:
- `__construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])`
- `query(string $sql, array $params = []): array` — SELECT, returns rows
- `execute(string $sql, array $params = []): int` — INSERT/UPDATE/DELETE, returns affected rows
- `lastInsertId(): string`
- Sets PDO to throw exceptions and fetch assoc by default

**Step 3: Run tests, commit**

```bash
git commit -m "feat(orm): add PDO connection wrapper"
```

---

### Task 12: Query builder

**Files:**
- Create: `src/Orm/src/QueryBuilder.php`
- Test: `src/Orm/tests/QueryBuilderTest.php`

**Step 1: Write failing tests**

Test query building (SQL generation) and execution against SQLite:
- `select()`, `where()`, `orderBy()`, `limit()`, `offset()`
- `get()`, `first()`, `count()`
- `insert()`, `update()`, `delete()`
- Chaining: `QueryBuilder::table('users')->where('active', true)->orderBy('name')->limit(10)->get()`
- All queries use prepared statements with parameter binding

**Step 2: Implement QueryBuilder**

Fluent builder that accumulates query parts and compiles to SQL:
- `static table(string $table, Connection $connection): self`
- `select(string ...$columns): self`
- `where(string $column, mixed $value, string $operator = '='): self`
- `orderBy(string $column, string $direction = 'asc'): self`
- `limit(int $limit): self`, `offset(int $offset): self`
- `get(): array`, `first(): ?array`, `count(): int`
- `insert(array $data): int` (returns last insert ID)
- `update(array $data): int` (returns affected rows)
- `delete(): int`

**Step 3: Run tests, commit**

```bash
git commit -m "feat(orm): add fluent query builder"
```

---

### Task 13: Model base class

**Files:**
- Create: `src/Orm/src/Model.php`
- Create: `src/Orm/src/Attributes/Table.php`
- Test: `src/Orm/tests/ModelTest.php`

**Step 1: Write failing tests**

Using an in-memory SQLite database, create a test model and verify:
- `User::find(1)` returns a User instance with properties populated
- `User::query()` returns a QueryBuilder scoped to the table
- `User::create([...])` inserts and returns a populated model
- `$user->save()` updates the record
- `User::query()->where(...)->get()` returns array of model instances

**Step 2: Implement Model**

- `#[Table('name')]` attribute defines table name
- `Model` reads the `#[Table]` attribute via reflection
- Static methods `find()`, `query()`, `create()` delegate to QueryBuilder
- `save()` does UPDATE by primary key (default `id`)
- Hydration: maps DB row (associative array) to public properties using reflection
- Needs a static `setConnection(Connection $connection)` or resolve from container

**Step 3: Run tests, commit**

```bash
git commit -m "feat(orm): add Model base class with find, create, query, save"
```

---

### Task 14: Relationships

**Files:**
- Create: `src/Orm/src/Attributes/HasMany.php`
- Create: `src/Orm/src/Attributes/BelongsTo.php`
- Modify: `src/Orm/src/Model.php` — add relationship loading
- Test: `src/Orm/tests/RelationshipTest.php`

**Step 1: Write failing tests**

Create User and Post models with `#[HasMany]` and `#[BelongsTo]` attributes. Test:
- Lazy loading: `$post->user` resolves the related User
- Lazy loading: `$user->posts` resolves an array of Posts
- Eager loading: `User::query()->with('posts')->find(1)` loads posts in one extra query
- Convention: `BelongsTo(User::class)` infers `user_id` foreign key
- Override: `BelongsTo(User::class, foreignKey: 'author_id')`

**Step 2: Implement relationship attributes and loading**

- `#[HasMany(relatedClass, foreignKey?)]` and `#[BelongsTo(relatedClass, foreignKey?)]` attribute classes
- Model uses `__get()` magic method or property initialization hooks for lazy loading
- `with(string ...$relations)` on QueryBuilder stores relation names, Model hydration batch-loads them

**Step 3: Run tests, commit**

```bash
git commit -m "feat(orm): add attribute-based relationships with lazy and eager loading"
```

---

### Task 15: Schema builder and migrations

**Files:**
- Create: `src/Orm/src/Schema/Schema.php`
- Create: `src/Orm/src/Schema/Table.php`
- Create: `src/Orm/src/Migration.php`
- Create: `src/Orm/src/Migrator.php`
- Test: `src/Orm/tests/Schema/SchemaTest.php`
- Test: `src/Orm/tests/MigratorTest.php`

**Step 1: Write failing tests for Schema**

Test that Schema generates correct SQL for creating tables:
- `$schema->create('users', fn(Table $t) => ...)` generates CREATE TABLE
- `Table` methods: `id()`, `string(name, length?)`, `integer(name)`, `boolean(name)`, `text(name)`, `timestamps()`, `unique()`, `default(value)`, `nullable()`

**Step 2: Implement Schema and Table**

Schema generates DDL SQL and executes it against a Connection. Table is a fluent column definition builder.

**Step 3: Write failing tests for Migrator**

Test that Migrator:
- Reads migration files from a directory
- Tracks which have been run (in a `migrations` table)
- `migrate()` runs pending migrations in order
- `rollback()` rolls back the last batch

**Step 4: Implement Migrator**

- Creates a `migrations` table if it doesn't exist
- Scans migration directory, sorts by filename timestamp
- Runs `up()` for pending, `down()` for rollback
- Tracks batch numbers

**Step 5: Run tests, commit**

```bash
git commit -m "feat(orm): add schema builder and migration system"
```

---

## Phase 5: Auth & Security

### Task 16: JWT middleware

**Files:**
- Create: `src/Auth/src/JwtMiddleware.php`
- Create: `src/Auth/src/JwtService.php`
- Create: `src/Auth/src/Auth.php` (static factory for middleware)
- Test: `src/Auth/tests/JwtMiddlewareTest.php`

**Step 1: Add firebase/php-jwt dependency**

```bash
composer require firebase/php-jwt
```

**Step 2: Write failing tests**

Test that:
- Request with valid JWT passes through, `$request->user()` is populated
- Request with expired JWT returns 401
- Request with no Authorization header returns 401
- Request with malformed token returns 401

**Step 3: Implement JwtService and JwtMiddleware**

- `JwtService` wraps firebase/php-jwt: `encode(array $payload): string`, `decode(string $token): object`
- `JwtMiddleware` implements `Middleware`, extracts bearer token, verifies, sets user attribute
- `Auth::jwt()` returns a configured `JwtMiddleware` class name (or factory)

**Step 4: Run tests, commit**

```bash
git commit -m "feat(auth): add JWT authentication middleware"
```

---

### Task 17: API key middleware

**Files:**
- Create: `src/Auth/src/ApiKeyMiddleware.php`
- Test: `src/Auth/tests/ApiKeyMiddlewareTest.php`

**Step 1: Write failing tests**

Test that:
- Request with valid API key in X-API-Key header passes through
- Request with invalid/missing key returns 401
- Key validation uses constant-time comparison (hash_equals)

**Step 2: Implement**

**Step 3: Run tests, commit**

```bash
git commit -m "feat(auth): add API key authentication middleware"
```

---

### Task 18: Security middleware (CORS, rate limiting, security headers)

**Files:**
- Create: `src/Auth/src/CorsMiddleware.php`
- Create: `src/Auth/src/RateLimit.php`
- Create: `src/Auth/src/SecurityHeadersMiddleware.php`
- Test: `src/Auth/tests/CorsMiddlewareTest.php`
- Test: `src/Auth/tests/RateLimitTest.php`
- Test: `src/Auth/tests/SecurityHeadersMiddlewareTest.php`

**Step 1: Write failing tests for each**

CORS: test that it adds correct headers, rejects disallowed origins, handles preflight OPTIONS.
Rate limiting: test `RateLimit::perMinute(5)` returns middleware, tracks requests (in-memory for now), returns 429 when exceeded.
Security headers: test that X-Content-Type-Options, X-Frame-Options, Strict-Transport-Security are set.

**Step 2: Implement each middleware**

**Step 3: Run tests, commit**

```bash
git commit -m "feat(auth): add CORS, rate limiting, and security headers middleware"
```

---

### Task 19: Request validation (FormRequest)

**Files:**
- Create: `src/Auth/src/FormRequest.php`
- Create: `src/Auth/src/Validator.php`
- Test: `src/Auth/tests/ValidatorTest.php`
- Test: `src/Auth/tests/FormRequestTest.php`

**Step 1: Write failing tests**

Test Validator rules: `required`, `string`, `email`, `min:N`, `max:N`, `integer`, `boolean`, `unique:table` (mocked).
Test FormRequest: resolves rules, validates incoming request body, throws ValidationException with structured errors.

**Step 2: Implement Validator and FormRequest**

- `Validator::validate(array $data, array $rules): array` — returns errors array (empty if valid)
- `FormRequest` extends `Request`, has abstract `rules()`, auto-validates on construction, `validated()` returns clean data

**Step 3: Run tests, commit**

```bash
git commit -m "feat(auth): add request validation with FormRequest and Validator"
```

---

## Phase 6: CLI

### Task 20: CLI command framework

**Files:**
- Create: `src/Cli/src/Command.php` (abstract base)
- Create: `src/Cli/src/CommandRunner.php`
- Create: `src/Cli/src/Output.php` (handles --json flag)
- Test: `src/Cli/tests/CommandRunnerTest.php`

**Step 1: Write failing tests**

Test that:
- CommandRunner parses `maia create:controller UserController` into command name + args
- Commands receive parsed arguments
- `--json` flag switches output to JSON format
- `--help` shows command help text
- Unknown commands return error

**Step 2: Implement**

- `Command` abstract: `name()`, `description()`, `execute(array $args, Output $output): int`
- `CommandRunner`: registers commands, parses argv, dispatches
- `Output`: `line(string)`, `json(array)`, `error(string)` — respects `--json` flag

**Step 3: Run tests, commit**

```bash
git commit -m "feat(cli): add command framework with argument parsing and JSON output"
```

---

### Task 21: `maia new` command

**Files:**
- Create: `src/Cli/src/Commands/NewCommand.php`
- Create: `src/Cli/src/Templates/` (template files for scaffolded project)
- Test: `src/Cli/tests/Commands/NewCommandTest.php`

**Step 1: Write failing tests**

Test that `maia new my-app`:
- Creates the full directory structure from the design doc
- Creates composer.json with maia/framework dependency
- Creates config files with sensible defaults
- Creates public/index.php entry point
- Creates .env.example
- Creates CLAUDE.md and AGENTS.md
- Creates maia.json

**Step 2: Implement**

The command creates directories and writes template files. Templates are stored as PHP files that return strings (or use heredoc). The command should also run `composer install` in the new directory if composer is available.

**Step 3: Run tests, commit**

```bash
git commit -m "feat(cli): add 'maia new' command for project scaffolding"
```

---

### Task 22: `maia up` command

**Files:**
- Create: `src/Cli/src/Commands/UpCommand.php`
- Test: `src/Cli/tests/Commands/UpCommandTest.php`

**Step 1: Write failing test**

Test that the command:
- Defaults to port 8000
- Respects `--port` flag
- Points PHP built-in server at `public/index.php`

**Step 2: Implement**

Uses PHP's built-in development server: `php -S localhost:{port} -t public/`.

**Step 3: Run tests, commit**

```bash
git commit -m "feat(cli): add 'maia up' command for dev server"
```

---

### Task 23: `maia create:*` scaffolding commands

**Files:**
- Create: `src/Cli/src/Commands/CreateControllerCommand.php`
- Create: `src/Cli/src/Commands/CreateServiceCommand.php`
- Create: `src/Cli/src/Commands/CreateModelCommand.php`
- Create: `src/Cli/src/Commands/CreateMiddlewareCommand.php`
- Create: `src/Cli/src/Commands/CreateRequestCommand.php`
- Create: `src/Cli/src/Commands/CreateMigrationCommand.php`
- Create: `src/Cli/src/Commands/CreateTestCommand.php`
- Test: `src/Cli/tests/Commands/CreateCommandsTest.php`

**Step 1: Write failing tests**

For each command, test that it:
- Creates the file in the correct directory
- Uses the correct namespace
- Extends/implements the right base class
- Includes sensible boilerplate (e.g., controller has a Route attribute, model has a Table attribute)

**Step 2: Implement all create commands**

Each reads a template with placeholder replacement (class name, namespace, table name inferred from model name, etc.).

**Step 3: Run tests, commit**

```bash
git commit -m "feat(cli): add scaffolding commands (create:controller, model, service, etc.)"
```

---

### Task 24: `maia migrate` commands

**Files:**
- Create: `src/Cli/src/Commands/MigrateCommand.php`
- Create: `src/Cli/src/Commands/MigrateRollbackCommand.php`
- Create: `src/Cli/src/Commands/MigrateStatusCommand.php`
- Test: `src/Cli/tests/Commands/MigrateCommandsTest.php`

**Step 1: Write failing tests**

Test against SQLite in-memory:
- `maia migrate` runs pending migrations, outputs results
- `maia migrate:rollback` rolls back last batch
- `maia migrate:status` shows which migrations have/haven't run
- All support `--json` output

**Step 2: Implement — delegate to Migrator from maia/orm**

**Step 3: Run tests, commit**

```bash
git commit -m "feat(cli): add migrate, migrate:rollback, migrate:status commands"
```

---

### Task 25: `maia routes` and `maia describe` commands

**Files:**
- Create: `src/Cli/src/Commands/RoutesCommand.php`
- Create: `src/Cli/src/Commands/DescribeCommand.php`
- Test: `src/Cli/tests/Commands/InspectionCommandsTest.php`

**Step 1: Write failing tests**

Test that:
- `maia routes` lists all registered routes (method, path, controller, middleware)
- `maia routes --json` outputs JSON array
- `maia describe` outputs project structure: routes, models, middleware stack, config
- `maia describe --json` outputs structured JSON manifest

**Step 2: Implement**

Routes command: boot the app, get router, list routes.
Describe command: scan project directories, read maia.json, compose a full manifest.

**Step 3: Run tests, commit**

```bash
git commit -m "feat(cli): add routes and describe inspection commands"
```

---

## Phase 7: Testing Support

### Task 26: TestCase base class with HTTP helpers

**Files:**
- Create: `src/Core/src/Testing/TestCase.php`
- Create: `src/Core/src/Testing/TestResponse.php`
- Test: `src/Core/tests/Testing/TestCaseTest.php`

**Step 1: Write failing tests**

Test that:
- `$this->get('/path')` returns a TestResponse
- `$this->post('/path', $data)` sends JSON body
- `$this->withToken($jwt)` sets Authorization header
- `TestResponse::assertStatus(200)` asserts status code
- `TestResponse::assertJsonStructure([...])` validates JSON shape
- `$this->resolve(SomeClass::class)` resolves from test container
- `$this->assertDatabaseHas('table', ['col' => 'val'])` checks DB

**Step 2: Implement**

- `TestCase` extends PHPUnit TestCase, boots a Maia App with in-memory SQLite, provides HTTP helper methods
- `TestResponse` wraps Response with assertion methods
- `generateJwt(array $payload)` creates a test JWT

**Step 3: Run tests, commit**

```bash
git commit -m "feat(core): add TestCase base class with HTTP helpers and DB assertions"
```

---

## Phase 8: Integration & Polish

### Task 27: Wire `maia` binary entry point

**Files:**
- Create: `bin/maia` (executable PHP script)
- Modify: root `composer.json` — add `bin` field

**Step 1: Create `bin/maia`**

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Maia\Cli\CommandRunner;
// Register all commands...

$runner = new CommandRunner();
// ... register commands
exit($runner->run($argv));
```

**Step 2: Add to composer.json**

```json
"bin": ["bin/maia"]
```

**Step 3: Test manually**

```bash
php bin/maia --help
php bin/maia routes --json
```

**Step 4: Commit**

```bash
git commit -m "feat: add maia CLI binary entry point"
```

---

### Task 28: End-to-end integration test

**Files:**
- Create: `tests/Integration/FullStackTest.php`

**Step 1: Write integration test**

Create a complete mini-app in the test:
- Register a controller with routes, middleware, validation
- Create DB tables via migration
- Test full request lifecycle: auth → validation → service → DB → response
- Test error paths: 401, 404, 422

**Step 2: Run full test suite**

```bash
vendor/bin/phpunit -v
```

**Step 3: Commit**

```bash
git commit -m "test: add end-to-end integration test for full request lifecycle"
```

---

### Task 29: Create CLAUDE.md and AGENTS.md templates

**Files:**
- Create: `src/Cli/src/Templates/CLAUDE.md.php`
- Create: `src/Cli/src/Templates/AGENTS.md.php`

**Step 1: Write templates**

CLAUDE.md template should describe:
- Project structure and conventions
- How to run the app (`maia up`)
- How to create new components (`maia create:*`)
- How to run tests (`maia test`)
- Where config lives

AGENTS.md template should describe:
- Available CLI commands and their JSON output formats
- Convention for file locations
- How to discover routes and project state (`maia describe --json`)

**Step 2: Integrate into `maia new` command**

Ensure the templates are rendered and written during project scaffolding.

**Step 3: Commit**

```bash
git commit -m "feat(cli): add CLAUDE.md and AGENTS.md templates for agent discoverability"
```

---

## Summary

| Phase | Tasks | Focus |
|-------|-------|-------|
| 1 | 1-2 | Project structure, config, env |
| 2 | 3 | DI container |
| 3 | 4-10 | HTTP: request, response, router, middleware, errors, logging, app bootstrap |
| 4 | 11-15 | ORM: connection, query builder, model, relationships, migrations |
| 5 | 16-19 | Auth: JWT, API keys, CORS, rate limiting, validation |
| 6 | 20-25 | CLI: framework, new, up, create:*, migrate, routes/describe |
| 7 | 26 | Testing support |
| 8 | 27-29 | Integration: binary, e2e test, agent templates |

Total: 29 tasks across 8 phases. Each task follows TDD: write failing test → implement → verify → commit.
