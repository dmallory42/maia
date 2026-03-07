<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Command;
use Maia\Cli\Output;
use Maia\Core\Routing\Router;

/**
 * Lists all registered HTTP routes discovered from the project's controllers.
 */
class RoutesCommand extends Command
{
    /**
     * Set up the routes command with optional project root and controller list.
     * @param string|null $projectRoot Absolute path to the project; defaults to cwd.
     * @param array $controllers Pre-registered controller class names to scan for routes.
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
        return 'routes';
    }

    /**
     * Return the command description.
     * @return string Short summary for help output.
     */
    public function description(): string
    {
        return 'List application routes';
    }

    /**
     * Collect all routes and display them as text lines or JSON.
     * @param array $args CLI arguments (unused).
     * @param Output $output Writer for the route listing.
     * @return int Exit code (always 0).
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
     * Register controllers with a Router and return the collected route definitions.
     * @return array List of route arrays with method, path, controller, action, and middleware keys.
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
     * Return the controller list, auto-discovering from the project if none were provided.
     * @return array Fully-qualified controller class names.
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
     * Find all declared classes whose source file resides under the given directory.
     * @param string $path Absolute path to the controllers directory.
     * @return array Fully-qualified class names found in the directory.
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
