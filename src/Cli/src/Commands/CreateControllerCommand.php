<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateControllerCommand defines a framework component for this package.
 */
class CreateControllerCommand extends BaseCreateCommand
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:controller';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create a controller scaffold';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'controller name');
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        $prefix = $this->snakeCase(str_replace('Controller', '', $class));
        $path = 'app/Controllers/' . $class . '.php';

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;

#[Controller('/{$prefix}')]
class {$class}
{
    #[Route('/', method: 'GET')]
    public function index(): Response
    {
        return Response::json(['message' => '{$class} index']);
    }
}
PHP;

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
