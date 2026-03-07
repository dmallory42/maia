<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

/**
 * Inspects a Maia project and outputs its structure (routes, models, middleware, config).
 */
class DescribeCommand extends Command
{
    /**
     * Set up the describe command with optional project root and pre-loaded controllers.
     * @param string|null $projectRoot Absolute path to the project; defaults to cwd.
     * @param array $controllers Pre-registered controller class names to inspect for routes.
     * @return void
     */
    public function __construct(
        private ?string $projectRoot = null,
        private array $controllers = []
    ) {
    }

    /**
     * Return the command name.
     * @return string The command identifier.
     */
    public function name(): string
    {
        return 'describe';
    }

    /**
     * Return the command description.
     * @return string Short summary for help output.
     */
    public function description(): string
    {
        return 'Describe project structure and metadata';
    }

    /**
     * Collect project metadata and output it as text or JSON.
     * @param array $args CLI arguments (unused).
     * @param Output $output Writer for the project manifest.
     * @return int Exit code (always 0).
     */
    public function execute(array $args, Output $output): int
    {
        $root = $this->projectRoot ?? getcwd();
        $manifest = [
            'routes' => $this->routeManifest(),
            'models' => $this->listPhpFiles($root . '/app/Models'),
            'middleware' => $this->listPhpFiles($root . '/app/Middleware'),
            'config' => $this->listPhpFiles($root . '/config'),
            'maia' => $this->readMaiaManifest($root . '/maia.json'),
        ];

        if ($output->isJson()) {
            $output->json($manifest);

            return 0;
        }

        $output->line('Routes: ' . count($manifest['routes']));
        $output->line('Models: ' . implode(', ', $manifest['models']));
        $output->line('Middleware: ' . implode(', ', $manifest['middleware']));
        $output->line('Config files: ' . implode(', ', $manifest['config']));

        return 0;
    }

    /**
     * Collect all registered routes from the project's controllers.
     * @return array List of route definition arrays.
     */
    private function routeManifest(): array
    {
        $routes = new RoutesCommand($this->projectRoot, $this->controllers);

        return $routes->collectRoutes();
    }

    /**
     * Return sorted basenames of all .php files in a directory.
     * @param string $directory Absolute path to scan.
     * @return array List of PHP file basenames, or empty if the directory does not exist.
     */
    private function listPhpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/') . '/*.php');
        if ($files === false) {
            return [];
        }

        $names = array_map(static fn (string $path): string => basename($path), $files);
        sort($names);

        return $names;
    }

    /**
     * Parse and return the contents of maia.json, or an empty array if missing.
     * @param string $path Absolute path to the maia.json file.
     * @return array Decoded manifest data.
     */
    private function readMaiaManifest(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
