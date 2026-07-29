<?php

namespace Trunk\Tests\Database\Grammar;

use PHPUnit\Framework\TestCase;
use Trunk\Database\Grammar\SqliteGrammar;
use Trunk\Database\QueryResult;

class SqliteGrammarTest extends TestCase
{
    private SqliteGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    public function testQuoteIdentifierUsesDoubleQuotes(): void
    {
        $this->assertSame('"users"', $this->grammar->quoteIdentifier('users'));
    }

    public function testColumnTypeMapsPhpTypesToSqlTypes(): void
    {
        $this->assertSame('INTEGER', $this->grammar->columnType('int'));
        $this->assertSame('INTEGER', $this->grammar->columnType('bool'));
        $this->assertSame('REAL', $this->grammar->columnType('float'));
        $this->assertSame('TEXT', $this->grammar->columnType('string'));
    }

    public function testColumnSqlMapsSemanticBlueprintTypes(): void
    {
        $this->assertSame('VARCHAR(100)', $this->grammar->columnSql('string', ['length' => 100]));
        $this->assertSame('VARCHAR(255)', $this->grammar->columnSql('string'));
        $this->assertSame('INTEGER', $this->grammar->columnSql('bigInteger'));
        $this->assertSame('DATETIME', $this->grammar->columnSql('timestamp'));
        $this->assertSame('TEXT', $this->grammar->columnSql('json'));
    }

    public function testPrimaryKeyColumnIsAutoincrement(): void
    {
        $this->assertSame('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $this->grammar->primaryKeyColumn('id'));
    }

    public function testCompileBindingsLeavesPlaceholdersUnchanged(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
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

    public function testTableOptionsIsEmpty(): void
    {
        $this->assertSame('', $this->grammar->tableOptions());
    }
}
