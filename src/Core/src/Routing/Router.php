<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use ReflectionClass;
use ReflectionMethod;

/**
 * Router defines a framework component for this package.
 */
class Router
{
    /**
     * @var array<int, array{
     *     http_method: string,
     *     path: string,
     *     segments: array<int, string>,
     *     controller: string,
     *     method: string,
     *     middleware: array<int, string>
     * }>
     */
    private array $routes = [];

    /**
     * Register controller and return void.
     * @param string $class Input value.
     * @return void Output value.
     */
    public function registerController(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $controllerAttributes = $reflection->getAttributes(Controller::class);

        $prefix = '';
        if ($controllerAttributes !== []) {
            /** @var Controller $controller */
            $controller = $controllerAttributes[0]->newInstance();
            $prefix = $controller->prefix;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);
            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();
                $fullPath = $this->joinPaths($prefix, $route->path);
                $normalizedPath = $this->normalizePath($fullPath);

                $this->routes[] = [
                    'http_method' => strtoupper($route->method),
                    'path' => $normalizedPath,
                    'segments' => $this->splitPath($normalizedPath),
                    'controller' => $class,
                    'method' => $method->getName(),
                    'middleware' => $route->middleware,
                ];
            }
        }
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        $normalizedMethod = strtoupper($method);
        $normalizedPath = $this->normalizePath($path);
        $pathSegments = $this->splitPath($normalizedPath);

        foreach ($this->routes as $route) {
            if ($route['http_method'] !== $normalizedMethod) {
                continue;
            }

            $params = $this->matchSegments($route['segments'], $pathSegments);
            if ($params === null) {
                continue;
            }

            return new RouteMatch(
                controller: $route['controller'],
                method: $route['method'],
                params: $params,
                middleware: $route['middleware']
            );
        }

        return null;
    }

    /**
     * Routes and return array.
     * @return array Output value.
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Normalize path and return string.
     * @param string $path Input value.
     * @return string Output value.
     */
    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        if (!is_string($path) || $path === '') {
            return '/';
        }

        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : '/' . $trimmed;
    }

    /**
     * Join paths and return string.
     * @param string $prefix Input value.
     * @param string $routePath Input value.
     * @return string Output value.
     */
    private function joinPaths(string $prefix, string $routePath): string
    {
        $prefix = trim($prefix);
        $routePath = trim($routePath);

        $prefix = $prefix === '' ? '' : '/' . trim($prefix, '/');
        $routePath = $routePath === '' ? '' : '/' . trim($routePath, '/');

        if ($prefix === '' && $routePath === '') {
            return '/';
        }

        if ($routePath === '') {
            return $prefix;
        }

        if ($prefix === '') {
            return $routePath;
        }

        return $prefix . $routePath;
    }

    /**
     * Split path and return array.
     * @param string $path Input value.
     * @return array Output value.
     */
    private function splitPath(string $path): array
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /**
     * Match segments and return array|null.
     * @param array $routeSegments Input value.
     * @param array $pathSegments Input value.
     * @return array|null Output value.
     */
    private function matchSegments(array $routeSegments, array $pathSegments): ?array
    {
        if (count($routeSegments) !== count($pathSegments)) {
            return null;
        }

        $params = [];

        foreach ($routeSegments as $index => $segment) {
            $pathSegment = $pathSegments[$index];

            if ($this->isParameterSegment($segment)) {
                $params[trim($segment, '{}')] = $pathSegment;
                continue;
            }

            if ($segment !== $pathSegment) {
                return null;
            }
        }

        return $params;
    }

    /**
     * Is parameter segment and return bool.
     * @param string $segment Input value.
     * @return bool Output value.
     */
    private function isParameterSegment(string $segment): bool
    {
        return str_starts_with($segment, '{')
            && str_ends_with($segment, '}')
            && strlen($segment) > 2;
    }
}
