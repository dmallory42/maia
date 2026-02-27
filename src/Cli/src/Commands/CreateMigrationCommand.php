<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CreateMigrationCommand defines a framework component for this package.
 */
class CreateMigrationCommand extends BaseCreateCommand
{
    /** @var callable(): string */
    private $clock;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string|null $workspace Input value.
     * @param callable|null $clock Input value.
     * @return void Output value.
     */
    public function __construct(?string $workspace = null, ?callable $clock = null)
    {
        parent::__construct($workspace);

        $this->clock = $clock ?? static fn (): string => date('Y_m_d_His');
    }

    /**
     * Name and return string.
     * @return string Output value.
     */
    public function name(): string
    {
        return 'create:migration';
    }

    /**
     * Description and return string.
     * @return string Output value.
     */
    public function description(): string
    {
        return 'Create a migration file';
    }

    /**
     * Execute and return int.
     * @param array $args Input value.
     * @param Output $output Input value.
     * @return int Output value.
     */
    public function execute(array $args, Output $output): int
    {
        $name = $this->requireName($args, $output, 'migration name');
        if ($name === null) {
            return 1;
        }

        $slug = $this->snakeCase($this->classBasename($name));
        $timestamp = ($this->clock)();
        $filename = $timestamp . '_' . $slug . '.php';
        $path = 'database/migrations/' . $filename;

        $content = <<<'PHP'
<?php

declare(strict_types=1);

use Maia\Orm\Migration;
use Maia\Orm\Schema\Schema;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        // Apply schema changes.
    }

    public function down(Schema $schema): void
    {
        // Revert schema changes.
    }
};
PHP;

        $this->writeFile($path, $content);
        $output->line('Created ' . $path);

        return 0;
    }
}
