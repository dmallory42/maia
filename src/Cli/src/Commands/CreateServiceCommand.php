<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateServiceCommand defines a framework component for this package.
 */
class CreateServiceCommand extends BaseCreateCommand
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:service';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create a service class';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
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
