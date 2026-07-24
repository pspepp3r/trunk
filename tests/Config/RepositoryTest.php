<?php

namespace Trunk\Tests\Config;

use PHPUnit\Framework\TestCase;
use Trunk\Config\Repository;

class RepositoryTest extends TestCase
{
    public function testGetReturnsDefaultWhenMissing(): void
    {
        $repo = new Repository();

        $this->assertSame('fallback', $repo->get('missing.key', 'fallback'));
        $this->assertNull($repo->get('missing.key'));
    }

    public function testGetTopLevelKey(): void
    {
        $repo = new Repository(['app' => ['name' => 'Trunk']]);

        $this->assertSame(['name' => 'Trunk'], $repo->get('app'));
    }

    public function testGetDotNotationTraversesNestedArrays(): void
    {
        $repo = new Repository(['database' => ['connections' => ['mysql' => ['host' => '127.0.0.1']]]]);

        $this->assertSame('127.0.0.1', $repo->get('database.connections.mysql.host'));
    }

    public function testGetDotNotationReturnsDefaultWhenIntermediateSegmentMissing(): void
    {
        $repo = new Repository(['database' => ['connections' => []]]);

        $this->assertSame('fallback', $repo->get('database.connections.mysql.host', 'fallback'));
    }

    public function testSetCreatesNestedStructureViaDotNotation(): void
    {
        $repo = new Repository();
        $repo->set('database.connections.mysql.host', '127.0.0.1');

        $this->assertSame('127.0.0.1', $repo->get('database.connections.mysql.host'));
        $this->assertSame(
            ['database' => ['connections' => ['mysql' => ['host' => '127.0.0.1']]]],
            $repo->all()
        );
    }

    public function testSetOverwritesExistingValue(): void
    {
        $repo = new Repository(['app' => ['env' => 'production']]);
        $repo->set('app.env', 'local');

        $this->assertSame('local', $repo->get('app.env'));
    }
}
