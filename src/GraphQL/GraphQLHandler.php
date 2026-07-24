<?php

namespace Trunk\GraphQL;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Http\Response;

/**
 * Generic GraphQL-over-HTTP endpoint. Executes queries against whatever Schema
 * is bound in the container using graphql-php's ReactPromiseAdapter, so field
 * resolvers can return React\Promise\PromiseInterface (e.g. an ORM Repository
 * query) and the whole request stays non-blocking end to end.
 *
 * The schema itself is app-specific and is not part of the framework core —
 * bind GraphQL\Type\Schema in your own service provider.
 */
class GraphQLHandler
{
    public function __construct(private readonly Schema $schema) {}

    public function handle(ServerRequestInterface $request): PromiseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $query = $body['query'] ?? '';
        $variables = $body['variables'] ?? null;
        $operationName = $body['operationName'] ?? null;

        $adapter = new ReactPromiseAdapter();

        $promise = GraphQL::promiseToExecute(
            $adapter,
            $this->schema,
            $query,
            null,
            null,
            $variables,
            $operationName
        )->then(fn(ExecutionResult $result) => Response::json($result->toArray()));

        return $promise->adoptedPromise;
    }
}
