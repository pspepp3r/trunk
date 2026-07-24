# GraphQL

Trunk includes a generic GraphQL-over-HTTP handler built on [`webonyx/graphql-php`](https://github.com/webonyx/graphql-php), using its `ReactPromiseAdapter` - so resolvers return `React\Promise\PromiseInterface` directly (an ORM `Repository` call, for instance) and the whole query executes non-blockingly, just like everything else in Trunk.

The handler itself is app-agnostic; you supply the schema.

## Defining types

```php
namespace App\GraphQL;

use App\Entities\User;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class UserType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'User',
            'fields' => [
                'id' => Type::nonNull(Type::id()),
                'name' => Type::nonNull(Type::string()),
                'email' => Type::nonNull(Type::string()),
            ],
            'resolveField' => fn (User $user, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            },
        ]);
    }
}
```

## Defining queries

Resolvers can return a Repository promise directly:

```php
namespace App\GraphQL;

use App\Entities\User;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Trunk\ORM\EntityManager;

class QueryType extends ObjectType
{
    public function __construct(EntityManager $entityManager)
    {
        $userType = new UserType();

        parent::__construct([
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => $userType,
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn ($root, array $args) =>
                        $entityManager->getRepository(User::class)->find((int) $args['id']),
                ],
                'users' => [
                    'type' => Type::listOf($userType),
                    'resolve' => fn () => $entityManager->getRepository(User::class)->findAll(),
                ],
            ],
        ]);
    }
}
```

## Wiring it up

Bind `GraphQL\Type\Schema` in a service provider:

```php
namespace App\Providers;

use App\GraphQL\QueryType;
use GraphQL\Type\Schema;
use Trunk\ORM\EntityManager;
use Trunk\Providers\ServiceProvider;

class GraphQLSchemaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Schema::class, fn ($c) => new Schema([
            'query' => new QueryType($c->get(EntityManager::class)),
        ]));
    }
}
```

Register the provider in `config/app.php`'s `providers` array, and add the route:

```php
use Trunk\GraphQL\GraphQLHandler;

$app->post('/graphql', [GraphQLHandler::class, 'handle']);
```

## Querying it

```bash
curl -X POST http://127.0.0.1:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"query { user(id: 1) { id name email } }"}'
```

A missing record resolves to `null` per GraphQL convention (`{"data":{"user":null}}`) rather than an HTTP error - your query decides how to represent absence.
