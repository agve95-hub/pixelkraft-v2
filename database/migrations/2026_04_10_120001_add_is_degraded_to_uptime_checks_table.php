<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('uptime_checks', 'is_degraded')) {
            Schema::table('uptime_checks', function (Blueprint $table) {
                $table->boolean('is_degraded')->default(false)->after('is_up');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('uptime_checks', 'is_degraded')) {
            Schema::table('uptime_checks', function (Blueprint $table) {
                $table->dropColumn('is_degraded');
            });
        }
    }
};
