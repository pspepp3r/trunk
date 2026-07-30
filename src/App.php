<?php

namespace Trunk;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Trunk\Container\Container;
use Trunk\Http\Router;
use Trunk\Middleware\Pipeline;

class App
{
    private static ?App $instance = null;
    private Container $container;
    private Router $router;
    private Pipeline $pipeline;
    private array $serviceProviders = [];
    private array $loadedProviders = [];
    private string $basePath = '';

    private array $defaultProviders = [
        \Trunk\Providers\LogServiceProvider::class,
        \Trunk\Providers\DatabaseServiceProvider::class,
        \Trunk\Providers\CacheServiceProvider::class,
        \Trunk\Providers\SessionServiceProvider::class,
        \Trunk\Providers\EventServiceProvider::class,
        \Trunk\Providers\AuthServiceProvider::class,
    ];

    public function __construct(?Container $container = null)
    {
        self::$instance = $this;
        $this->container = $container ?? new Container();
        $this->router = new Router($this->container);
        $this->pipeline = new Pipeline($this->container);

        $this->container->singleton(Container::class, $this->container);
        $this->container->singleton(Router::class, $this->router);
        $this->container->singleton(Pipeline::class, $this->pipeline);
        $this->container->singleton(self::class, $this);
    }

    public function registerProvider(string $providerClass): void
    {
        if (isset($this->loadedProviders[$providerClass])) {
            return;
        }

        $provider = new $providerClass($this->container);
        $provider->register();
        $this->serviceProviders[] = $provider;
        $this->loadedProviders[$providerClass] = true;
    }

    public function bootProviders(): void
    {
        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }
    }

    public static function getInstance(): ?App
    {
        return self::$instance;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function configure(string $basePath): void
    {
        $this->basePath = $basePath;
        if (file_exists("$basePath/.env")) {
            $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
            $dotenv->safeLoad();
        }

        $configRepo = new \Trunk\Config\Repository();

        $configPath = "$basePath/config";
        if (is_dir($configPath)) {
            foreach (scandir($configPath) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $key = pathinfo($file, PATHINFO_FILENAME);
                    $configRepo->set($key, require "$configPath/$file");
                }
            }
        }

        $this->container->singleton(\Trunk\Config\Repository::class, $configRepo);

        $providers = $configRepo->get('provider.providers');
        if ($providers) $this->defaultProviders = [...$this->defaultProviders, ...$providers];

        foreach ($this->defaultProviders as $provider) {
            $this->registerProvider($provider);
        }

        // Register event listeners declared in config/events.php: EventClass::class => [ListenerClass::class, ...]
        $eventListeners = $configRepo->get('events', []);
        if (!empty($eventListeners)) {
            $dispatcher = $this->container->get(\Trunk\Event\Dispatcher::class);
            foreach ($eventListeners as $eventClass => $listenerClasses) {
                foreach ($listenerClasses as $listenerClass) {
                    $dispatcher->listen($eventClass, $this->container->get($listenerClass));
                }
            }
        }

        $this->use(\Trunk\Middleware\CorsMiddleware::class);
        $this->use(\Trunk\Middleware\JsonBodyParserMiddleware::class);
        $this->use(\Trunk\Middleware\LogMiddleware::class);
        $this->use(\Trunk\Session\SessionMiddleware::class);

        $this->get('/health', fn() => \Trunk\Http\Response::json([
            'status' => 'healthy',
            'time' => time(),
            'framework' => 'Trunk Async'
        ]));
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function get(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->router->get($path, $handler, $middleware);
    }

    public function post(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->router->post($path, $handler, $middleware);
    }

    public function put(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->router->put($path, $handler, $middleware);
    }

    public function delete(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->router->delete($path, $handler, $middleware);
    }

    public function patch(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->router->patch($path, $handler, $middleware);
    }

    public function use(mixed $middleware): void
    {
        $this->pipeline->pipe($middleware);
    }

    public function run(string $listenAddress = '0.0.0.0:8080'): void
    {
        $this->bootProviders();

        $dispatcher = $this->container->get(\Trunk\Event\Dispatcher::class);

        $httpServer = new HttpServer(function (ServerRequestInterface $request) use ($dispatcher) {
            $start = microtime(true);
            $dispatcher->dispatchAsync(new \Trunk\Event\Events\RequestReceived($request));

            return $this->pipeline->handle($request, $this->router->dispatch(...))->then(function ($response) use ($dispatcher, $request, $start) {
                $dispatcher->dispatchAsync(new \Trunk\Event\Events\ResponseSent($request, $response, (microtime(true) - $start) * 1000));
                return $response;
            });
        });

        $socket = new SocketServer($listenAddress);
        $httpServer->listen($socket);

        echo "Trunk async API server running at http://{$listenAddress}\n";
    }
}
