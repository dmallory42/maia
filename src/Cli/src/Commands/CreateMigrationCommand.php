<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

class CreateMigrationCommand extends BaseCreateCommand
{
    /** @var callable(): string */
    private $clock;

    public function __construct(?string $workspace = null, ?callable $clock = null)
    {
        parent::__construct($workspace);

        $this->clock = $clock ?? static fn (): string => date('Y_m_d_His');
    }

    public function name(): string
    {
        return 'create:migration';
    }

    public function description(): string
    {
        return 'Create a migration file';
    }

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
