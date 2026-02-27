<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

class CreateControllerCommand extends BaseCreateCommand
{
    public function name(): string
    {
        return 'create:controller';
    }

    public function description(): string
    {
        return 'Create a controller scaffold';
    }

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
