<?php

namespace Trunk\Tests\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\ServerRequest;
use Trunk\Container\Container;
use Trunk\Http\Response;
use Trunk\Http\Router;
use Trunk\ORM\EntityManager;
use Trunk\ORM\Repository;
use Trunk\Tests\Fixtures\AddHeaderMiddleware;
use Trunk\Tests\Fixtures\CreateThingRequest;
use Trunk\Tests\Fixtures\FakeEntity;
use Trunk\Tests\Fixtures\NonEntityService;

use function React\Async\await;
use function React\Promise\resolve;

class RouterTest extends TestCase
{
    private function jsonBody(mixed $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    public function testDispatchesMatchingRouteToClosureHandler(): void
    {
        $router = new Router(new Container());
        $router->get('/ping', function (ServerRequestInterface $request) {
            return Response::json(['pong' => true]);
        });

        $response = await($router->dispatch(new ServerRequest('GET', '/ping')));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['pong' => true], $this->jsonBody($response));
    }

    public function testReturns404ForUnmatchedRoute(): void
    {
        $router = new Router(new Container());
        $router->get('/ping', fn () => Response::json(['pong' => true]));

        $response = await($router->dispatch(new ServerRequest('GET', '/nowhere')));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $this->jsonBody($response)['error']);
    }

    public function testPathParametersArePassedPositionallyToTheHandler(): void
    {
        $router = new Router(new Container());
        $router->get('/items/{id}', function (ServerRequestInterface $request, string $id) {
            return Response::json(['id' => $id]);
        });

        $response = await($router->dispatch(new ServerRequest('GET', '/items/42')));

        $this->assertSame(['id' => '42'], $this->jsonBody($response));
    }

    public function testFormRequestValidationFailureReturns422AndNeverCallsTheHandler(): void
    {
        $router = new Router(new Container());
        $handlerCalled = false;

        $router->post('/things', function (CreateThingRequest $request) use (&$handlerCalled) {
            $handlerCalled = true;
            return Response::json(['ok' => true]);
        });

        $request = (new ServerRequest('POST', '/things'))->withParsedBody([]);
        $response = await($router->dispatch($request));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('name', $this->jsonBody($response)['errors']);
        $this->assertFalse($handlerCalled);
    }

    public function testFormRequestValidDataIsPassedToHandlerViaValidated(): void
    {
        $router = new Router(new Container());

        $router->post('/things', function (CreateThingRequest $request) {
            return Response::json(['name' => $request->validated()['name']]);
        });

        $request = (new ServerRequest('POST', '/things'))->withParsedBody(['name' => 'Widget']);
        $response = await($router->dispatch($request));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['name' => 'Widget'], $this->jsonBody($response));
    }

    public function testRouteModelBindingResolvesEntityAndPassesInstanceToHandler(): void
    {
        $entity = new FakeEntity(1);
        $repository = $this->createMock(Repository::class);
        $repository->method('find')->with('1')->willReturn(resolve($entity));

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getRepository')->with(FakeEntity::class)->willReturn($repository);

        $container = new Container();
        $container->singleton(EntityManager::class, $entityManager);

        $router = new Router($container);
        $router->get('/entities/{entity}', function (ServerRequestInterface $request, FakeEntity $entity) {
            return Response::json(['id' => $entity->id]);
        });

        $response = await($router->dispatch(new ServerRequest('GET', '/entities/1')));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 1], $this->jsonBody($response));
    }

    public function testRouteModelBindingReturns404WhenEntityNotFound(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('find')->willReturn(resolve(null));

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $container = new Container();
        $container->singleton(EntityManager::class, $entityManager);

        $router = new Router($container);
        $router->get('/entities/{entity}', function (ServerRequestInterface $request, FakeEntity $entity) {
            return Response::json(['id' => $entity->id]);
        });

        $response = await($router->dispatch(new ServerRequest('GET', '/entities/999')));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testNonEntityClassTypedParameterIsNeverResolvedViaEntityManager(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects($this->never())->method('getRepository');

        $container = new Container();
        $container->singleton(EntityManager::class, $entityManager);

        $router = new Router($container);
        $router->get('/services/{service}', function (ServerRequestInterface $request, NonEntityService $service) {
            return Response::json(['ok' => true]);
        });

        // The guard correctly declines to bind a non-Entity class - but the framework has no
        // other mechanism for arbitrary class-typed route params either, so the raw string
        // segment gets passed where NonEntityService is expected, which is a TypeError. That's
        // fine here: the point of this test is the mock's never() expectation above.
        $response = await($router->dispatch(new ServerRequest('GET', '/services/abc')));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testRouteSpecificMiddlewareOnlyAppliesToItsOwnRoute(): void
    {
        $router = new Router(new Container());
        $router->get('/protected', fn () => Response::json(['ok' => true]), [AddHeaderMiddleware::class]);
        $router->get('/plain', fn () => Response::json(['ok' => true]));

        $protectedResponse = await($router->dispatch(new ServerRequest('GET', '/protected')));
        $plainResponse = await($router->dispatch(new ServerRequest('GET', '/plain')));

        $this->assertSame('applied', $protectedResponse->getHeaderLine('X-Test-Middleware'));
        $this->assertSame('', $plainResponse->getHeaderLine('X-Test-Middleware'));
    }
}
