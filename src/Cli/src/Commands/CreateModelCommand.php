<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that scaffolds an ORM model class.
 */
class CreateModelCommand extends BaseCreateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:model';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create an ORM model';
    }

    /**
     * Generate a model scaffold in app/Models.
     * @param array $args CLI arguments containing the model name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        return $this->scaffoldFromName($args, $output, 'model name', function (string $class): array {
            $table = $this->snakeCase($class) . 's';

            return ['app/Models/' . $class . '.php', <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Maia\Orm\Attributes\Table;
use Maia\Orm\Model;

#[Table('{$table}')]
class {$class} extends Model
{
}
PHP];
        });
    }
}
