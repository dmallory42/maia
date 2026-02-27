<?php

declare(strict_types=1);

namespace Maia\Core;

use Maia\Core\Config\Config;
use Maia\Core\Config\Env;
use Maia\Core\Container\Container;
use Maia\Core\Exceptions\ExceptionHandler;
use Maia\Core\Exceptions\NotFoundException;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Logging\Logger;
use Maia\Core\Middleware\Middleware as MiddlewareContract;
use Maia\Core\Middleware\Pipeline;
use Maia\Core\Routing\MiddlewareAttribute;
use Maia\Core\Routing\RouteMatch;
use Maia\Core\Routing\Router;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;

/**
 * App defines a framework component for this package.
 */
class App
{
    /** @var array<int, MiddlewareContract|string> */
    private array $globalMiddleware = [];

    /**
     * Create an instance with configured dependencies and defaults.
     * @param Container $container Input value.
     * @param Router $router Input value.
     * @param Config $config Input value.
     * @param Logger $logger Input value.
     * @param ExceptionHandler $exceptionHandler Input value.
     * @return void Output value.
     */
    private function __construct(
        private Container $container,
        private Router $router,
        private Config $config,
        private Logger $logger,
        private ExceptionHandler $exceptionHandler
    ) {
    }

    /**
     * Create and return self.
     * @param string|null $configDir Input value.
     * @param string|null $envFile Input value.
     * @return self Output value.
     */
    public static function create(?string $configDir = null, ?string $envFile = null): self
    {
        if ($envFile !== null) {
            Env::load($envFile);
        }

        $config = new Config($configDir ?? '');
        $debug = self::resolveDebugFlag($config);
        $logger = self::buildLogger($config);

        $container = new Container();
        $router = new Router();

        $container->instance(Container::class, $container);
        $container->instance(Config::class, $config);
        $container->instance(Logger::class, $logger);
        $container->instance(Router::class, $router);

        $exceptionHandler = new ExceptionHandler($debug);

        return new self($container, $router, $config, $logger, $exceptionHandler);
    }

    /**
     * Register controller and return void.
     * @param string $class Input value.
     * @return void Output value.
     */
    public function registerController(string $class): void
    {
        $this->router->registerController($class);
    }

    /**
     * Container and return Container.
     * @return Container Output value.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Add middleware and return void.
     * @param MiddlewareContract|string $middleware Input value.
     * @return void Output value.
     */
    public function addMiddleware(MiddlewareContract|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @return Response Output value.
     */
    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request->method(), $request->path());
            if ($match === null) {
                throw new NotFoundException('Route not found');
            }

            $requestWithParams = $request->withRouteParams($match->params);
            $middleware = $this->resolveMiddlewareStack($match);
            $pipeline = new Pipeline($middleware);

