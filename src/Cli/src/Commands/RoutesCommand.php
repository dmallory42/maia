<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Core\Routing\Router;

/**
 * RoutesCommand defines a framework component for this package.
 */
class RoutesCommand extends Command
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
        return 'routes';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'List application routes';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
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
     * Collect routes and return array.
     * @return array Output value.
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

    /**
     * Resolved controllers and return array.
     * @return array Output value.
     */
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
     * Discover classes in path and return array.
     * @param string $path Input value.
     * @return array Output value.
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
