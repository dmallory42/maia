<?php

declare(strict_types=1);

namespace Maia\Orm\Tests\Schema;

use Maia\Orm\Connection;
use Maia\Orm\Schema\Schema;
use Maia\Orm\Schema\Table;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    private Connection $connection;
    private Schema $schema;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->schema = new Schema($this->connection);
    }

    public function testCreatesTableWithExpectedColumns(): void
    {
        $this->schema->create('users', function (Table $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->integer('age')->default(18);
            $table->boolean('active')->default(true);
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        $columns = $this->connection->query('PRAGMA table_info(users)');
        $this->assertCount(7, $columns);

        $names = array_column($columns, 'name');
        $this->assertSame(
            ['id', 'email', 'age', 'active', 'bio', 'created_at', 'updated_at'],
            $names
        );

        $email = array_values(array_filter($columns, static fn (array $column): bool => $column['name'] === 'email'))[0];
        $this->assertSame(1, (int) $email['notnull']);

        $bio = array_values(array_filter($columns, static fn (array $column): bool => $column['name'] === 'bio'))[0];
        $this->assertSame(0, (int) $bio['notnull']);
    }

    public function testUniqueConstraintIsCreated(): void
    {
        $this->schema->create('users', function (Table $table): void {
            $table->id();
            $table->string('email')->unique();
        });

        $this->connection->execute('INSERT INTO users (email) VALUES (?)', ['one@example.com']);

        $this->expectException(\PDOException::class);
        $this->connection->execute('INSERT INTO users (email) VALUES (?)', ['one@example.com']);
    }
}
