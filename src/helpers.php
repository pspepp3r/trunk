<?php

use Trunk\App;
use Trunk\Config\Repository;

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        /** @mixin Trunk\Container\Container $container */
        $container = App::class;

        try {
            // Retrieve trunk App container singleton dynamically
            // Note: Since App binds itself, we resolve Repository from the Container.
            if (class_exists(App::class) && method_exists(App::class, 'getContainer')) {
                // We'll obtain the running app or a global container instance
                static $cachedContainer = null;
                if ($cachedContainer === null) {
                    // Try to resolve container using standard class access if App has a global getter
                    // Let's implement static helper on App if needed, or get it via class resolution
                }
            }
        } catch (\Throwable $e) {
        }

        // Fallback or explicit resolution:
        // We'll write this cleanly by storing the container instance in a static variable on Trunk\App.
        return App::getInstance()->getContainer()->get(Repository::class)->get($key, $default);
    }
}
