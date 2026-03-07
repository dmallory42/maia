<?php

declare(strict_types=1);

namespace Maia\Cli\Commands;

use Maia\Cli\Output;

/**
 * CLI command that creates timestamped migration files.
 */
class CreateMigrationCommand extends BaseCreateCommand
{
    /** @var callable(): string */
    private $clock;

    /**
     * Configure the command with an optional workspace and clock override.
     * @param string|null $workspace Project root directory; defaults to cwd.
     * @param callable|null $clock Callback that returns the migration timestamp string.
     * @return void
     */
    public function __construct(?string $workspace = null, ?callable $clock = null)
    {
        parent::__construct($workspace);

        $this->clock = $clock ?? static fn (): string => date('Y_m_d_His');
    }

    /**
     * Return the CLI command name.
     * @return string Command identifier.
     */
    public function name(): string
    {
        return 'create:migration';
    }

    /**
     * Return the help description.
     * @return string Short summary for CLI help.
     */
    public function description(): string
    {
        return 'Create a migration file';
    }

    /**
     * Generate a timestamped migration scaffold in database/migrations.
     * @param array $args CLI arguments containing the migration name.
     * @param Output $output Output writer for status messages.
     * @return int Exit code.
     */
    public function execute(array $args, Output $output): int
    {
        return $this->scaffoldFromName($args, $output, 'migration name', function (string $class): array {
            $slug = $this->snakeCase($class);
            $timestamp = ($this->clock)();
            $filename = $timestamp . '_' . $slug . '.php';

            return ['database/migrations/' . $filename, <<<'PHP'
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
PHP];
        });
    }
}
