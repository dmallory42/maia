<?php

declare(strict_types=1);

namespace Maia\Orm\Tests;

use Maia\Orm\Attributes\Table;
use Maia\Orm\Connection;
use Maia\Orm\Model;
use Maia\Orm\QueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[Table('users')]
class User extends Model
{
    public int $id;
    public string $name;
    public string $email;
    public int $active;
}

class ModelTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1)'
        );
        $this->connection->execute('INSERT INTO users (name, email, active) VALUES (?, ?, ?)', ['Mal', 'mal@example.com', 1]);

        User::setConnection($this->connection);
    }

    public function testFindReturnsModelInstance(): void
    {
        $user = User::find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, $user->id);
        $this->assertSame('Mal', $user->name);
    }

    public function testQueryReturnsScopedQueryBuilder(): void
    {
        $query = User::query();

        $this->assertInstanceOf(QueryBuilder::class, $query);

        $result = $query->where('email', 'mal@example.com')->first();
        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('Mal', $result->name);
    }

    public function testCreateInsertsAndReturnsModel(): void
    {
        $user = User::create([
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'active' => 1,
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(2, $user->id);
        $this->assertSame('Alex', $user->name);
    }

    public function testSaveUpdatesExistingRecord(): void
    {
        $user = User::find(1);
        $this->assertInstanceOf(User::class, $user);

        $user->name = 'Mal Updated';
        $result = $user->save();

        $this->assertTrue($result);

        $refetched = User::find(1);
        $this->assertInstanceOf(User::class, $refetched);
        $this->assertSame('Mal Updated', $refetched->name);
    }

    public function testWhereGetReturnsModelInstances(): void
    {
        User::create([
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'active' => 0,
        ]);

        $users = User::query()->where('active', 1)->get();

        $this->assertNotEmpty($users);
        $this->assertContainsOnlyInstancesOf(User::class, $users);
    }

    public function testReflectionMetadataIsCachedPerModelClass(): void
    {
        $method = new ReflectionMethod(Model::class, 'reflectionFor');
        $method->setAccessible(true);

        $first = $method->invoke(null, User::class);
        $second = $method->invoke(null, User::class);

        $this->assertSame($first, $second);
    }
}
