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
    ],
];
```

Switch drivers by setting `DB_CONNECTION=pgsql` in `.env` - your migrations, ORM, and raw queries all work unchanged; Trunk compiles SQL per-dialect internally.

::: warning MongoDB isn't supported
There's no maintained non-blocking MongoDB client for ReactPHP. Setting `DB_CONNECTION=mongodb` throws a clear `UnsupportedDriverException` at boot rather than silently blocking the event loop.
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

`php trunk orm:schema-diff` scans `src/Entities/` for `#[Entity]` attributes and compares them against your current database schema. It then generates a timestamped migration file in `database/migrations/` containing the difference (e.g. `CREATE TABLE` and `ALTER TABLE` statements). This offers a seamless way to evolve your database schema without writing migrations by hand.

## The ORM

Entities are plain PHP objects that implement `Trunk\ORM\Interface\EntityInterface`. Trunk uses PHP 8 attributes to map these entities to your database tables and columns:

```php
namespace App\Entities;

use Trunk\ORM\Interface\EntityInterface;
use Trunk\Database\ORM\Attributes\Entity;
use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\OneToMany;

#[Entity(table: 'users')]
class User implements EntityInterface
{
    #[Column(primary: true)]
    private ?int $id = null;
    
    #[Column(type: 'VARCHAR', length: 255)]
    private string $name;
    
    #[Column(type: 'VARCHAR', length: 255, unique: true)]
    private string $email;
    
    #[OneToMany(targetEntity: Post::class, mappedBy: 'user')]
    private array $posts = [];

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
}
```

Trunk supports all typical relationship attributes: `#[OneToOne]`, `#[OneToMany]`, `#[ManyToOne]`, and `#[ManyToMany]`. When executing `orm:schema-diff`, the framework will read these attributes and automatically map foreign keys and pivot tables.

Fetch a repository through the `EntityManager` (autowired, no config needed for the default table-name convention - `User` maps to `users`):

```php
$repository = $entityManager->getRepository(User::class);

$repository->find(1);                 // PromiseInterface<?User>
$repository->findAll();               // PromiseInterface<User[]>
$repository->persist($user);          // insert or update, PromiseInterface<User>
$repository->delete($user);           // PromiseInterface<bool>
```

`persist()` inserts when the entity's primary key is unset and updates otherwise; it also back-fills the auto-generated ID onto the entity after an insert.

Custom repository classes are picked up by convention: `App\Entities\User` → `App\Repositories\UserRepository` (extend `Trunk\ORM\Repository`), if it exists.
