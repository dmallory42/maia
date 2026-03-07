<?php

declare(strict_types=1);

namespace Maia\Cli\Tests\Commands;

use Maia\Cli\Commands\MigrateCommand;
use Maia\Cli\Commands\MigrateRollbackCommand;
use Maia\Cli\Commands\MigrateStatusCommand;
use Maia\Cli\Output;
use Maia\Orm\Connection;
use PHPUnit\Framework\TestCase;

class MigrateCommandsTest extends TestCase
{
    private Connection $connection;
    private string $migrationDir;
    private string $previousCwd;

    protected function setUp(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);
        $this->previousCwd = $cwd;

        $this->connection = new Connection('sqlite::memory:');
        $this->migrationDir = sys_get_temp_dir() . '/maia_cli_migrations_' . uniqid('', true);
        mkdir($this->migrationDir);

        file_put_contents($this->migrationDir . '/2026_01_01_000001_create_users.php', $this->usersMigration());
        file_put_contents($this->migrationDir . '/2026_01_01_000002_create_posts.php', $this->postsMigration());
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);

        $files = glob($this->migrationDir . '/*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        rmdir($this->migrationDir);
    }

    public function testMigrateRunsPendingMigrations(): void
    {
        $command = new MigrateCommand($this->connection, $this->migrationDir);
        $output = new Output();

        $code = $command->execute([], $output);

        $this->assertSame(0, $code);

        $tables = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_column($tables, 'name');

        $this->assertContains('users', $tableNames);
        $this->assertContains('posts', $tableNames);
    }

    public function testMigrateRollbackRollsBackLastBatch(): void
    {
        $migrate = new MigrateCommand($this->connection, $this->migrationDir);
        $migrate->execute([], new Output());

        $rollback = new MigrateRollbackCommand($this->connection, $this->migrationDir);
        $code = $rollback->execute([], new Output());

        $this->assertSame(0, $code);

        $tables = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_column($tables, 'name');

        $this->assertNotContains('users', $tableNames);
        $this->assertNotContains('posts', $tableNames);
    }

    public function testMigrateStatusShowsRunAndPendingWithJsonOutput(): void
    {
        $migrate = new MigrateCommand($this->connection, $this->migrationDir);
        $migrate->execute([], new Output());

        file_put_contents($this->migrationDir . '/2026_01_01_000003_create_comments.php', $this->commentsMigration());

        $status = new MigrateStatusCommand($this->connection, $this->migrationDir);
        $output = new Output(true);

        $code = $status->execute([], $output);
        $this->assertSame(0, $code);

        $payload = json_decode(trim($output->buffer()), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('migrations', $payload);
        $this->assertCount(3, $payload['migrations']);

        $states = array_column($payload['migrations'], 'ran', 'migration');
        $this->assertTrue($states['2026_01_01_000001_create_users.php']);
        $this->assertFalse($states['2026_01_01_000003_create_comments.php']);
    }

    public function testMigrationCommandsUseDefaultConnectionAndMigrationPaths(): void
    {
        $workspace = sys_get_temp_dir() . '/maia_cli_workspace_' . uniqid('', true);
        mkdir($workspace);
        mkdir($workspace . '/database');
        mkdir($workspace . '/database/migrations', 0777, true);

        file_put_contents(
            $workspace . '/database/migrations/2026_01_01_000001_create_users.php',
            $this->usersMigration()
        );

        chdir($workspace);

        $migrate = new MigrateCommand();
        $rollback = new MigrateRollbackCommand();
        $status = new MigrateStatusCommand();

        $this->assertSame(0, $migrate->execute([], new Output()));

        $connection = new Connection('sqlite:' . $workspace . '/database/database.sqlite');
        $tables = $connection->query("SELECT name FROM sqlite_master WHERE type='table'");
        $this->assertContains('users', array_column($tables, 'name'));

        $statusOutput = new Output(true);
        $this->assertSame(0, $status->execute([], $statusOutput));
        $payload = json_decode(trim($statusOutput->buffer()), true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['migrations'][0]['ran']);

        $this->assertSame(0, $rollback->execute([], new Output()));

        $tablesAfterRollback = $connection->query("SELECT name FROM sqlite_master WHERE type='table'");
        $this->assertNotContains('users', array_column($tablesAfterRollback, 'name'));

        unlink($workspace . '/database/database.sqlite');
        unlink($workspace . '/database/migrations/2026_01_01_000001_create_users.php');
        rmdir($workspace . '/database/migrations');
        rmdir($workspace . '/database');
        rmdir($workspace);
    }

    private function usersMigration(): string
    {
        return <<<'PHP'
<?php

use Maia\Orm\Migration;
use Maia\Orm\Schema\Schema;
use Maia\Orm\Schema\Table;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Table $table): void {
            $table->id();
            $table->string('name');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('users');
    }
};
PHP;
    }

    private function postsMigration(): string
    {
        return <<<'PHP'
<?php

use Maia\Orm\Migration;
use Maia\Orm\Schema\Schema;
use Maia\Orm\Schema\Table;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('posts', function (Table $table): void {
            $table->id();
            $table->integer('user_id');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('posts');
    }
};
PHP;
    }

    private function commentsMigration(): string
    {
        return <<<'PHP'
<?php

use Maia\Orm\Migration;
use Maia\Orm\Schema\Schema;
use Maia\Orm\Schema\Table;

return new class extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('comments', function (Table $table): void {
            $table->id();
            $table->integer('post_id');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('comments');
    }
};
PHP;
    }
}
