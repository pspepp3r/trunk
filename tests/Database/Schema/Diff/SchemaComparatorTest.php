<?php

namespace Trunk\Tests\Database\Schema\Diff;

use PHPUnit\Framework\TestCase;
use Trunk\Database\Grammar\MysqlGrammar;
use Trunk\Database\Schema\Diff\ColumnSchema;
use Trunk\Database\Schema\Diff\ForeignKeySchema;
use Trunk\Database\Schema\Diff\SchemaComparator;
use Trunk\Database\Schema\Diff\TableSchema;

class SchemaComparatorTest extends TestCase
{
    private function comparator(): SchemaComparator
    {
        return new SchemaComparator(new MysqlGrammar());
    }

    public function testGeneratesCreateTableForAMissingTable(): void
    {
        $expected = [
            'users' => new TableSchema('users', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
                'name' => new ColumnSchema('name', 'VARCHAR', length: 255),
            ]),
        ];

        $diff = $this->comparator()->diff($expected, []);

        $this->assertCount(1, $diff['up']);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `users`', $diff['up'][0]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `users`', $diff['down'][0]);
    }

    public function testGeneratesAddColumnForAColumnMissingOnAnExistingTable(): void
    {
        $expected = [
            'users' => new TableSchema('users', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
                'name' => new ColumnSchema('name', 'VARCHAR', length: 255),
                'bio' => new ColumnSchema('bio', 'TEXT', nullable: true),
            ]),
        ];

        $actual = [
            'users' => new TableSchema('users', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
                'name' => new ColumnSchema('name', 'VARCHAR', length: 255),
            ]),
        ];

        $diff = $this->comparator()->diff($expected, $actual);

        $this->assertCount(1, $diff['up']);
        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN `bio`', $diff['up'][0]);
    }

    public function testGeneratesAddForeignKeyForAMissingConstraint(): void
    {
        $expected = [
            'posts' => new TableSchema(
                'posts',
                ['author_id' => new ColumnSchema('author_id', 'integer', nullable: true)],
                ['author_id' => new ForeignKeySchema('author_id', 'authors')]
            ),
        ];

        $actual = [
            'posts' => new TableSchema('posts', [
                'author_id' => new ColumnSchema('author_id', 'integer', nullable: true),
            ]),
        ];

        $diff = $this->comparator()->diff($expected, $actual);

        $this->assertCount(1, $diff['up']);
        $this->assertStringContainsString('ADD CONSTRAINT `fk_posts_author_id`', $diff['up'][0]);
        $this->assertStringContainsString('FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`)', $diff['up'][0]);
    }

    public function testNothingToDoWhenSchemasAlreadyMatch(): void
    {
        $schema = [
            'users' => new TableSchema('users', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
            ]),
        ];

        $diff = $this->comparator()->diff($schema, $schema);

        $this->assertSame([], $diff['up']);
        $this->assertSame([], $diff['down']);
    }

    public function testNeverGeneratesDropStatementsForColumnsOrTablesNotInExpectedSchema(): void
    {
        $expected = [
            'users' => new TableSchema('users', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
            ]),
        ];

        // Actual DB has an extra table and an extra column not present in the entities.
        $actual = [
            'users' => new TableSchema('users', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
                'legacy_column' => new ColumnSchema('legacy_column', 'VARCHAR'),
            ]),
            'legacy_table' => new TableSchema('legacy_table', [
                'id' => new ColumnSchema('id', 'INT', primary: true, autoIncrement: true),
            ]),
        ];

        $diff = $this->comparator()->diff($expected, $actual);

        $this->assertSame([], $diff['up']);
        foreach (array_merge($diff['up'], $diff['down']) as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('DROP', $sql);
        }
    }
}
