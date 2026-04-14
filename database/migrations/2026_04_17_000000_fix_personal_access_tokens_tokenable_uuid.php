<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure tokenable_id can store UUID strings (User model uses UUID PKs).
     *
     * Older installs used morphs() → bigint tokenable_id, which truncates UUIDs
     * on MariaDB/MySQL and fails Sanctum createToken().
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (! Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
            return;
        }

        if (! $this->tokenableIdIsNumeric()) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuidMorphs('tokenable');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (! Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
            return;
        }

        if ($this->tokenableIdIsNumeric()) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->numericMorphs('tokenable');
        });
    }

    private function tokenableIdIsNumeric(): bool
    {
        $type = strtolower(Schema::getConnection()->getSchemaBuilder()->getColumnType(
            'personal_access_tokens',
            'tokenable_id'
        ));

        return in_array($type, [
            'bigint',
            'int',
            'integer',
            'mediumint',
            'smallint',
            'tinyint',
        ], true);
    }
};
