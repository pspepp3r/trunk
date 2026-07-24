# gRPC Client

Trunk does not - and cannot - run a gRPC **server**. This isn't a Trunk or ReactPHP limitation specifically: the official `grpc/grpc` PHP bindings are client-only by design, since there's no way for userland PHP to get at the raw HTTP/2 streams gRPC needs for a server. If you need to *expose* a gRPC service, write it in another language and have Trunk talk to it as a client.

## Why not just call a gRPC client directly?

PHP's gRPC client bindings (`grpc/grpc` + `google/protobuf`) are a C extension (`ext-grpc`) whose calls **block** the calling process until the response arrives. Calling one directly from inside a request handler would stall Trunk's single event loop for every other concurrent request - exactly what this framework exists to avoid.

## `Trunk\Grpc\GrpcClient`

Instead, `GrpcClient::callAsync()` runs a worker script - which you write using generated `grpc/grpc` + `google/protobuf` stub code - in a separate PHP process (`react/child-process`), and adapts its result into a `PromiseInterface`. The blocking call happens off the event loop, in that isolated process.

```php
use Trunk\Grpc\GrpcClient;

$client = new GrpcClient();

$client->callAsync(__DIR__ . '/workers/get_thing.php', ['id' => 42])
    ->then(function (array $result) {
        // $result is whatever your worker script echoed as JSON
    });
```

### Writing the worker script

```php
<?php
require __DIR__ . '/../../vendor/autoload.php';

// The payload is base64-encoded on the command line - Windows' escapeshellarg()
// strips quote/colon characters, which corrupts raw JSON, so decode first.
$payload = json_decode(base64_decode($argv[1]), true);

$client = new \Your\Generated\ThingServiceClient('localhost:50051', [
    'credentials' => \Grpc\ChannelCredentials::createInsecure(),
]);

$request = new \Your\Generated\GetThingRequest();
$request->setId($payload['id']);

[$response, $status] = $client->GetThing($request)->wait();

if ($status->code !== \Grpc\STATUS_OK) {
    fwrite(STDERR, $status->details);
    exit(1);
}

echo json_encode(['id' => $response->getId(), 'name' => $response->getName()]);
```

A non-zero exit code rejects the returned promise with `Trunk\Grpc\Exception\GrpcCallException`, carrying the worker's stderr output.

### Requirements

`grpc/grpc` and `google/protobuf` (plus the `ext-grpc` PECL extension) are **not** core dependencies - add them to your own app only if you need real gRPC calls. `GrpcClient` itself has no gRPC-specific dependency; it's a generic blocking-call-to-child-process bridge.
