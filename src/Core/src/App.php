<?php

declare(strict_types=1);

namespace Maia\Core;

use Closure;
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
 * Application kernel that boots the framework, dispatches HTTP requests through middleware, and returns responses.
 */
class App
{
    /** @var array<int, MiddlewareContract|string> */
    private array $globalMiddleware = [];

    /**
     * Initialize the application kernel with its core services.
     * @param Container $container Dependency injection container for resolving services.
     * @param Router $router Route registry used to match incoming requests.
     * @param Config $config Application configuration loaded from PHP files.
     * @param Logger $logger Logger instance for recording runtime events.
     * @param ExceptionHandler $exceptionHandler Handler that converts exceptions to HTTP responses.
     * @return void
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
     * Bootstrap a new application instance, loading environment and config files.
     * @param string|null $configDir Directory containing PHP configuration files, or null to skip.
     * @param string|null $envFile Path to a .env file to load, or null to skip.
     * @return self Fully initialized application ready to handle requests.
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
        self::configureContainerBindings($container, $config);

        $exceptionHandler = new ExceptionHandler($debug);

        return new self($container, $router, $config, $logger, $exceptionHandler);
    }

    /**
     * Register a controller class so its route attributes are discovered.
     * @param string $class Fully qualified class name of the controller to register.
     * @return void
     */
    public function registerController(string $class): void
    {
        $this->router->registerController($class);
    }

    /**
     * Return the dependency injection container.
     * @return Container The application's DI container.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Append a middleware to the global stack applied to every request.
     * @param MiddlewareContract|string $middleware Middleware instance or class name to resolve from the container.
     * @return void
     */
    public function addMiddleware(MiddlewareContract|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Process an HTTP request through the middleware pipeline and return a response.
     * @param Request $request The incoming HTTP request to handle.
     * @return Response The HTTP response produced by the matched route or exception handler.
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
     * Invoke the matched controller action and return its response.
     * @param RouteMatch $match The resolved route containing controller, method, and parameters.
     * @param Request $request The current HTTP request with route params attached.
     * @return Response The response returned by the controller,
     *     or a JSON-encoded response if the action returns raw data.
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
     * Build the argument list for a controller method using type hints, route params, and the container.
     * @param ReflectionMethod $method Reflection of the controller method to invoke.
     * @param Request $request The current HTTP request.
     * @param array $routeParams Named parameters extracted from the URL path.
     * @return array Ordered list of resolved arguments matching the method signature.
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
     * Resolve a single controller method parameter from the request, route params, or container.
     * @param ReflectionParameter $parameter The parameter to resolve.
     * @param Request $request The current HTTP request (injected when type-hinted).
     * @param array $routeParams Named route parameters matched from the URL.
     * @param ReflectionMethod $method The controller method, used for error messages.
     * @return mixed The resolved value for the parameter.
     */
    private function resolveMethodParameter(
        ReflectionParameter $parameter,
        Request $request,
        array $routeParams,
        ReflectionMethod $method
    ): mixed {
        $type = $parameter->getType();

        /** Inject the Request object when the parameter type-hints it. */
        if (
            $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_a($type->getName(), Request::class, true)
        ) {
            return $request;
        }

        $name = $parameter->getName();
        if (array_key_exists($name, $routeParams)) {
            return $this->castRouteParameter($name, $routeParams[$name], $type);
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
     * Cast a route parameter string to the type declared by the controller method signature.
     * @param string $name The route parameter name, used when reporting invalid values.
     * @param string $value The raw string value captured from the URL segment.
     * @param \ReflectionType|null $type The declared type of the controller parameter, or null if untyped.
     * @return mixed The value cast to int, float, bool, or left as a string.
     */
    private function castRouteParameter(string $name, string $value, \ReflectionType|null $type): mixed
    {
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => $this->validateRouteParameter(
                $name,
                filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
            ),
            'float' => $this->validateRouteParameter(
                $name,
                filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            ),
            'bool' => $this->validateRouteParameter(
                $name,
                filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ),
            'string' => $value,
            default => $value,
        };
    }

    /**
     * Reject invalid builtin route parameter values with a 404 instead of passing coerced data to the controller.
     * @param string $name The route parameter name.
     * @param mixed $value The cast value, or null when validation failed.
     * @return mixed The validated value.
     */
    private function validateRouteParameter(string $name, mixed $value): mixed
    {
        if ($value !== null) {
            return $value;
        }

        throw new NotFoundException(sprintf('Route parameter [%s] is invalid.', $name));
    }

    /**
     * Merge global, controller-level, method-level, and route-level middleware into a single ordered stack.
     * @param RouteMatch $match The matched route whose middleware definitions are included.
     * @return array Ordered list of resolved Middleware instances.
     */
    private function resolveMiddlewareStack(RouteMatch $match): array
    {
        $stack = [];
        $stack = array_merge($stack, $this->resolveMiddlewareEntries($this->globalMiddleware));

        $reflection = new ReflectionClass($match->controller);
        $stack = array_merge(
            $stack,
            $this->resolveAttributeMiddleware($reflection->getAttributes(MiddlewareAttribute::class))
        );

        $method = $reflection->getMethod($match->method);
        $stack = array_merge(
            $stack,
            $this->resolveAttributeMiddleware($method->getAttributes(MiddlewareAttribute::class))
        );

        $stack = array_merge($stack, $this->resolveMiddlewareEntries($match->middleware));

        return $stack;
    }

    /**
     * Extract and resolve middleware from MiddlewareAttribute PHP attributes.
     * @param array $attributes ReflectionAttribute instances to inspect for middleware declarations.
     * @return array Resolved Middleware instances declared via attributes.
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
     * Convert an array of middleware instances or class names into resolved Middleware objects.
     * @param array $entries Middleware instances or fully qualified class names to resolve.
     * @return array Resolved Middleware instances ready for the pipeline.
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
     * Determine the debug mode from the APP_DEBUG env var, falling back to the config file.
     * @param Config $config Application configuration to check if the env var is not set.
     * @return bool True when the application is running in debug mode.
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
     * Create a Logger instance based on the logging configuration (path and level).
     * @param Config $config Application configuration containing logging.level and logging.path.
     * @return Logger Configured logger, or a null logger if no valid path is set.
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
     * Register configured factories and singletons from config/app.php with the container.
     * @param Container $container The application's DI container.
     * @param Config $config The loaded configuration repository.
     * @return void
     */
    private static function configureContainerBindings(Container $container, Config $config): void
    {
        $factories = $config->get('app.factories', []);
        if (is_array($factories)) {
            foreach ($factories as $class => $factory) {
                if (is_string($class) && $factory instanceof Closure) {
                    $container->factory($class, $factory);
                }
            }
        }

        $singletons = $config->get('app.singletons', []);
        if (!is_array($singletons)) {
            return;
        }

        foreach ($singletons as $class => $factory) {
            if (is_int($class) && is_string($factory)) {
                $container->singleton($factory);
                continue;
            }

            if (is_string($class) && $factory instanceof Closure) {
                $container->singleton($class, $factory);
            }
        }
    }

    /**
     * Parse a string like "true", "1", "yes" into a boolean, or return null for unrecognized values.
     * @param string|null $value The string to parse, or null.
     * @return bool|null The parsed boolean, or null if the value is absent or unrecognized.
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
