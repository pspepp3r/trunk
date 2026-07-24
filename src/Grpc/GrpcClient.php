<?php

namespace Trunk\Grpc;

use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Trunk\Grpc\Exception\GrpcCallException;

/**
 * PHP has no async gRPC client: the official grpc/grpc bindings are a C extension
 * (ext-grpc) whose calls block the calling process until the response arrives, and
 * PHP cannot itself act as a gRPC server (the official stance is client-only, since
 * there's no way for userland PHP — ReactPHP included — to get at the raw HTTP/2
 * streams gRPC needs). Calling a blocking gRPC stub directly from a request handler
 * would stall this framework's single event loop for every concurrent request.
 *
 * This class does not implement gRPC itself. It runs a worker script — which you
 * write using grpc/grpc + google/protobuf generated stub code — in a separate PHP
 * process via react/child-process, and adapts its result into a Promise. The actual
 * blocking call happens in that isolated process, off the event loop.
 *
 * The payload is passed base64-encoded on the command line — Windows' escapeshellarg()
 * strips quote and colon characters, which corrupts raw JSON, so the worker script must
 * base64_decode($argv[1]) before json_decode-ing it.
 *
 * Example worker script (grpc/grpc + google/protobuf required in your app, not core):
 *
 *   <?php
 *   require __DIR__ . '/../vendor/autoload.php';
 *
 *   $payload = json_decode(base64_decode($argv[1]), true);
 *   $client = new \Your\Generated\ThingServiceClient('localhost:50051', [
 *       'credentials' => \Grpc\ChannelCredentials::createInsecure(),
 *   ]);
 *
 *   $request = new \Your\Generated\GetThingRequest();
 *   $request->setId($payload['id']);
 *
 *   [$response, $status] = $client->GetThing($request)->wait();
 *   if ($status->code !== \Grpc\STATUS_OK) {
 *       fwrite(STDERR, $status->details);
 *       exit(1);
 *   }
 *
 *   echo json_encode(['id' => $response->getId(), 'name' => $response->getName()]);
 */
class GrpcClient
{
    public function callAsync(string $workerScriptPath, array $payload): PromiseInterface
    {
        $deferred = new Deferred();
        $encodedPayload = base64_encode(json_encode($payload));
        $command = 'php ' . escapeshellarg($workerScriptPath) . ' ' . escapeshellarg($encodedPayload);

        // Socket-pair descriptors support non-blocking I/O on every platform, including
        // Windows, where the default pipe-based stdio is blocking and unsupported.
        $process = new Process($command, null, null, [
            ['socket'],
            ['socket'],
            ['socket'],
        ]);
        $process->start(Loop::get());

        $stdout = '';
        $stderr = '';

        $process->stdout->on('data', function (string $chunk) use (&$stdout) {
            $stdout .= $chunk;
        });

        $process->stderr->on('data', function (string $chunk) use (&$stderr) {
            $stderr .= $chunk;
        });

        $process->on('exit', function (?int $exitCode) use ($deferred, &$stdout, &$stderr) {
            if ($exitCode !== 0) {
                $deferred->reject(new GrpcCallException(
                    $stderr !== '' ? $stderr : "gRPC worker exited with code {$exitCode}"
                ));
                return;
            }

            $decoded = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $deferred->reject(new GrpcCallException("gRPC worker returned invalid JSON: {$stdout}"));
                return;
            }

            $deferred->resolve($decoded);
        });

        return $deferred->promise();
    }
}
