<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sites', 'inbox_inbound_secret')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->text('inbox_inbound_secret')->nullable()->after('webhook_secret');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sites', 'inbox_inbound_secret')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn('inbox_inbound_secret');
            });
        }
    }
};
