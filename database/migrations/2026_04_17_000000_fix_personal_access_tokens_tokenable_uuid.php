<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace bigint morph columns with UUID morph columns for Sanctum.
     *
     * The original migration used morphs(), which is incompatible with
     * App\Models\User (UUID primary keys) on MySQL/MariaDB.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
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

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->morphs('tokenable');
        });
    }
};