            return $pipeline->run(
                $requestWithParams,
                fn (Request $req): Response => $this->dispatch($match, $req)
            );
        } catch (Throwable $exception) {
            $this->logger->error('Request handling failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->exceptionHandler->handle($exception);
        }
    }

    /**
     * Dispatch and return Response.
     * @param RouteMatch $match Input value.
     * @param Request $request Input value.
     * @return Response Output value.
     */
    private function dispatch(RouteMatch $match, Request $request): Response
    {
        $controller = $this->container->resolve($match->controller);
        $method = new ReflectionMethod($controller, $match->method);
        $args = $this->resolveMethodArguments($method, $request, $match->params);

        $result = $method->invokeArgs($controller, $args);

        if ($result instanceof Response) {
            return $result;
        }

        return Response::json($result);
    }

    /**
     * Resolve method arguments and return array.
     * @param ReflectionMethod $method Input value.
     * @param Request $request Input value.
     * @param array $routeParams Input value.
     * @return array Output value.
     */
    private function resolveMethodArguments(ReflectionMethod $method, Request $request, array $routeParams): array
    {
        $args = [];

        foreach ($method->getParameters() as $parameter) {
            $args[] = $this->resolveMethodParameter($parameter, $request, $routeParams, $method);
        }

        return $args;
    }

    /**
     * Resolve method parameter and return mixed.
     * @param ReflectionParameter $parameter Input value.
     * @param Request $request Input value.
     * @param array $routeParams Input value.
     * @param ReflectionMethod $method Input value.
     * @return mixed Output value.
     */
    private function resolveMethodParameter(
        ReflectionParameter $parameter,
        Request $request,
        array $routeParams,
        ReflectionMethod $method
    ): mixed {
        $type = $parameter->getType();

        /**
         * true defines a framework component for this package.
         */
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
            return $request;
        }

        $name = $parameter->getName();
        if (array_key_exists($name, $routeParams)) {
            return $this->castRouteParameter($routeParams[$name], $type);
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->container->resolve($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException(
            sprintf(
                'Cannot resolve parameter [%s] for controller action [%s::%s].',
                $name,
                $method->getDeclaringClass()->getName(),
                $method->getName()
            )
        );
    }

    /**
     * Cast route parameter and return mixed.
     * @param string $value Input value.
     * @param \ReflectionType|null $type Input value.
     * @return mixed Output value.
     */
    private function castRouteParameter(string $value, \ReflectionType|null $type): mixed
    {
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'string' => $value,
            default => $value,
        };
    }

    /**
     * Resolve middleware stack and return array.
     * @param RouteMatch $match Input value.
     * @return array Output value.
     */
    private function resolveMiddlewareStack(RouteMatch $match): array
    {
        $stack = [];
        $stack = array_merge($stack, $this->resolveMiddlewareEntries($this->globalMiddleware));

        $reflection = new ReflectionClass($match->controller);
        $stack = array_merge($stack, $this->resolveAttributeMiddleware($reflection->getAttributes(MiddlewareAttribute::class)));

        $method = $reflection->getMethod($match->method);
        $stack = array_merge($stack, $this->resolveAttributeMiddleware($method->getAttributes(MiddlewareAttribute::class)));

        $stack = array_merge($stack, $this->resolveMiddlewareEntries($match->middleware));

        return $stack;
    }

    /**
     * Resolve attribute middleware and return array.
     * @param array $attributes Input value.
     * @return array Output value.
     */
    private function resolveAttributeMiddleware(array $attributes): array
    {
        $middlewares = [];

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if (!$instance instanceof MiddlewareAttribute) {
                continue;
            }

            $middlewares = array_merge($middlewares, $this->resolveMiddlewareEntries($instance->middleware));
        }

        return $middlewares;
    }

    /**
     * Resolve middleware entries and return array.
     * @param array $entries Input value.
     * @return array Output value.
     */
    private function resolveMiddlewareEntries(array $entries): array
    {
        $resolved = [];

        foreach ($entries as $entry) {
            if ($entry instanceof MiddlewareContract) {
                $resolved[] = $entry;
                continue;
            }

            if (is_string($entry)) {
                $middleware = $this->container->resolve($entry);
                if (!$middleware instanceof MiddlewareContract) {
                    throw new RuntimeException("Middleware [{$entry}] must implement Middleware interface.");
                }

                $resolved[] = $middleware;
                continue;
            }

            throw new RuntimeException('Invalid middleware entry encountered.');
        }

        return $resolved;
    }

    /**
     * Resolve debug flag and return bool.
     * @param Config $config Input value.
     * @return bool Output value.
     */
    private static function resolveDebugFlag(Config $config): bool
    {
        $fromEnv = self::boolFromString(Env::get('APP_DEBUG'));
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return (bool) $config->get('app.debug', false);
    }

    /**
     * Build logger and return Logger.
     * @param Config $config Input value.
     * @return Logger Output value.
     */
    private static function buildLogger(Config $config): Logger
    {
        $level = (string) $config->get('logging.level', 'info');
        $path = $config->get('logging.path');

        if (is_string($path)) {
            if (strtolower($path) === 'null') {
                return Logger::null();
            }

            if ($path === 'php://stderr') {
                return Logger::stderr($level);
            }

            return new Logger($path, $level);
        }

        return Logger::null();
    }

    /**
     * Bool from string and return bool|null.
     * @param string|null $value Input value.
     * @return bool|null Output value.
     */
    private static function boolFromString(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }
}
