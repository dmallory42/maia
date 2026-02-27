<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateModelCommand defines a framework component for this package.
 */
class CreateModelCommand extends BaseCreateCommand
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:model';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create an ORM model';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'model name');
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        $table = $this->snakeCase($class) . 's';
        $path = 'app/Models/' . $class . '.php';

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Maia\Orm\Attributes\Table;
use Maia\Orm\Model;

#[Table('{$table}')]
class {$class} extends Model
{
}
PHP;

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
