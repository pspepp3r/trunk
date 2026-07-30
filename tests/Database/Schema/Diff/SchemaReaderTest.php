<?php

namespace Trunk\Tests\Database\Schema\Diff;

use PHPUnit\Framework\TestCase;
use Trunk\Database\Schema\Diff\SchemaReader;
use Trunk\Tests\Fixtures\AuthorFixture;
use Trunk\Tests\Fixtures\PostFixture;
use Trunk\Tests\Fixtures\TagFixture;

class SchemaReaderTest extends TestCase
{
    private function read(): array
    {
        return (new SchemaReader())->read([AuthorFixture::class, PostFixture::class, TagFixture::class]);
    }

    public function testReadsPlainColumnsIncludingPrimaryKey(): void
    {
        $tables = $this->read();

        $this->assertArrayHasKey('fixture_authors', $tables);
        $authors = $tables['fixture_authors'];

        $this->assertTrue($authors->columns['id']->primary);
        $this->assertTrue($authors->columns['id']->autoIncrement);
        $this->assertSame('VARCHAR', $authors->columns['name']->type);
        $this->assertSame(255, $authors->columns['name']->length);
    }

    public function testManyToOneGeneratesForeignKeyColumnAndConstraint(): void
    {
        $tables = $this->read();
        $posts = $tables['fixture_posts'];

        $this->assertTrue($posts->hasColumn('author_id'));
        $this->assertTrue($posts->hasForeignKey('author_id'));
        $this->assertSame('fixture_authors', $posts->foreignKeys['author_id']->referencesTable);
    }

    public function testOneToManyContributesNoColumnOnTheInverseSide(): void
    {
        $tables = $this->read();
        $authors = $tables['fixture_authors'];

        // AuthorFixture::$posts is #[OneToMany] - it must never produce an "posts"
        // column or a fixture_authors_posts table; the FK lives on fixture_posts.
        $this->assertFalse($authors->hasColumn('posts'));
    }

    public function testManyToManyGeneratesOnePivotTableFromTheOwningSideOnly(): void
    {
        $tables = $this->read();

        $this->assertArrayHasKey('fixture_posts_fixture_tags', $tables);

        $pivot = $tables['fixture_posts_fixture_tags'];
        $this->assertTrue($pivot->hasColumn('fixture_post_id'));
        $this->assertTrue($pivot->hasColumn('fixture_tag_id'));
        $this->assertTrue($pivot->hasForeignKey('fixture_post_id'));
        $this->assertTrue($pivot->hasForeignKey('fixture_tag_id'));

        // TagFixture::$posts is the mappedBy (inverse) side - it must not generate a
        // second, duplicate pivot table.
        $this->assertCount(4, $tables); // fixture_authors, fixture_posts, fixture_tags, one pivot
    }
}
