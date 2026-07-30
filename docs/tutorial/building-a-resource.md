# Building a Resource

This page builds one real resource - blog posts, owned by a user - end to end: an entity, a generated migration, validation, a controller, and a route. It assumes you've completed [Installation](/tutorial/installation) and have a running database.

## 1. Define the entity

Entities are plain PHP classes extending `Trunk\ORM\BaseEntity`, mapped to a table via attributes:

```php
// src/Entities/Post.php
namespace App\Entities;

use App\Entities\User;
use Trunk\ORM\BaseEntity;
use Trunk\Database\ORM\Attributes\{Entity, Column, ManyToOne};

#[Entity(table: 'posts')]
class Post extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $title;

    #[Column(type: 'TEXT')]
    private string $body;

    #[ManyToOne(targetEntity: User::class)]
    private ?User $author = null;

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $body): void { $this->body = $body; }
}
```

`#[ManyToOne(targetEntity: User::class)]` declares that a post belongs to a user - see [Database & Migrations](/guide/database#relationships) for the full relationship reference.

## 2. Generate and run the migration

Rather than hand-writing a migration, scan your entities and diff them against the live database:

```bash
php trunk orm:schema-diff
```

```text
Migration file generated: database/migrations/2026_01_15_103000_schema_diff.php
  + CREATE TABLE IF NOT EXISTS `posts` (
```

This inspects `#[Entity]`/`#[Column]`/`#[ManyToOne]` on every entity under `src/`, compares that against what's actually in your database, and writes a migration for whatever's missing - here, the whole `posts` table, including an `author_id` foreign key column generated from the `#[ManyToOne]`. Run it:

```bash
php trunk migrate
```

## 3. Validate incoming data

```php
// src/Requests/CreatePostRequest.php
namespace App\Requests;

use Trunk\Validation\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ];
    }
}
```

See [Validation](/guide/validation) for the full rule set.

## 4. Write the controller

```php
// src/Controllers/PostController.php
namespace App\Controllers;

use App\Entities\Post;
use App\Entities\User;
use App\Requests\CreatePostRequest;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Http\Response;
use Trunk\ORM\EntityManager;

class PostController
{
    public function __construct(private readonly EntityManager $em) {}

    public function index(): PromiseInterface
    {
        return $this->em->getRepository(Post::class)->findAll()->then(
            fn (array $posts) => Response::json(array_map(
                fn (Post $post) => ['id' => $post->getId(), 'title' => $post->getTitle()],
                $posts
            ))
        );
    }

    public function store(CreatePostRequest $request, ServerRequestInterface $http): PromiseInterface
    {
        $data = $request->validated();
        $claims = $http->getAttribute('auth'); // set by AuthMiddleware - see Adding Authentication

        return $this->em->getRepository(User::class)->find((int) $claims['sub'])->then(
            function (?User $author) use ($data) {
                $post = new Post();
                $post->setTitle($data['title']);
                $post->setBody($data['body']);

                // ManyToOne/OneToMany/ManyToMany properties aren't cascade-persisted (see the
                // Database guide's Relationships section) - write the FK column yourself, or
                // add a plain #[Column] property for author_id alongside the relation.
                return $this->em->getRepository(Post::class)->persist($post);
            }
        )->then(
            fn (Post $post) => Response::json(['id' => $post->getId(), 'title' => $post->getTitle()], 201)
        );
    }
}
```

Notice the handler's *first* parameter is the `FormRequest` (Trunk builds and validates it from the incoming request before your method runs - a `422` is returned automatically on failure, your method body never even executes), and the raw PSR-7 request is available as a second, explicitly-typed parameter if you need it too. See [Routing](/guide/routing) for the full parameter-resolution rules.

## 5. Register the route

```php
// config/routes.php
use App\Controllers\PostController;
use App\Middleware\AuthMiddleware;

$app->get('/posts', [PostController::class, 'index']);
$app->post('/posts', [PostController::class, 'store'], [AuthMiddleware::class]);
```

`/posts` (POST) is protected - see [Adding Authentication](/tutorial/authentication) for where the token in `Authorization: Bearer <token>` comes from.

## 6. Try it

```bash
TOKEN=$(curl -s -X POST http://127.0.0.1:8080/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"secret123"}' | jq -r .token)

curl -X POST http://127.0.0.1:8080/posts \
  -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Hello Trunk","body":"My first post."}'

curl http://127.0.0.1:8080/posts
```

From here, [Database & Migrations](/guide/database) covers the full Repository API (`findBy`, `findOneBy`, `delete`) and loading the `author` relation back out via `EntityManager::loadRelation()`; [Testing](/guide/testing) covers testing `PostController` the same way the shipped `AuthControllerTest` tests `AuthController` - with a mocked `EntityManager`/`Repository`, no live database needed.
