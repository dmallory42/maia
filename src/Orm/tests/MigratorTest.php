<?php

declare(strict_types=1);

namespace Maia\Orm\Tests;

use Maia\Orm\Connection;
use Maia\Orm\Migrator;
use Maia\Orm\OrmException;
use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    private Connection $connection;
    private string $migrationDir;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->migrationDir = sys_get_temp_dir() . '/maia_migrations_' . uniqid('', true);
        mkdir($this->migrationDir);

        $this->writeMigration('2026_01_01_000001_create_users.php', $this->createUsersMigration());
        $this->writeMigration('2026_01_01_000002_create_posts.php', $this->createPostsMigration());
    }

    protected function tearDown(): void
    {
        $files = glob($this->migrationDir . '/*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        rmdir($this->migrationDir);
    }

    public function testMigrateRunsPendingMigrationsAndTracksThem(): void
    {
        $migrator = new Migrator($this->connection, $this->migrationDir);

        $ran = $migrator->migrate();

        $this->assertSame(2, $ran);

        $tables = $this->connection->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        $tableNames = array_column($tables, 'name');

        $this->assertContains('users', $tableNames);
        $this->assertContains('posts', $tableNames);

        $rows = $this->connection->query('SELECT migration, batch FROM migrations ORDER BY migration');
        $this->assertCount(2, $rows);
        $this->assertSame(1, (int) $rows[0]['batch']);
    }

    public function testMigrateSkipsAlreadyRunMigrations(): void
    {
        $migrator = new Migrator($this->connection, $this->migrationDir);

        $this->assertSame(2, $migrator->migrate());
        $this->assertSame(0, $migrator->migrate());
    }

    public function testRollbackRollsBackLastBatch(): void
    {
        $migrator = new Migrator($this->connection, $this->migrationDir);
        $migrator->migrate();

        $rolledBack = $migrator->rollback();

        $this->assertSame(2, $rolledBack);

        $tables = $this->connection->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        $tableNames = array_column($tables, 'name');

        $this->assertNotContains('users', $tableNames);
        $this->assertNotContains('posts', $tableNames);

        $rows = $this->connection->query('SELECT migration FROM migrations');
        $this->assertCount(0, $rows);
    }

    public function testMigrateThrowsOrmExceptionWhenFileDoesNotReturnMigration(): void
    {
        $this->writeMigration('2026_01_01_000003_invalid.php', "<?php\n\nreturn 'not-a-migration';\n");

        $migrator = new Migrator($this->connection, $this->migrationDir);

        $this->expectException(OrmException::class);
        $this->expectExceptionMessage('must return a Migration instance');

        $migrator->migrate();
    }

    private function writeMigration(string $filename, string $content): void
    {
        file_put_contents($this->migrationDir . '/' . $filename, $content);
    }

    private function createUsersMigration(): string
    {
        return <<<'MIG'
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
MIG;
    }

    private function createPostsMigration(): string
    {
        return <<<'MIG'
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
            $table->string('title');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('posts');
    }
};
MIG;
    }
}
