<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

class CreateServiceCommand extends BaseCreateCommand
{
    public function name(): string
    {
        return 'create:service';
    }

    public function description(): string
    {
        return 'Create a service class';
    }

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
