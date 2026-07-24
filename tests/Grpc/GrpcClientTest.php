<?php

namespace Trunk\Tests\Grpc;

use PHPUnit\Framework\TestCase;
use Trunk\Grpc\Exception\GrpcCallException;
use Trunk\Grpc\GrpcClient;

use function React\Async\await;

class GrpcClientTest extends TestCase
{
    private string $worker;

    protected function setUp(): void
    {
        $this->worker = __DIR__ . '/../Fixtures/grpc_dummy_worker.php';
    }

    public function testCallAsyncResolvesWithTheWorkersJsonOutput(): void
    {
        $client = new GrpcClient();

        $result = await($client->callAsync($this->worker, ['id' => 42, 'name' => 'trunk']));

        $this->assertSame(['echo' => ['id' => 42, 'name' => 'trunk']], $result);
    }

    public function testCallAsyncRejectsWithWorkerStderrOnNonZeroExit(): void
    {
        $client = new GrpcClient();

        $this->expectException(GrpcCallException::class);
        $this->expectExceptionMessage('simulated worker failure');

        await($client->callAsync($this->worker, ['mode' => 'fail']));
    }

    public function testPayloadSurvivesBase64EncodingRoundTripIntactOnWindowsShellQuoting(): void
    {
        // Raw JSON containing quotes/colons gets mangled by Windows' escapeshellarg();
        // base64-encoding first is what keeps this working cross-platform.
        $client = new GrpcClient();

        $payload = ['message' => 'has "quotes" and: colons', 'nested' => ['a' => 1]];
        $result = await($client->callAsync($this->worker, $payload));

        $this->assertSame(['echo' => $payload], $result);
    }
}
