<?php

namespace Trunk\Tests;

use PHPUnit\Framework\TestCase;
use Trunk\Container\Container;
use Trunk\Container\Exception\ContainerException;
use Trunk\Container\Exception\NotFoundException;
use Trunk\Tests\Fixtures\PlainService;
use Trunk\Tests\Fixtures\ServiceWithDependency;
use Trunk\Tests\Fixtures\ServiceWithScalarDefault;
use Trunk\Tests\Fixtures\UnresolvableService;

class ContainerTest extends TestCase
{
    public function testGetThrowsNotFoundExceptionForUnknownId(): void
    {
        $container = new Container();

        $this->expectException(NotFoundException::class);
        $container->get('does.not.exist');
    }

    public function testGetAutowiresAnUnregisteredExistingClass(): void
    {
        $container = new Container();

        $instance = $container->get(PlainService::class);

        $this->assertInstanceOf(PlainService::class, $instance);
    }

    public function testAutowireRecursivelyResolvesConstructorDependencies(): void
    {
        $container = new Container();

        $instance = $container->get(ServiceWithDependency::class);

        $this->assertInstanceOf(ServiceWithDependency::class, $instance);
        $this->assertInstanceOf(PlainService::class, $instance->plainService);
    }

    public function testAutowireUsesDefaultValueForUntypedOrScalarParameters(): void
    {
        $container = new Container();

        $instance = $container->get(ServiceWithScalarDefault::class);

        $this->assertSame(10, $instance->limit);
    }

    public function testAutowireThrowsContainerExceptionWhenParameterUnresolvable(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->get(UnresolvableService::class);
    }

    public function testGetWithoutAutowireCreatesFreshInstanceEachTime(): void
    {
        $container = new Container();

        $first = $container->get(PlainService::class);
        $second = $container->get(PlainService::class);

        $this->assertNotSame($first, $second);
    }

    public function testSetBindsALiteralValue(): void
    {
        $container = new Container();
        $container->set('config.name', 'Trunk');

        $this->assertSame('Trunk', $container->get('config.name'));
    }

    public function testSetBindsAClosureFactoryEvaluatedOnGet(): void
    {
        $container = new Container();
        $calls = 0;
        $container->set(PlainService::class, function () use (&$calls) {
            $calls++;
            return new PlainService();
        });

        $container->get(PlainService::class);
        $container->get(PlainService::class);

        $this->assertSame(2, $calls, 'a plain set() factory should run on every get()');
    }

    public function testSingletonEvaluatesClosureOnlyOnceAndReusesTheInstance(): void
    {
        $container = new Container();
        $calls = 0;
        $container->singleton(PlainService::class, function () use (&$calls) {
            $calls++;
            return new PlainService();
        });

        $first = $container->get(PlainService::class);
        $second = $container->get(PlainService::class);

        $this->assertSame(1, $calls);
        $this->assertSame($first, $second);
    }

    public function testSingletonWithClassNameStringAutowiresAndReuses(): void
    {
        $container = new Container();
        $container->singleton('plain', PlainService::class);

        $first = $container->get('plain');
        $second = $container->get('plain');

        $this->assertInstanceOf(PlainService::class, $first);
        $this->assertSame($first, $second);
    }

    public function testSingletonWithLiteralObjectReturnsThatExactInstance(): void
    {
        $container = new Container();
        $instance = new PlainService();
        $container->singleton(PlainService::class, $instance);

        $this->assertSame($instance, $container->get(PlainService::class));
    }

    public function testHasReturnsTrueForRegisteredEntryOrExistingClass(): void
    {
        $container = new Container();
        $container->set('bound', 'value');

        $this->assertTrue($container->has('bound'));
        $this->assertTrue($container->has(PlainService::class));
        $this->assertFalse($container->has('Totally\\Unknown\\Class'));
    }
}
