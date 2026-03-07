<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use RuntimeException;

/**
 * Scaffolds a new Maia project with directory structure, config files, and optional Composer install.
 */
class NewCommand extends Command
{
    /**
     * Configure the new-project scaffolder.
     * @param string|null $workspace Parent directory for the new project; defaults to cwd.
     * @param bool $autoInstall Whether to run composer install after scaffolding.
     * @return void
     */
    public function __construct(
        private ?string $workspace = null,
        private bool $autoInstall = true
    ) {
    }

    /**
     * Return the command name.
     * @return string The command identifier.
     */
    public function name(): string
    {
        return 'new';
    }

    /**
     * Return the command description.
     * @return string Short summary for help output.
     */
    public function description(): string
    {
        return 'Create a new Maia project';
    }

    /**
     * Scaffold the project directory, write all template files, and optionally run composer install.
     * @param array $args CLI arguments; expects the project name as first positional arg.
     * @param Output $output Writer for status messages and errors.
     * @return int Exit code (0 on success, 1 on failure).
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
     * Find the first non-flag argument to use as the project name.
     * @param array $args CLI arguments to search.
     * @return string|null The project name, or null if none was provided.
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
     * Build the absolute path where the new project will be created.
     * @param string $projectName Directory name for the new project.
     * @return string Absolute filesystem path for the project root.
     */
    private function targetPath(string $projectName): string
    {
        $base = $this->workspace ?? getcwd();

        return rtrim((string) $base, '/') . '/' . $projectName;
    }

    /**
     * Create the standard Maia project directory structure.
     * @param string $target Absolute path to the project root.
     * @return void
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
     * Generate and write all scaffolded files (config, routes, composer.json, etc.) into the project.
     * @param string $target Absolute path to the project root.
     * @param string $projectName Name of the project, used in templates.
     * @return void
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
     * Load a template file and interpolate variables into it.
     * @param string $name Template name (resolved from the Templates directory).
     * @param array $vars Key-value pairs to substitute into the template.
     * @return string The rendered template content.
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
     * Determine whether composer install should run after scaffolding.
     * @param array $args CLI arguments; checks for --no-install flag.
     * @return bool True if composer install should be executed.
     */
    private function shouldRunComposerInstall(array $args): bool
    {
        if (!$this->autoInstall) {
            return false;
        }

        return !in_array('--no-install', $args, true);
    }

    /**
     * Check whether the composer binary is available on the system PATH.
     * @return bool True if composer can be found.
     */
    private function composerAvailable(): bool
    {
        $result = shell_exec('command -v composer');

        return is_string($result) && trim($result) !== '';
    }

    /**
     * Execute composer install in the new project directory.
     * @param string $path Absolute path to the project where composer.json resides.
     * @param Output $output Writer for reporting install failures.
     * @return void
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
     * Generate the config/app.php file content.
     * @param string $projectName Application name to embed in the config.
     * @return string PHP source for the app configuration file.
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
     * Generate the config/database.php file content.
     * @return string PHP source for the database configuration file.
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
     * Generate the config/auth.php file content.
     * @return string PHP source for the auth configuration file.
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
     * Generate the config/cors.php file content.
     * @return string PHP source for the CORS configuration file.
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
     * Generate the config/logging.php file content.
     * @return string PHP source for the logging configuration file.
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
     * Generate the config/middleware.php file content.
     * @return string PHP source for the middleware configuration file.
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
     * Generate the routes/api.php file content with example controller registration.
     * @return string PHP source for the API routes file.
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
     * Generate the maia.json manifest describing the project layout.
     * @return string JSON-encoded manifest content.
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
