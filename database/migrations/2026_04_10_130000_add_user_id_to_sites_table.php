<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sites', 'user_id')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->foreignUuid('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index('user_id');
            });
        }

        $firstAdminId = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('created_at')
            ->value('id');

        if ($firstAdminId && Schema::hasColumn('sites', 'user_id')) {
            DB::table('sites')
                ->whereNull('user_id')
                ->update(['user_id' => $firstAdminId]);
        } elseif (Schema::hasColumn('sites', 'user_id') && DB::table('sites')->whereNull('user_id')->exists()) {
            throw new \RuntimeException('Cannot assign existing sites: no admin user exists.');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sites', 'user_id')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
