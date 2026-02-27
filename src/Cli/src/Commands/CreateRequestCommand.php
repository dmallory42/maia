<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

class CreateRequestCommand extends BaseCreateCommand
{
    public function name(): string
    {
        return 'create:request';
    }

    public function description(): string
    {
        return 'Create a form request class';
    }

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
