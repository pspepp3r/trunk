<?php

namespace Trunk\Tests\Database\Grammar;

use PHPUnit\Framework\TestCase;
use Trunk\Database\Grammar\MysqlGrammar;
use Trunk\Database\QueryResult;

class MysqlGrammarTest extends TestCase
{
    private MysqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new MysqlGrammar();
    }

    public function testQuoteIdentifierUsesBackticks(): void
    {
        $this->assertSame('`users`', $this->grammar->quoteIdentifier('users'));
    }

    public function testColumnTypeMapsPhpTypesToSqlTypes(): void
    {
        $this->assertSame('INT', $this->grammar->columnType('int'));
        $this->assertSame('TINYINT(1)', $this->grammar->columnType('bool'));
        $this->assertSame('DOUBLE', $this->grammar->columnType('float'));
        $this->assertSame('VARCHAR(255)', $this->grammar->columnType('string'));
    }

    public function testColumnSqlMapsSemanticBlueprintTypes(): void
    {
        $this->assertSame('VARCHAR(100)', $this->grammar->columnSql('string', ['length' => 100]));
        $this->assertSame('VARCHAR(255)', $this->grammar->columnSql('string'));
        $this->assertSame('BIGINT', $this->grammar->columnSql('bigInteger'));
        $this->assertSame('DATETIME', $this->grammar->columnSql('timestamp'));
        $this->assertSame('JSON', $this->grammar->columnSql('json'));
    }

    public function testPrimaryKeyColumnIsAutoIncrement(): void
    {
        $this->assertSame('`id` INT AUTO_INCREMENT PRIMARY KEY', $this->grammar->primaryKeyColumn('id'));
    }

    public function testCompileBindingsLeavesPlaceholdersUnchanged(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';

        $this->assertSame($sql, $this->grammar->compileBindings($sql));
    }

    public function testInsertReturningClauseIsEmpty(): void
    {
        $this->assertSame('', $this->grammar->insertReturningClause('id'));
    }

    public function testExtractInsertIdReadsFromQueryResult(): void
    {
        $result = new QueryResult(rows: [], insertId: 42, affectedRows: 1);

        $this->assertSame(42, $this->grammar->extractInsertId($result, 'id'));
    }

    public function testTableOptionsIncludesEngineAndCharset(): void
    {
        $this->assertStringContainsString('ENGINE=InnoDB', $this->grammar->tableOptions());
        $this->assertStringContainsString('utf8mb4', $this->grammar->tableOptions());
    }
}
