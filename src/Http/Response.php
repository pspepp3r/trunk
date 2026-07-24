<?php

namespace Trunk\Http;

use React\Http\Message\Response as ReactResponse;

class Response
{
    public static function json(array|object $data, int $status = 200, array $headers = []): ReactResponse
    {
        $headers = ['Content-Type' => 'application/json', ...$headers];

        return new ReactResponse(
            $status,
            $headers,
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function text(string $text, int $status = 200, array $headers = []): ReactResponse
    {
        $headers = ['Content-Type' => 'text/plain; charset=utf-8', ...$headers];

        return new ReactResponse($status, $headers, $text);
    }

    public static function empty(int $status = 204, array $headers = []): ReactResponse
    {
        return new ReactResponse($status, $headers, '');
    }
}
