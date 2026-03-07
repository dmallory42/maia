<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that scaffolds a form request class.
 */
class CreateRequestCommand extends BaseCreateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:request';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create a form request class';
    }

    /**
     * Generate a form request scaffold in app/Requests.
     * @param array $args CLI arguments containing the request class name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        return $this->scaffoldFromName($args, $output, 'request name', function (string $class): array {
            return ['app/Requests/' . $class . '.php', <<<PHP
<?php

declare(strict_types=1);

namespace App\Requests;

use Maia\Auth\FormRequest;

class {$class} extends FormRequest
{
    protected function rules(): array
    {
        return [];
    }
}
PHP];
        });
    }
}
