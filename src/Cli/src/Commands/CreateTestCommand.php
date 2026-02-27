<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateTestCommand defines a framework component for this package.
 */
class CreateTestCommand extends BaseCreateCommand
{
    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:test';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create a PHPUnit test class';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
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
