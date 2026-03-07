<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use ReflectionClass;
use ReflectionMethod;

/**
 * Collects route definitions from controller attributes and matches incoming requests to routes.
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
     * Scan a controller class for Route attributes and add its methods to the route table.
     * @param string $class Fully qualified class name of the controller to register.
     * @return void
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
     * Return all registered route definitions.
     * @return array List of route definition arrays.
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Strip query strings and normalize slashes so paths can be compared consistently.
     * @param string $path The raw URL path to normalize.
     * @return string The normalized path with a leading slash and no trailing slash.
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
     * Concatenate a controller prefix and a route path into a single normalized path.
     * @param string $prefix The controller-level path prefix (may be empty).
     * @param string $routePath The method-level route path (may be empty).
     * @return string The combined path starting with a leading slash.
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
     * Split a normalized path into its individual segments for matching.
     * @param string $path A normalized path starting with a leading slash.
     * @return array List of path segments (empty array for the root path).
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
     * Compare route segments against request path segments, extracting named parameters.
     * @param array $routeSegments Segments from the route definition, including {param} placeholders.
     * @param array $pathSegments Segments from the incoming request path.
     * @return array|null Associative array of captured parameters, or null if segments do not match.
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
     * Check whether a route segment is a {named} parameter placeholder.
     * @param string $segment The route segment to test.
     * @return bool True if the segment is wrapped in curly braces (e.g. "{id}").
     */
    private function isParameterSegment(string $segment): bool
    {
        return str_starts_with($segment, '{')
            && str_ends_with($segment, '}')
            && strlen($segment) > 2;
    }
}
