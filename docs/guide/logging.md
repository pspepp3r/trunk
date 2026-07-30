# Logging

`Trunk\Log\Logger` is a PSR-3 (`Psr\Log\LoggerInterface`) logger, autowired anywhere you type-hint it - the same instance the framework's own request/response logging middleware uses.

## Configuring channels

`config/logging.php`:

```php
return [
    'default' => $_ENV['LOG_CHANNEL'] ?? 'stack',

    'channels' => [
        'stack' => [
            'driver' => 'single',
            'path' => 'php://stdout',
            'level' => 'debug',
        ],
        'file' => [
            'driver' => 'single',
            'path' => __DIR__ . '/../storage/logs/trunk.log',
            'level' => 'debug',
        ],
    ],
];
```

`logging.default` picks which channel `Logger` writes to; `level` filters what gets written (`debug` is the most permissive - everything at or below that severity is written; set it to `error`, for instance, to silence `info`/`debug`/`notice`/`warning`). Switch to the `file` channel (or add your own) by setting `LOG_CHANNEL=file` in `.env` and making sure `storage/logs/` is writable - `Logger` creates the directory for you if it doesn't exist.

## Writing log entries

Full PSR-3 level set is available:

```php
use Trunk\Log\Logger;

class OrderController
{
    public function __construct(private readonly Logger $logger) {}

    public function create(): void
    {
        $this->logger->info('Order created for user {id}', ['id' => $userId]);
        $this->logger->error('Payment failed: {reason}', ['reason' => $e->getMessage()]);
    }
}
```

`{placeholder}` tokens in the message are replaced with the matching key from the context array (standard PSR-3 message interpolation) - only scalar values and objects with `__toString()` are interpolated, anything else is left as the literal placeholder text.

Each line is formatted as:

```text
[2026-01-15 10:32:04] trunk.INFO: Order created for user 42 {"id":42}
```

- timestamp, `trunk.{LEVEL}`, the interpolated message, then the raw context as JSON (skipped entirely if the context array is empty).

## Where writes go

Both `php://stdout`/`php://stderr` and a real file path are supported. Writing to a real file path uses a plain blocking `file_put_contents(..., FILE_APPEND)` - not `react/filesystem` - since a single `fwrite` to an append-mode file handle is fast enough in practice that it doesn't justify the extra complexity of queuing writes through the event loop; if your app logs at a rate where that becomes a bottleneck, that's a sign to route logs elsewhere (a log shipper reading `stdout`, for instance) rather than to a local file at all.
