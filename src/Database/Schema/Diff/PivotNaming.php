<?php

namespace Trunk\Database\Schema\Diff;

/** Shared by SchemaReader and EntityManager::loadRelation() so pivot naming stays in sync - see the Database guide's Relationships section. */
final class PivotNaming
{
    public static function tableName(string $tableA, string $tableB): string
    {
        $names = [$tableA, $tableB];
        sort($names);

        return implode('_', $names);
    }

    public static function singular(string $tableName): string
    {
        return str_ends_with($tableName, 's') ? substr($tableName, 0, -1) : $tableName;
    }
}
