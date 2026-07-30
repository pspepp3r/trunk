<?php

namespace Trunk\Grpc;

use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Trunk\Grpc\Exception\GrpcCallException;

/**
 * Runs a gRPC worker script in a child process and adapts its result into a Promise -
 * see the gRPC Client guide for the full rationale and a worker script example.
 */
class GrpcClient
{
    public function callAsync(string $workerScriptPath, array $payload): PromiseInterface
    {
        $deferred = new Deferred();
        $encodedPayload = base64_encode(json_encode($payload));
        $command = 'php ' . escapeshellarg($workerScriptPath) . ' ' . escapeshellarg($encodedPayload);

        // Windows-blocking-stdio workaround - see the gRPC Client guide.
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
