<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that scaffolds a controller class with a basic index route.
 */
class CreateControllerCommand extends BaseCreateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:controller';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create a controller scaffold';
    }

    /**
     * Generate a controller scaffold in app/Controllers.
     * @param array $args CLI arguments containing the controller name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        return $this->scaffoldFromName($args, $output, 'controller name', function (string $class): array {
            $prefix = $this->snakeCase(str_replace('Controller', '', $class));

            return ['app/Controllers/' . $class . '.php', <<<PHP
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
PHP];
        });
    }
}
