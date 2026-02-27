<?php

declare(strict_types=1);

namespace Maia\Orm\Tests;

use Maia\Orm\Connection;
use Maia\Orm\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1)'
        );

        $this->connection->execute('INSERT INTO users (name, email, active) VALUES (?, ?, ?)', ['Mal', 'mal@example.com', 1]);
        $this->connection->execute('INSERT INTO users (name, email, active) VALUES (?, ?, ?)', ['Alex', 'alex@example.com', 0]);
        $this->connection->execute('INSERT INTO users (name, email, active) VALUES (?, ?, ?)', ['Sam', 'sam@example.com', 1]);
    }

    public function testSelectWhereOrderLimitOffsetGet(): void
    {
        $rows = QueryBuilder::table('users', $this->connection)
            ->select('id', 'name')
            ->where('active', 1)
            ->orderBy('name', 'asc')
            ->limit(1)
            ->offset(1)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Sam', $rows[0]['name']);
        $this->assertArrayNotHasKey('email', $rows[0]);
    }

    public function testFirstReturnsSingleRowOrNull(): void
    {
        $row = QueryBuilder::table('users', $this->connection)
            ->where('email', 'alex@example.com')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Alex', $row['name']);

        $missing = QueryBuilder::table('users', $this->connection)
            ->where('email', 'missing@example.com')
            ->first();

        $this->assertNull($missing);
    }

    public function testCountUsesWhereClauses(): void
    {
        $count = QueryBuilder::table('users', $this->connection)
            ->where('active', 1)
            ->count();

        $this->assertSame(2, $count);
    }

    public function testInsertReturnsLastInsertId(): void
    {
        $id = QueryBuilder::table('users', $this->connection)
            ->insert([
                'name' => 'Taylor',
                'email' => 'taylor@example.com',
                'active' => 1,
            ]);

        $this->assertSame(4, $id);
    }

    public function testUpdateReturnsAffectedRows(): void
    {
        $affected = QueryBuilder::table('users', $this->connection)
            ->where('name', 'Alex')
            ->update(['active' => 1]);

        $this->assertSame(1, $affected);

        $updated = QueryBuilder::table('users', $this->connection)
            ->where('name', 'Alex')
            ->first();

        $this->assertNotNull($updated);
        $this->assertSame(1, (int) $updated['active']);
    }

    public function testDeleteReturnsAffectedRows(): void
    {
        $affected = QueryBuilder::table('users', $this->connection)
            ->where('name', 'Sam')
            ->delete();

        $this->assertSame(1, $affected);

        $count = QueryBuilder::table('users', $this->connection)->count();
        $this->assertSame(2, $count);
    }
}
