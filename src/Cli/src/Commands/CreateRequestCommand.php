<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateRequestCommand defines a framework component for this package.
 */
class CreateRequestCommand extends BaseCreateCommand
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:request';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create a form request class';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'request name');
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        $path = 'app/Requests/' . $class . '.php';

        $content = <<<PHP
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
PHP;

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
