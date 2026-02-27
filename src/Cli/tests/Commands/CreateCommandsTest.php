<?php

declare(strict_types=1);

namespace Maia\Cli\Tests\Commands;

use Maia\Cli\Commands\CreateControllerCommand;
use Maia\Cli\Commands\CreateMiddlewareCommand;
use Maia\Cli\Commands\CreateMigrationCommand;
use Maia\Cli\Commands\CreateModelCommand;
use Maia\Cli\Commands\CreateRequestCommand;
use Maia\Cli\Commands\CreateServiceCommand;
use Maia\Cli\Commands\CreateTestCommand;
use Maia\Cli\Output;
use PHPUnit\Framework\TestCase;

class CreateCommandsTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/maia_create_' . uniqid('', true);
        mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);
    }

    public function testCreateControllerCommand(): void
    {
        $command = new CreateControllerCommand($this->workspace);
        $command->execute(['UserController'], new Output());

        $file = $this->workspace . '/app/Controllers/UserController.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('namespace App\\Controllers;', $contents);
        $this->assertStringContainsString('#[Controller', $contents);
        $this->assertStringContainsString('#[Route', $contents);
    }

    public function testCreateServiceCommand(): void
    {
        $command = new CreateServiceCommand($this->workspace);
        $command->execute(['BillingService'], new Output());

        $file = $this->workspace . '/app/Services/BillingService.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('namespace App\\Services;', $contents);
        $this->assertStringContainsString('class BillingService', $contents);
    }

    public function testCreateModelCommand(): void
    {
        $command = new CreateModelCommand($this->workspace);
        $command->execute(['Invoice'], new Output());

        $file = $this->workspace . '/app/Models/Invoice.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('namespace App\\Models;', $contents);
        $this->assertStringContainsString('extends Model', $contents);
        $this->assertStringContainsString('#[Table(', $contents);
    }

    public function testCreateMiddlewareCommand(): void
    {
        $command = new CreateMiddlewareCommand($this->workspace);
        $command->execute(['AuditMiddleware'], new Output());

        $file = $this->workspace . '/app/Middleware/AuditMiddleware.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('namespace App\\Middleware;', $contents);
        $this->assertStringContainsString('implements Middleware', $contents);
    }

    public function testCreateRequestCommand(): void
    {
        $command = new CreateRequestCommand($this->workspace);
        $command->execute(['StoreUserRequest'], new Output());

        $file = $this->workspace . '/app/Requests/StoreUserRequest.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('namespace App\\Requests;', $contents);
        $this->assertStringContainsString('extends FormRequest', $contents);
    }

    public function testCreateMigrationCommand(): void
    {
        $command = new CreateMigrationCommand($this->workspace, fn (): string => '2026_02_25_120000');
        $command->execute(['create_users_table'], new Output());

        $file = $this->workspace . '/database/migrations/2026_02_25_120000_create_users_table.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('extends Migration', $contents);
    }

    public function testCreateTestCommand(): void
    {
        $command = new CreateTestCommand($this->workspace);
        $command->execute(['UserServiceTest'], new Output());

        $file = $this->workspace . '/tests/UserServiceTest.php';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('namespace Tests;', $contents);
        $this->assertStringContainsString('extends TestCase', $contents);
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
