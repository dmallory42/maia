<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that scaffolds a PHPUnit test class.
 */
class CreateTestCommand extends BaseCreateCommand
{
    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:test';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create a PHPUnit test class';
    }

    /**
     * Generate a test scaffold in tests/.
     * @param array $args CLI arguments containing the test class name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        return $this->scaffoldFromName($args, $output, 'test name', function (string $class): array {
            return ['tests/' . $class . '.php', <<<PHP
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
PHP];
        });
    }
}
