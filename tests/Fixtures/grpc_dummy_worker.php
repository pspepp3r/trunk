<?php
// Test fixture standing in for a real gRPC worker script (see Trunk\Grpc\GrpcClient docblock).
// Deliberately has no dependency on ext-grpc so GrpcClientTest can run without it installed.

$payload = json_decode(base64_decode($argv[1]), true);

if (($payload['mode'] ?? '') === 'fail') {
    fwrite(STDERR, 'simulated worker failure');
    exit(1);
}

echo json_encode(['echo' => $payload]);
