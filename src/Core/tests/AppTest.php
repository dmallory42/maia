<?php

declare(strict_types=1);

namespace Maia\Core\Tests;

use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;
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

class ConfiguredFactoryService
{
    public function __construct(public string $source)
    {
    }
}

class ConfiguredSingletonService
{
}

class AppTest extends TestCase
{
    private ?string $configDir = null;

    protected function tearDown(): void
    {
        if ($this->configDir !== null && is_dir($this->configDir)) {
            $files = glob($this->configDir . '/*.php');
            if (is_array($files)) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }

            rmdir($this->configDir);
        }

        parent::tearDown();
    }

    public function testHandlesMatchedRequest(): void
    {
        $app = App::create();
        $app->registerController(TestController::class);

        $request = new Request('GET', '/test', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('hello', $response->body());
    }

    public function testReturns404ForUnmatchedRoute(): void
    {
        $app = App::create();
        $request = new Request('GET', '/nothing', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testHandlesExceptionsGracefully(): void
    {
        $app = App::create();
        $app->registerController(TestController::class);

        $request = new Request('GET', '/test/error', [], [], null, []);
        $response = $app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testAppliesConfiguredContainerBindings(): void
    {
        $this->configDir = sys_get_temp_dir() . '/maia_app_config_' . uniqid('', true);
        mkdir($this->configDir);

        file_put_contents(
            $this->configDir . '/app.php',
            <<<'PHP'
<?php

return [
    'factories' => [
        \Maia\Core\Tests\ConfiguredFactoryService::class => static fn (
            \Maia\Core\Container\Container $container
        ) => new \Maia\Core\Tests\ConfiguredFactoryService('config'),
    ],
    'singletons' => [
        \Maia\Core\Tests\ConfiguredSingletonService::class,
    ],
];
PHP
        );

        $app = App::create($this->configDir);

        $factoryA = $app->container()->resolve(ConfiguredFactoryService::class);
        $factoryB = $app->container()->resolve(ConfiguredFactoryService::class);
        $singletonA = $app->container()->resolve(ConfiguredSingletonService::class);
        $singletonB = $app->container()->resolve(ConfiguredSingletonService::class);

        $this->assertSame('config', $factoryA->source);
        $this->assertNotSame($factoryA, $factoryB);
        $this->assertSame($singletonA, $singletonB);
    }
}
