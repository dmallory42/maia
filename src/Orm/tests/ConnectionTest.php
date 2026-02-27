<?php

declare(strict_types=1);

namespace Maia\Orm\Tests;

use Maia\Orm\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)'
        );
    }

    public function testCreatesConnectionAndExecutesParameterizedInsert(): void
    {
        $affected = $this->connection->execute(
            'INSERT INTO users (name, email) VALUES (:name, :email)',
            [
                'name' => 'Mal',
                'email' => 'mal@example.com',
            ]
        );

        $this->assertSame(1, $affected);
    }

    public function testFetchesRowsAsAssociativeArrays(): void
    {
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Mal', 'mal@example.com']);

        $rows = $this->connection->query('SELECT * FROM users');

        $this->assertCount(1, $rows);
        $this->assertSame('Mal', $rows[0]['name']);
        $this->assertSame('mal@example.com', $rows[0]['email']);
    }

    public function testHandlesParameterizedSelectQueries(): void
    {
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Mal', 'mal@example.com']);
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Alex', 'alex@example.com']);

        $rows = $this->connection->query('SELECT * FROM users WHERE name = :name', ['name' => 'Alex']);

        $this->assertCount(1, $rows);
        $this->assertSame('Alex', $rows[0]['name']);
    }

    public function testReturnsLastInsertId(): void
    {
        $this->connection->execute('INSERT INTO users (name, email) VALUES (?, ?)', ['Mal', 'mal@example.com']);

        $this->assertSame('1', $this->connection->lastInsertId());
    }
}
