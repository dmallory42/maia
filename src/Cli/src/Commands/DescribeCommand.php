<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

/**
 * DescribeCommand defines a framework component for this package.
 */
class DescribeCommand extends Command
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string|null $projectRoot Input value.
     * @param array $controllers Input value.
     * @return void Output value.
     */
    public function __construct(
        private ?string $projectRoot = null,
        private array $controllers = []
    ) {
    }

    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'describe';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Describe project structure and metadata';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
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
     * Route manifest and return array.
     * @return array Output value.
     */
    private function routeManifest(): array
    {
        $routes = new RoutesCommand($this->projectRoot, $this->controllers);

        return $routes->collectRoutes();
    }

    /**
     * List php files and return array.
     * @param string $directory Input value.
     * @return array Output value.
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
     * Read maia manifest and return array.
     * @param string $path Input value.
     * @return array Output value.
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
