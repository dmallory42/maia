<?php

declare(strict_types=1);

namespace Maia\Orm\Tests\Relationships;

use Maia\Orm\Attributes\BelongsTo;
use Maia\Orm\Attributes\HasMany;
use Maia\Orm\Attributes\Table;
use Maia\Orm\Connection;
use Maia\Orm\Model;
use PHPUnit\Framework\TestCase;

#[Table('users')]
class User extends Model
{
    public int $id;
    public string $name;

    #[HasMany(Post::class)]
    private array $posts;

    #[HasMany(AuthorPost::class, foreignKey: 'author_id')]
    private array $authoredPosts;
}

#[Table('posts')]
class Post extends Model
{
    public int $id;
    public int $user_id;
    public string $title;

    #[BelongsTo(User::class)]
    private User $user;
}

#[Table('author_posts')]
class AuthorPost extends Model
{
    public int $id;
    public int $author_id;
    public string $title;

    #[BelongsTo(User::class, foreignKey: 'author_id')]
    private User $author;
}

class RelationshipTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->connection->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');
        $this->connection->execute('CREATE TABLE author_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, author_id INTEGER NOT NULL, title TEXT NOT NULL)');

        $this->connection->execute('INSERT INTO users (name) VALUES (?)', ['Mal']);
        $this->connection->execute('INSERT INTO users (name) VALUES (?)', ['Alex']);

        $this->connection->execute('INSERT INTO posts (user_id, title) VALUES (?, ?)', [1, 'First']);
        $this->connection->execute('INSERT INTO posts (user_id, title) VALUES (?, ?)', [1, 'Second']);
        $this->connection->execute('INSERT INTO posts (user_id, title) VALUES (?, ?)', [2, 'Third']);

        $this->connection->execute('INSERT INTO author_posts (author_id, title) VALUES (?, ?)', [2, 'By Alex']);

        User::setConnection($this->connection);
        Post::setConnection($this->connection);
        AuthorPost::setConnection($this->connection);
    }

    public function testLazyLoadsBelongsTo(): void
    {
        $post = Post::find(1);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertInstanceOf(User::class, $post->user);
        $this->assertSame('Mal', $post->user->name);
    }

    public function testLazyLoadsHasMany(): void
    {
        $user = User::find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertIsArray($user->posts);
        $this->assertCount(2, $user->posts);
        $this->assertContainsOnlyInstancesOf(Post::class, $user->posts);
    }

    public function testEagerLoadsRelationsViaWith(): void
    {
        $users = User::query()->with('posts')->orderBy('id')->get();

        $this->assertCount(2, $users);
        $this->assertCount(2, $users[0]->posts);
        $this->assertCount(1, $users[1]->posts);
    }

    public function testBelongsToInfersConventionalForeignKey(): void
    {
        $post = Post::find(3);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame('Alex', $post->user->name);
    }

    public function testBelongsToSupportsForeignKeyOverride(): void
    {
        $post = AuthorPost::find(1);

        $this->assertInstanceOf(AuthorPost::class, $post);
        $this->assertInstanceOf(User::class, $post->author);
        $this->assertSame('Alex', $post->author->name);
    }

    public function testHasManySupportsForeignKeyOverride(): void
    {
        $user = User::find(2);

        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(1, $user->authoredPosts);
        $this->assertSame('By Alex', $user->authoredPosts[0]->title);
    }
}
