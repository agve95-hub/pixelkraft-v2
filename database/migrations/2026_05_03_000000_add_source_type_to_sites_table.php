<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Tracks how a site's source files arrived: cloned from git, uploaded as a ZIP, or mounted from a local server path.
            $table->string('source_type', 32)->default('github')->after('project_type');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
