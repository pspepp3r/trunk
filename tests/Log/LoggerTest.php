<?php

namespace Trunk\Tests\Log;

use PHPUnit\Framework\TestCase;
use Trunk\Config\Repository;
use Trunk\Log\Logger;
use RuntimeException;

class LoggerTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/trunk_test_logs_' . uniqid() . '/test.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
        if (is_dir(dirname($this->logPath))) {
            rmdir(dirname($this->logPath));
        }
    }

    public function testLoggerCreatesDirectoryAndWritesFile(): void
    {
        $config = new Repository([
            'logging' => [
                'default' => 'single',
                'channels' => [
                    'single' => [
                        'path' => $this->logPath,
                        'level' => 'debug',
                    ],
                ],
            ],
        ]);

        $logger = new Logger($config);
        $logger->info('Test message');

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('trunk.INFO: Test message', $content);
    }
}
