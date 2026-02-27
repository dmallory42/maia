<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use ReflectionClass;
use ReflectionMethod;

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
     * @return array<int, array{
     *     http_method: string,
     *     path: string,
     *     segments: array<int, string>,
     *     controller: string,
     *     method: string,
     *     middleware: array<int, string>
     * }>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        if (!is_string($path) || $path === '') {
            return '/';
        }

        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : '/' . $trimmed;
    }

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

    /** @return array<int, string> */
    private function splitPath(string $path): array
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /**
     * @param array<int, string> $routeSegments
     * @param array<int, string> $pathSegments
     * @return array<string, string>|null
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

    private function isParameterSegment(string $segment): bool
    {
        return str_starts_with($segment, '{')
            && str_ends_with($segment, '}')
            && strlen($segment) > 2;
    }
}
