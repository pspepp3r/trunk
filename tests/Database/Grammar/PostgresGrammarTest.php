<?php

namespace Trunk\Tests\Database\Grammar;

use PHPUnit\Framework\TestCase;
use Trunk\Database\Grammar\PostgresGrammar;
use Trunk\Database\QueryResult;

class PostgresGrammarTest extends TestCase
{
    private PostgresGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new PostgresGrammar();
    }

    public function testQuoteIdentifierUsesDoubleQuotes(): void
    {
        $this->assertSame('"users"', $this->grammar->quoteIdentifier('users'));
    }

    public function testColumnTypeMapsPhpTypesToSqlTypes(): void
    {
        $this->assertSame('INTEGER', $this->grammar->columnType('int'));
        $this->assertSame('BOOLEAN', $this->grammar->columnType('bool'));
        $this->assertSame('DOUBLE PRECISION', $this->grammar->columnType('float'));
    }

    public function testPrimaryKeyColumnUsesSerial(): void
    {
        $this->assertSame('"id" SERIAL PRIMARY KEY', $this->grammar->primaryKeyColumn('id'));
    }

    public function testCompileBindingsConvertsSequentialQuestionMarksToDollarPlaceholders(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ? AND name = ? AND active = ?';

        $this->assertSame(
            'SELECT * FROM users WHERE id = $1 AND name = $2 AND active = $3',
            $this->grammar->compileBindings($sql)
        );
    }

    public function testCompileBindingsWithNoPlaceholdersIsUnchanged(): void
    {
        $sql = 'SELECT * FROM users';

        $this->assertSame($sql, $this->grammar->compileBindings($sql));
    }

    public function testInsertReturningClauseAppendsQuotedPrimaryKey(): void
    {
        $this->assertSame(' RETURNING "id"', $this->grammar->insertReturningClause('id'));
    }

    public function testExtractInsertIdReadsFromFirstReturnedRow(): void
    {
        $result = new QueryResult(rows: [['id' => 7, 'name' => 'Alice']]);

        $this->assertSame(7, $this->grammar->extractInsertId($result, 'id'));
    }

    public function testExtractInsertIdReturnsNullWhenNoRowsReturned(): void
    {
        $result = new QueryResult(rows: []);

        $this->assertNull($this->grammar->extractInsertId($result, 'id'));
    }

    public function testTableOptionsIsEmpty(): void
    {
        $this->assertSame('', $this->grammar->tableOptions());
    }
}
