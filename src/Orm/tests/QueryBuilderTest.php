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
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                active INTEGER NOT NULL DEFAULT 1
            )'
        );
        $this->connection->execute(
            'CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                views INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->connection->execute(
            'INSERT INTO users (name, email, active) VALUES (?, ?, ?)',
            ['Mal', 'mal@example.com', 1]
        );
        $this->connection->execute(
            'INSERT INTO users (name, email, active) VALUES (?, ?, ?)',
            ['Alex', 'alex@example.com', 0]
        );
        $this->connection->execute(
            'INSERT INTO users (name, email, active) VALUES (?, ?, ?)',
            ['Sam', 'sam@example.com', 1]
        );
        $this->connection->execute(
            'INSERT INTO posts (user_id, title, views) VALUES (?, ?, ?)',
            [1, 'First', 10]
        );
        $this->connection->execute(
            'INSERT INTO posts (user_id, title, views) VALUES (?, ?, ?)',
            [1, 'Second', 20]
        );
        $this->connection->execute(
            'INSERT INTO posts (user_id, title, views) VALUES (?, ?, ?)',
            [2, 'Third', 5]
        );
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

    public function testUpsertInsertsAndUpdatesRecords(): void
    {
        $inserted = QueryBuilder::table('users', $this->connection)->upsert([
            'email' => 'jules@example.com',
            'name' => 'Jules',
            'active' => 1,
        ], ['email']);

        $updated = QueryBuilder::table('users', $this->connection)->upsert([
            'email' => 'alex@example.com',
            'name' => 'Alex Updated',
            'active' => 1,
        ], ['email']);

        $this->assertSame(1, $inserted);
        $this->assertSame(1, $updated);

        $created = QueryBuilder::table('users', $this->connection)
            ->where('email', 'jules@example.com')
            ->first();
        $existing = QueryBuilder::table('users', $this->connection)
            ->where('email', 'alex@example.com')
            ->first();

        $this->assertNotNull($created);
        $this->assertNotNull($existing);
        $this->assertSame('Jules', $created['name']);
        $this->assertSame('Alex Updated', $existing['name']);
        $this->assertSame(1, (int) $existing['active']);
    }

    public function testJoinGroupByAndHavingCanBuildAggregateQueries(): void
    {
        $rows = QueryBuilder::table('users', $this->connection)
            ->select('users.name', 'COUNT(posts.id) AS post_count', 'SUM(posts.views) AS total_views')
            ->join('posts', 'posts.user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.name')
            ->having('COUNT(posts.id)', 1, '>')
            ->orderBy('total_views', 'desc')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Mal', $rows[0]['name']);
        $this->assertSame(2, (int) $rows[0]['post_count']);
        $this->assertSame(30, (int) $rows[0]['total_views']);
    }

    public function testLeftJoinAndHavingRawSupportCustomReportingQueries(): void
    {
        $rows = QueryBuilder::table('users', $this->connection)
            ->select('users.name', 'COUNT(posts.id) AS post_count')
            ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.name')
            ->havingRaw('COUNT(posts.id) >= ?', [0])
            ->orderBy('users.id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame('Mal', $rows[0]['name']);
        $this->assertSame('Alex', $rows[1]['name']);
        $this->assertSame('Sam', $rows[2]['name']);
        $this->assertSame(0, (int) $rows[2]['post_count']);
    }
}
