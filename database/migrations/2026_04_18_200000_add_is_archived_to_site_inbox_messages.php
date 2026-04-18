<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_inbox_messages', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('is_read');
            $table->index(['site_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::table('site_inbox_messages', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'is_archived']);
            $table->dropColumn('is_archived');
        });
    }
};
