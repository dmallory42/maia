<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

class CreateMiddlewareCommand extends BaseCreateCommand
{
    public function name(): string
    {
        return 'create:middleware';
    }

    public function description(): string
    {
        return 'Create an HTTP middleware class';
    }

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
