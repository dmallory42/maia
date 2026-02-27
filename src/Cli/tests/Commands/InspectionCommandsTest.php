<?php

declare(strict_types=1);

namespace Maia\Cli\Tests\Commands;

use Maia\Cli\Commands\DescribeCommand;
use Maia\Cli\Commands\RoutesCommand;
use Maia\Cli\Output;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;
use PHPUnit\Framework\TestCase;

#[Controller('/users')]
class InspectUserController
{
    #[Route('/', method: 'GET')]
    public function index(): string
    {
        return 'ok';
    }
}

class InspectionCommandsTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/maia_describe_' . uniqid('', true);
        mkdir($this->workspace);

        mkdir($this->workspace . '/app/Models', 0777, true);
        mkdir($this->workspace . '/app/Middleware', 0777, true);
        mkdir($this->workspace . '/config', 0777, true);

        file_put_contents($this->workspace . '/app/Models/User.php', '<?php class User {}');
        file_put_contents($this->workspace . '/app/Middleware/AuthMiddleware.php', '<?php class AuthMiddleware {}');
        file_put_contents($this->workspace . '/config/app.php', '<?php return [];');
        file_put_contents($this->workspace . '/maia.json', '{"app":{"controllers":"app/Controllers"}}');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);
    }

    public function testRoutesCommandListsRegisteredRoutes(): void
    {
        $command = new RoutesCommand($this->workspace, [InspectUserController::class]);
        $output = new Output();

        $code = $command->execute([], $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('GET', $output->buffer());
        $this->assertStringContainsString('/users', $output->buffer());
    }

    public function testRoutesCommandSupportsJsonOutput(): void
    {
        $command = new RoutesCommand($this->workspace, [InspectUserController::class]);
        $output = new Output(true);

        $command->execute([], $output);

        $payload = json_decode(trim($output->buffer()), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('routes', $payload);
        $this->assertCount(1, $payload['routes']);
    }

    public function testDescribeCommandReturnsProjectManifest(): void
    {
        $command = new DescribeCommand($this->workspace, [InspectUserController::class]);
        $output = new Output(true);

        $code = $command->execute([], $output);

        $this->assertSame(0, $code);

        $payload = json_decode(trim($output->buffer()), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('routes', $payload);
        $this->assertArrayHasKey('models', $payload);
        $this->assertArrayHasKey('middleware', $payload);
        $this->assertArrayHasKey('config', $payload);
        $this->assertArrayHasKey('maia', $payload);
        $this->assertContains('User.php', $payload['models']);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            if (is_dir($target)) {
                $this->deleteDirectory($target);
            } else {
                unlink($target);
            }
        }

        rmdir($path);
    }
}
