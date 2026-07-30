# Database & Migrations

Trunk's database layer is fully async - every query returns a `React\Promise\PromiseInterface`, whether you're using MySQL or PostgreSQL.

## Configuring a connection

`config/database.php`:

```php
return [
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',

    'connections' => [
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'forge',
            'username' => $_ENV['DB_USERNAME'] ?? 'forge',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],
        'pgsql' => [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? 5432,
            'database' => $_ENV['DB_DATABASE'] ?? 'forge',
            'username' => $_ENV['DB_USERNAME'] ?? 'forge',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],
        'sqlite' => [
            'database' => $_ENV['DB_DATABASE'] ?? ':memory:',
        ],
    ],
];
```

Switch drivers by setting `DB_CONNECTION=pgsql` in `.env` - your migrations, ORM, and raw queries all work unchanged; Trunk compiles SQL per-dialect internally.

::: warning MongoDB isn't supported
There's no maintained non-blocking MongoDB client for ReactPHP. Setting `DB_CONNECTION=mongodb` throws a clear `UnsupportedDriverException` at boot rather than silently blocking the event loop.
:::

::: tip Looking for Redis?
Redis isn't a SQL connection, so it's configured and documented separately - see [Cache](/guide/cache).
:::

::: warning Windows: use an absolute path with backslashes for SQLite
The underlying `clue/reactphp-sqlite` package only recognizes `C:\path\to\file.sqlite` (a literal backslash after the drive letter) as absolute. A `C:/path/to/file.sqlite` path - otherwise valid everywhere else on Windows and PHP - gets silently treated as relative and resolved against the wrong directory instead of erroring. `SqliteDriver` normalizes this for you automatically when `DIRECTORY_SEPARATOR` is `\`, but if you're constructing a path yourself (e.g. via `database_path()`), be aware forward slashes on Windows work everywhere in PHP *except* here.
:::

## Running raw queries

```php
use Trunk\Database\Connection;

$connection->query('SELECT * FROM users WHERE id = ?', [$id])
    ->then(function (\Trunk\Database\QueryResult $result) {
        return $result->rows; // array of associative rows
    });
