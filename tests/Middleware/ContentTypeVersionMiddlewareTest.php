<?php

namespace Trunk\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;
use Trunk\App;
use Trunk\Middleware\ContentTypeVersionMiddleware;

use function React\Async\await;
use function React\Promise\resolve;

class ContentTypeVersionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // config()'s fallback path (no matching version header) resolves through
        // App::getInstance() - constructing one (without configure()) is enough to
        // give it an empty-but-real Config\Repository to read defaults from.
        new App();
    }

    private function versionSeenByNext(string $header, string $headerName = 'Accept'): ?string
    {
        $middleware = new ContentTypeVersionMiddleware();
        $request = new ServerRequest('GET', '/', [$headerName => $header]);

        $seen = null;
        await($middleware->process($request, function ($req) use (&$seen) {
            $seen = $req->getAttribute('api_version');
            return resolve('ok');
        }));

        return $seen;
    }

    public function testParsesVersionEmbeddedInVendorMediaType(): void
    {
        $this->assertSame('2', $this->versionSeenByNext('application/vnd.trunk.v2+json'));
    }

    public function testParsesVersionAsMediaTypeParameter(): void
    {
        $this->assertSame('3', $this->versionSeenByNext('application/vnd.trunk+json;version=3'));
    }

    public function testFallsBackToContentTypeWhenAcceptIsAbsent(): void
    {
        $middleware = new ContentTypeVersionMiddleware();
        $request = new ServerRequest('GET', '/', ['Content-Type' => 'application/vnd.trunk.v5+json']);

        $seen = null;
        await($middleware->process($request, function ($req) use (&$seen) {
            $seen = $req->getAttribute('api_version');
            return resolve('ok');
        }));

        $this->assertSame('5', $seen);
    }

    public function testDefaultsToConfiguredVersionWhenHeaderDoesNotMatch(): void
    {
        $this->assertSame('1', $this->versionSeenByNext('application/json'));
    }
}
