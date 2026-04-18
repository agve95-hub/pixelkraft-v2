<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class SchemaState
{
    /**
     * @var array<string, bool>
     */
    private static array $tableCache = [];

    /**
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    public static function reset(): void
    {
        self::$tableCache = [];
        self::$columnCache = [];
    }

    public static function hasTable(string $table): bool
    {
        return self::$tableCache[$table] ??= Schema::hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return self::$columnCache[$key] ??= self::hasTable($table)
            && Schema::hasColumn($table, $column);
    }
}
