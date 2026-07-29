<?php

namespace Trunk\Console\Command;

class StartCommand extends Command
{
    public static function description(): string
    {
        return 'Start the ReactPHP async API server';
    }

    public function handle(array $args): void
    {
        $port = config('app.port', '8080');
        
        $watch = in_array('--watch', $args, true) || in_array('-w', $args, true);
        
        if (!$watch) {
            $this->app->run("127.0.0.1:{$port}");
            return;
        }

        echo "Starting server in watch mode on 127.0.0.1:{$port}...\n";
        $this->startWatchProcess($port);
    }

    private function startWatchProcess(string $port): void
    {
        $loop = \React\EventLoop\Loop::get();
        $process = $this->spawnProcess($port);
        $lastMtime = $this->getLastMtime();

        $loop->addPeriodicTimer(1.0, function () use (&$process, &$lastMtime, $port) {
            $currentMtime = $this->getLastMtime();
            if ($currentMtime > $lastMtime) {
                echo "\n[Watcher] File change detected, restarting server...\n";
                $lastMtime = $currentMtime;
                $process->terminate();
                $process = $this->spawnProcess($port);
            }
        });

        $loop->run();
    }

    private function spawnProcess(string $port): \React\ChildProcess\Process
    {
        // argv[0] is usually the trunk executable (e.g. 'trunk' or 'bin/trunk')
        $executable = $_SERVER['argv'][0] ?? 'trunk';
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($executable) . ' start';
        
        $process = new \React\ChildProcess\Process($cmd);
        $process->start();

        $process->stdout->on('data', function ($chunk) {
            echo $chunk;
        });
        
        $process->stderr->on('data', function ($chunk) {
            echo $chunk;
        });

        return $process;
    }

    private function getLastMtime(): int
    {
        $maxMtime = 0;
        // Watch common directories
        $directories = [getcwd() . '/src', getcwd() . '/config', getcwd() . '/bootstrap'];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $mtime = $file->getMTime();
                    if ($mtime > $maxMtime) {
                        $maxMtime = $mtime;
                    }
                }
            }
        }

        return $maxMtime;
    }
}
