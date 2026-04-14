<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sites', 'webhook_secret')) {
            Schema::table('sites', function (Blueprint $table) {
                // Stored encrypted at rest (same pattern as github_token).
                // When set, the GitHub webhook for this site must be signed with
                // this secret instead of the global GITHUB_WEBHOOK_SECRET.
                $table->text('webhook_secret')->nullable()->after('github_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sites', 'webhook_secret')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn('webhook_secret');
            });
        }
    }
};
