<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

class NewCommand extends Command
{
    public function __construct(
        private ?string $workspace = null,
        private bool $autoInstall = true
    ) {
    }

    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Create a new Maia project';
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args, Output $output): int
    {
        $projectName = $this->extractProjectName($args);
        if ($projectName === null) {
            $output->error('Project name is required: maia new <name>');

            return 1;
        }

        $target = $this->targetPath($projectName);
        if (file_exists($target)) {
            $output->error(sprintf('Target directory already exists: %s', $target));

            return 1;
        }

        $this->createDirectories($target);
        $this->writeFiles($target, $projectName);

        if ($this->shouldRunComposerInstall($args) && $this->composerAvailable()) {
            $this->runComposerInstall($target, $output);
        }

        $output->line(sprintf('Project created at %s', $target));

        return 0;
    }

    /** @param array<int, string> $args */
    private function extractProjectName(array $args): ?string
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                return trim($arg);
            }
        }

        return null;
    }

    private function targetPath(string $projectName): string
    {
        $base = $this->workspace ?? getcwd();

        return rtrim((string) $base, '/') . '/' . $projectName;
    }

    private function createDirectories(string $target): void
    {
        $directories = [
            'app/Controllers',
            'app/Services',
            'app/Models',
            'app/Middleware',
            'app/Requests',
            'config',
            'routes',
            'database/migrations',
            'storage/logs',
            'public',
            'tests',
        ];

        foreach ($directories as $directory) {
            mkdir($target . '/' . $directory, 0777, true);
        }
    }

    private function writeFiles(string $target, string $projectName): void
    {
        $files = [
            'composer.json' => $this->renderTemplate('composer', ['project_name' => $projectName]),
            'public/index.php' => $this->renderTemplate('public_index', ['project_name' => $projectName]),
            '.env.example' => $this->renderTemplate('env_example', ['project_name' => $projectName]),
            'config/app.php' => $this->configApp($projectName),
            'config/database.php' => $this->configDatabase(),
            'config/auth.php' => $this->configAuth(),
            'config/cors.php' => $this->configCors(),
            'config/logging.php' => $this->configLogging(),
            'config/middleware.php' => $this->configMiddleware(),
            'routes/api.php' => $this->routesApi(),
            'CLAUDE.md' => $this->renderTemplate('CLAUDE.md', ['project_name' => $projectName]),
            'AGENTS.md' => $this->renderTemplate('AGENTS.md', ['project_name' => $projectName]),
            'maia.json' => $this->maiaManifest(),
        ];

        foreach ($files as $relativePath => $content) {
            file_put_contents($target . '/' . $relativePath, $content);
        }
    }

    /** @param array<string, string> $vars */
    private function renderTemplate(string $name, array $vars): string
    {
        $templatePath = __DIR__ . '/../Templates/' . $name . '.php';

        $renderer = require $templatePath;

        return $renderer($vars);
    }

    /** @param array<int, string> $args */
    private function shouldRunComposerInstall(array $args): bool
    {
        if (!$this->autoInstall) {
            return false;
        }

        return !in_array('--no-install', $args, true);
    }

    private function composerAvailable(): bool
    {
        $result = shell_exec('command -v composer');

        return is_string($result) && trim($result) !== '';
    }

    private function runComposerInstall(string $path, Output $output): void
    {
        $command = sprintf('cd %s && composer install', escapeshellarg($path));

        exec($command, $ignored, $code);

        if ($code !== 0) {
            $output->error('composer install failed. You can run it manually.');
        }
    }

    private function configApp(string $projectName): string
    {
        return <<<PHP
<?php

return [
    'name' => '{$projectName}',
    'env' => env('APP_ENV', 'local'),
    'debug' => env('APP_DEBUG', 'true') === 'true',
    'factories' => [],
    'singletons' => [],
];
PHP;
    }

    private function configDatabase(): string
    {
        return <<<'PHP'
<?php

return [
    'dsn' => env('DB_DSN', 'sqlite:' . __DIR__ . '/../database/database.sqlite'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
];
PHP;
    }

    private function configAuth(): string
    {
        return <<<'PHP'
<?php

return [
    'jwt_secret' => env('JWT_SECRET', 'change-me-please-change-me-please!'),
    'api_keys' => explode(',', env('API_KEYS', '')),
];
PHP;
    }

    private function configCors(): string
    {
        return <<<'PHP'
<?php

return [
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-API-Key'],
];
PHP;
    }

    private function configLogging(): string
    {
        return <<<'PHP'
<?php

return [
    'path' => __DIR__ . '/../storage/logs/app.log',
    'level' => env('LOG_LEVEL', 'info'),
];
PHP;
    }

    private function configMiddleware(): string
    {
        return <<<'PHP'
<?php

return [
    'global' => [],
];
PHP;
    }

    private function routesApi(): string
    {
        return <<<'PHP'
<?php

// Register controllers here.
// Example:
// $app->registerController(App\Controllers\UserController::class);
PHP;
    }

    private function maiaManifest(): string
    {
        $manifest = [
            'app' => [
                'controllers' => 'app/Controllers',
                'services' => 'app/Services',
                'models' => 'app/Models',
                'middleware' => 'app/Middleware',
                'requests' => 'app/Requests',
            ],
            'routes' => 'routes/api.php',
            'migrations' => 'database/migrations',
        ];

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return ($encoded ?: '{}') . PHP_EOL;
    }
}
