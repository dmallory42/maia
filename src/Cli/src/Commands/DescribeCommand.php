<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;

class DescribeCommand extends Command
{
    /**
     * @param array<int, class-string> $controllers
     */
    public function __construct(
        private ?string $projectRoot = null,
        private array $controllers = []
    ) {
    }

    public function name(): string
    {
        return 'describe';
    }

    public function description(): string
    {
        return 'Describe project structure and metadata';
    }

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
     * @return array<int, array{
     *     method: string,
     *     path: string,
     *     controller: string,
     *     action: string,
     *     middleware: array<int, string>
     * }>
     */
    private function routeManifest(): array
    {
        $routes = new RoutesCommand($this->projectRoot, $this->controllers);

        return $routes->collectRoutes();
    }

    /** @return array<int, string> */
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

    /** @return array<string, mixed> */
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
