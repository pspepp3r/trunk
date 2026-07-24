<?php

namespace Trunk\Console\Command;

class KeyGenerateCommand extends Command
{
    public static function description(): string
    {
        return 'Generate a JWT_SECRET and write it into .env';
    }

    public function handle(array $args): void
    {
        $secret = base64_encode(random_bytes(32));
        $envPath = $this->app->getBasePath() . '/.env';

        if (!file_exists($envPath)) {
            echo "No .env file found. Add this to your environment manually:\n";
            echo "JWT_SECRET={$secret}\n";
            return;
        }

        $contents = file_get_contents($envPath);

        if (preg_match('/^JWT_SECRET=.*$/m', $contents)) {
            $contents = preg_replace('/^JWT_SECRET=.*$/m', "JWT_SECRET={$secret}", $contents);
        } else {
            $contents = rtrim($contents, "\n") . "\nJWT_SECRET={$secret}\n";
        }

        file_put_contents($envPath, $contents);
        echo "New JWT_SECRET written to .env\n";
    }
}
