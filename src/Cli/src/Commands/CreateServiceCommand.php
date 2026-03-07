<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that scaffolds an application service class.
 */
class CreateServiceCommand extends BaseCreateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:service';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create a service class';
    }

    /**
     * Generate a service scaffold in app/Services.
     * @param array $args CLI arguments containing the service class name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'service name');
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        $path = 'app/Services/' . $class . '.php';

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Services;

class {$class}
{
}
PHP;

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