```

`QueryResult` normalizes both drivers' responses into `rows`, `insertId`, and `affectedRows` - you don't need driver-specific handling.

## Migrations

Generate one with the CLI:

```bash
php trunk make:migration CreateUsersTable
```

This creates `database/migrations/{timestamp}_create_users_table.php` (table name is guessed from `Create{X}Table`):

```php
return new class extends \Trunk\Database\Migration {
    public function up(\Trunk\Database\Schema\SchemaBuilder $schema): \React\Promise\PromiseInterface
    {
        return $schema->create('users', function (\Trunk\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(\Trunk\Database\Schema\SchemaBuilder $schema): \React\Promise\PromiseInterface
    {
        return $schema->drop('users');
    }
};
```

Blueprint column types: `id()`, `string($name, $length = 255)`, `text()`, `integer()`, `bigInteger()`, `boolean()`, `float()`, `dateTime()`, `timestamp()`, `json()`, `timestamps()` (adds nullable `created_at`/`updated_at`). Chain `->nullable()`, `->unique()`, `->default($value)` off any column.

Run and manage migrations:

```bash
php trunk migrate               # run all pending migrations
php trunk migrate:status        # show ran vs. pending
php trunk migrate:rollback      # roll back the last batch
php trunk migrate:rollback --step=3   # roll back the last 3 batches
```

### `orm:schema-diff`

`php trunk orm:schema-diff` scans `src/` for classes carrying a `#[Entity]` attribute, reads the schema it expects from their `#[Column]` and relationship attributes, and compares that against your **live database schema** (introspected via `information_schema`). Whatever's missing gets written to a timestamped migration in `database/migrations/`:

- A `#[Entity]` class with no matching table → `CREATE TABLE IF NOT EXISTS`
- A `#[Column]` with no matching column on an existing table → `ALTER TABLE ... ADD COLUMN`
- A `#[ManyToOne]`/owning-side `#[OneToOne]` with no matching foreign key → `ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY`
- A `#[ManyToMany]` → a pivot table (name is the two table names joined with `_`, sorted alphabetically) with both FK columns and constraints

Run it again with nothing changed and you'll see `No schema changes required.` - it's safe to run repeatedly.

::: warning Additive only, MySQL/PostgreSQL only
This diff never generates `DROP COLUMN` or `DROP TABLE` - if you remove a `#[Column]` or an entity, the live schema is left untouched and you get no migration for it. That's a deliberate safety decision, not a missing feature: edit the generated file (or write one by hand) if you need to remove something.

Schema introspection also only supports the `mysql` and `pgsql` drivers - SQLite has no `information_schema` (it uses `PRAGMA` statements instead, which isn't implemented here), so running this command against a SQLite connection throws an `UnsupportedDriverException`.
:::

Under the hood, both MySQL and PostgreSQL expose `information_schema`, but not identically: Postgres's `key_column_usage` view doesn't carry the referenced table for a foreign key, so introspecting FKs there needs an extra join through `constraint_column_usage` that MySQL's equivalent query doesn't. Reversing a generated foreign key also differs by dialect - Postgres uses `DROP CONSTRAINT` for unique/check/FK constraints alike, while MySQL has a dedicated `DROP FOREIGN KEY` clause. You don't need to think about either of these unless you're reading the generated SQL closely or extending the comparator yourself - both are handled for you.

## The ORM

Entities are plain PHP objects that extend `Trunk\ORM\BaseEntity`. Trunk uses PHP 8 attributes to map these entities to your database tables and columns:

```php
namespace App\Entities;

use Trunk\ORM\BaseEntity;
use Trunk\Database\ORM\Attributes\Entity;
use Trunk\Database\ORM\Attributes\Column;

#[Entity(table: 'users')]
class User extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $name;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $email;

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
}
```

`BaseEntity` gives you `toArray()`, JSON serialization, and array access (`$user['name']`) for free.

Fetch a repository through the `EntityManager` (autowired, no config needed for the default table-name convention - `User` maps to `users`):

```php
$repository = $entityManager->getRepository(User::class);

$repository->find(1);                          // PromiseInterface<?User>
$repository->findAll();                        // PromiseInterface<User[]>
$repository->findBy('author_id', $userId);     // PromiseInterface<Post[]>
$repository->findOneBy('email', $email);       // PromiseInterface<?User>
$repository->persist($user);                   // insert or update, PromiseInterface<User>
$repository->delete($user);                    // PromiseInterface<bool>
```

`persist()` inserts when the entity's primary key is unset and updates otherwise; it also back-fills the auto-generated ID onto the entity after an insert. It skips relationship-typed properties entirely (see below) - it only ever writes plain `#[Column]` properties.

Custom repository classes are picked up by convention: `App\Entities\User` → `App\Repositories\UserRepository` (extend `Trunk\ORM\Repository`), if it exists.

### Relationships

Declare a relationship with `#[ManyToOne]`, `#[OneToOne]`, `#[OneToMany]`, or `#[ManyToMany]` and the `orm:schema-diff` command described above generates the foreign key (or pivot table) for you.

```php
namespace App\Entities;

use Trunk\ORM\BaseEntity;
use Trunk\Database\ORM\Attributes\{Entity, Column, ManyToOne, ManyToMany};

#[Entity(table: 'posts')]
class Post extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $title;

    #[ManyToOne(targetEntity: User::class)]
    private ?User $author = null;

    #[ManyToMany(targetEntity: Tag::class)]
    private array $tags = [];
}
```

```php
namespace App\Entities;

use Trunk\ORM\BaseEntity;
use Trunk\Database\ORM\Attributes\{Entity, Column, OneToMany};

#[Entity(table: 'users')]
class User extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];
}
```

The owning side of a relationship is whichever property carries the target entity's foreign key conceptually - `Post::$author` for the `ManyToOne`, `Post::$tags` for the `ManyToMany`. The inverse side (`User::$posts`, or a `#[ManyToMany(mappedBy: 'tags')]` on `Tag`) only declares `mappedBy` and never gets its own column or pivot table - `orm:schema-diff` skips it to avoid generating a duplicate.

::: warning No self-referential ManyToMany
A pivot table's two foreign key columns are named from the two entities' table names (e.g. `posts` + `tags` → `post_id`/`tag_id`), regardless of which side is "owning." If both sides of a `#[ManyToMany]` point at the *same* entity (e.g. a `User` "follows" other `User`s), both columns would collide on the same name - this isn't supported. Model it as a plain pivot table with your own migration instead.
:::

Relationships aren't lazy-loaded through a magic proxy - you load them explicitly through the `EntityManager`, which returns a promise:

```php
$post = await($repository->find(1));

$author = await($entityManager->loadRelation($post, 'author'));   // ?User
$tags = await($entityManager->loadRelation($post, 'tags'));       // Tag[]

$user = await($userRepository->find(1));
$posts = await($entityManager->loadRelation($user, 'posts'));     // Post[]
```

::: warning Relations aren't cascade-persisted
`Repository::persist()` only ever writes plain `#[Column]` properties - setting `$post->author` to a `User` object and calling `persist($post)` will **not** write `author_id`. To set the foreign key yourself, add a plain `#[Column]` property for it (e.g. `private ?int $authorId = null;`) alongside the relationship property, or write the FK with a raw query. `#[ManyToMany]` pivot rows likewise aren't written by `persist()` - insert/delete them with a raw query against the pivot table.

`loadRelation()` for `#[ManyToMany]` also does one query per related row (fetches matching pivot rows, then `find()`s each related entity) rather than a single `JOIN` - fine for small relation counts, worth knowing about for large ones.
:::
