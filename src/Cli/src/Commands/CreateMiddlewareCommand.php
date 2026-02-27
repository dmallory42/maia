<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateMiddlewareCommand defines a framework component for this package.
 */
class CreateMiddlewareCommand extends BaseCreateCommand
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:middleware';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create an HTTP middleware class';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'middleware name');
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        $path = 'app/Middleware/' . $class . '.php';

        $template = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

class {{class}} implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
PHP;

        $content = str_replace('{{class}}', $class, $template);
        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
