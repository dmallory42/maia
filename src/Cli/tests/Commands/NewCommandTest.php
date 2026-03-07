<?php

declare(strict_types=1);

namespace Maia\Cli\Tests\Commands;

use Maia\Cli\Commands\NewCommand;
use Maia\Cli\Output;
use PHPUnit\Framework\TestCase;

class NewCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/maia_new_' . uniqid('', true);
        mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);
    }

    public function testScaffoldsNewProjectStructure(): void
    {
        $command = new NewCommand($this->workspace, autoInstall: false);
        $output = new Output();

        $code = $command->execute(['my-app', '--no-install'], $output);

        $this->assertSame(0, $code);

        $base = $this->workspace . '/my-app';

        $this->assertDirectoryExists($base . '/app/Controllers');
        $this->assertDirectoryExists($base . '/app/Services');
        $this->assertDirectoryExists($base . '/app/Models');
        $this->assertDirectoryExists($base . '/app/Middleware');
        $this->assertDirectoryExists($base . '/app/Requests');
        $this->assertDirectoryExists($base . '/config');
        $this->assertDirectoryExists($base . '/routes');
        $this->assertDirectoryExists($base . '/database/migrations');
        $this->assertDirectoryExists($base . '/storage/logs');
        $this->assertDirectoryExists($base . '/public');

        $composer = file_get_contents($base . '/composer.json');
        $this->assertIsString($composer);
        $this->assertStringContainsString('"maia/framework"', $composer);

        $this->assertFileExists($base . '/config/app.php');
        $this->assertFileExists($base . '/config/database.php');
        $this->assertFileExists($base . '/config/auth.php');
        $this->assertFileExists($base . '/config/cors.php');
        $this->assertFileExists($base . '/config/logging.php');
        $this->assertFileExists($base . '/config/middleware.php');

        $this->assertFileExists($base . '/public/index.php');
        $this->assertFileExists($base . '/.env.example');
        $this->assertFileExists($base . '/CLAUDE.md');
        $this->assertFileExists($base . '/AGENTS.md');
        $this->assertFileExists($base . '/maia.json');

        $publicIndex = file_get_contents($base . '/public/index.php');
        $routes = file_get_contents($base . '/routes/api.php');
        $appConfig = file_get_contents($base . '/config/app.php');

        $this->assertIsString($publicIndex);
        $this->assertIsString($routes);
        $this->assertIsString($appConfig);
        $this->assertStringContainsString("require __DIR__ . '/../routes/api.php';", $publicIndex);
        $this->assertStringContainsString("use Maia\\Orm\\Connection;", $publicIndex);
        $this->assertStringContainsString("use Maia\\Orm\\Model;", $publicIndex);
        $this->assertStringContainsString("Model::setConnection(\$connection);", $publicIndex);
        $this->assertStringContainsString('return static function (App $app): void {', $routes);
        $this->assertStringContainsString("use Maia\\Core\\Config\\Env;", $appConfig);
        $this->assertStringContainsString("Env::get('APP_ENV', 'local')", $appConfig);
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
