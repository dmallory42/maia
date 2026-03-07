<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that scaffolds an HTTP middleware class.
 */
class CreateMiddlewareCommand extends BaseCreateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:middleware';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create an HTTP middleware class';
    }

    /**
     * Generate a middleware scaffold in app/Middleware.
     * @param array $args CLI arguments containing the middleware name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
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
