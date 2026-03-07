<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use RuntimeException;

/**
 * NewCommand defines a framework component for this package.
 */
class NewCommand extends Command
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string|null $workspace Input value.
     * @param bool $autoInstall Input value.
     * @return void Output value.
     */
    public function __construct(
        private ?string $workspace = null,
        private bool $autoInstall = true
    ) {
    }

    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'new';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create a new Maia project';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
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

    /**
     * Extract project name and return string|null.
     * @param array $args Input value.
     * @return string|null Output value.
     */
    private function extractProjectName(array $args): ?string
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                return trim($arg);
            }
        }

        return null;
    }

    /**
     * Target path and return string.
     * @param string $projectName Input value.
     * @return string Output value.
     */
    private function targetPath(string $projectName): string
    {
        $base = $this->workspace ?? getcwd();

        return rtrim((string) $base, '/') . '/' . $projectName;
    }

    /**
     * Create directories and return void.
     * @param string $target Input value.
     * @return void Output value.
     */
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

    /**
     * Write files and return void.
     * @param string $target Input value.
     * @param string $projectName Input value.
     * @return void Output value.
     */
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

    /**
     * Render template and return string.
     * @param string $name Input value.
     * @param array $vars Input value.
     * @return string Output value.
     */
    private function renderTemplate(string $name, array $vars): string
    {
        $basePath = __DIR__ . '/../Templates/' . $name;

        if (is_file($basePath)) {
            $content = file_get_contents($basePath);
            if (!is_string($content)) {
                throw new RuntimeException(sprintf('Unable to read template [%s].', $basePath));
            }

            foreach ($vars as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }

            return $content;
        }

        $phpTemplatePath = $basePath . '.php';
        if (!is_file($phpTemplatePath)) {
            throw new RuntimeException(sprintf('Template [%s] not found.', $name));
        }

        $renderer = require $phpTemplatePath;
        if (!is_callable($renderer)) {
            throw new RuntimeException(sprintf('Template [%s] must return a callable.', $phpTemplatePath));
        }

        return $renderer($vars);
    }

    /**
     * Should run composer install and return bool.
     * @param array $args Input value.
     * @return bool Output value.
     */
    private function shouldRunComposerInstall(array $args): bool
    {
        if (!$this->autoInstall) {
            return false;
        }

        return !in_array('--no-install', $args, true);
    }

    /**
     * Composer available and return bool.
     * @return bool Output value.
     */
    private function composerAvailable(): bool
    {
        $result = shell_exec('command -v composer');

        return is_string($result) && trim($result) !== '';
    }

    /**
     * Run composer install and return void.
     * @param string $path Input value.
     * @param Output $output Input value.
     * @return void Output value.
     */
    private function runComposerInstall(string $path, Output $output): void
    {
        $command = sprintf('cd %s && composer install', escapeshellarg($path));

        exec($command, $ignored, $code);

        if ($code !== 0) {
            $output->error('composer install failed. You can run it manually.');
        }
    }

    /**
     * Config app and return string.
     * @param string $projectName Input value.
     * @return string Output value.
     */
    private function configApp(string $projectName): string
    {
        return <<<PHP
<?php

use Maia\Core\Config\Env;

return [
    'name' => '{$projectName}',
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::get('APP_DEBUG', 'true') === 'true',
    'factories' => [],
    'singletons' => [],
];
PHP;
    }

    /**
     * Config database and return string.
     * @return string Output value.
     */
    private function configDatabase(): string
    {
        return <<<'PHP'
<?php

use Maia\Core\Config\Env;

$dsn = Env::get('DB_DSN');
if ($dsn === null || $dsn === '') {
    $dsn = 'sqlite:' . __DIR__ . '/../database/database.sqlite';
} elseif (str_starts_with($dsn, 'sqlite:')) {
    $path = substr($dsn, 7);
    $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;

    if ($path !== '' && $path !== ':memory:' && !str_starts_with($path, '/') && !$isWindowsAbsolute) {
        $dsn = 'sqlite:' . __DIR__ . '/../' . ltrim($path, '/');
    }
}

return [
    'dsn' => $dsn,
    'username' => Env::get('DB_USERNAME'),
    'password' => Env::get('DB_PASSWORD'),
];
PHP;
    }

    /**
     * Config auth and return string.
     * @return string Output value.
     */
    private function configAuth(): string
    {
        return <<<'PHP'
<?php

use Maia\Core\Config\Env;

return [
    'jwt_secret' => Env::get('JWT_SECRET', 'change-me-please-change-me-please!'),
    'api_keys' => explode(',', Env::get('API_KEYS', '')),
];
PHP;
    }

    /**
     * Config cors and return string.
     * @return string Output value.
     */
    private function configCors(): string
    {
        return <<<'PHP'
<?php

use Maia\Core\Config\Env;

return [
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', Env::get('CORS_ALLOWED_ORIGINS', ''))
    ))),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-API-Key'],
];
PHP;
    }

    /**
     * Config logging and return string.
     * @return string Output value.
     */
    private function configLogging(): string
    {
        return <<<'PHP'
<?php

use Maia\Core\Config\Env;

return [
    'path' => __DIR__ . '/../storage/logs/app.log',
    'level' => Env::get('LOG_LEVEL', 'info'),
];
PHP;
    }

    /**
     * Config middleware and return string.
     * @return string Output value.
     */
    private function configMiddleware(): string
    {
        return <<<'PHP'
<?php

return [
    'global' => [],
];
PHP;
    }

    /**
     * Routes api and return string.
     * @return string Output value.
     */
    private function routesApi(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Maia\Core\App;

return static function (App $app): void {
    // Register controllers here.
    // Example:
    // $app->registerController(App\Controllers\UserController::class);
};
PHP;
    }

    /**
     * Maia manifest and return string.
     * @return string Output value.
     */
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
