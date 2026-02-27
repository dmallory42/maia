<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

class CreateTestCommand extends BaseCreateCommand
{
    public function name(): string
    {
        return 'create:test';
    }

    public function description(): string
    {
        return 'Create a PHPUnit test class';
    }

    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'test name');
        if ($name === null) {
            return 1;
        }

        $class = $this->classBasename($name);
        $path = 'tests/' . $class . '.php';

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class {$class} extends TestCase
{
    public function testPlaceholder(): void
    {
        self::assertTrue(true);
    }
}
PHP;

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
