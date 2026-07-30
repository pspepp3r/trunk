<?php

use Trunk\App;
use Trunk\Config\Repository;

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        return App::getInstance()->getContainer()->get(Repository::class)->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Absolute path to the application's root directory, or a path beneath it.
     */
    function base_path(string $path = ''): string
    {
        $basePath = rtrim(App::getInstance()->getBasePath(), '/\\');
        return $path === '' ? $basePath : $basePath . '/' . ltrim($path, '/\\');
    }
}

if (!function_exists('database_path')) {
    /**
     * Absolute path to the application's database/ directory, or a path beneath it.
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}
