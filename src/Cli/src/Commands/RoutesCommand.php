<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Core\Routing\Router;

class RoutesCommand extends Command
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
        return 'routes';
    }

    public function description(): string
    {
        return 'List application routes';
    }

    public function execute(array $args, Output $output): int
    {
        $routes = $this->collectRoutes();

        if ($output->isJson()) {
            $output->json(['routes' => $routes]);

            return 0;
        }

        foreach ($routes as $route) {
            $output->line(sprintf(
                '%s %s -> %s@%s',
                $route['method'],
                $route['path'],
                $route['controller'],
                $route['action']
            ));
        }

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
    public function collectRoutes(): array
    {
        $router = new Router();

        foreach ($this->resolvedControllers() as $controller) {
            $router->registerController($controller);
        }

        $routes = [];

        foreach ($router->routes() as $route) {
            $routes[] = [
                'method' => $route['http_method'],
                'path' => $route['path'],
                'controller' => $route['controller'],
                'action' => $route['method'],
                'middleware' => $route['middleware'],
            ];
        }

        return $routes;
    }

    /** @return array<int, class-string> */
    private function resolvedControllers(): array
    {
        if ($this->controllers !== []) {
            return $this->controllers;
        }

        $root = $this->projectRoot ?? getcwd();
        $path = rtrim($root, '/') . '/app/Controllers';

        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.php');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            require_once $file;
        }

        return $this->discoverClassesInPath($path);
    }

    /**
     * @return array<int, class-string>
     */
    private function discoverClassesInPath(string $path): array
    {
        $classes = [];

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);
            $filename = $reflection->getFileName();

            if (!is_string($filename)) {
                continue;
            }

            if (str_starts_with($filename, $path)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
