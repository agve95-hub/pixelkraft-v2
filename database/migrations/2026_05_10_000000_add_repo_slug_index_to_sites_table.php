<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Indexed normalized GitHub repository slug (e.g. "owner/repo").
            // Used by WebhookController to look up matching sites in O(log n)
            // instead of loading every active site and filtering in PHP.
            $table->string('repo_slug', 255)->nullable()->after('repo_url');
            $table->index('repo_slug');
        });

        // Back-fill from existing repo_url values.
        Site::query()->whereNotNull('repo_url')->lazyById()->each(function (Site $site) {
            $slug = Site::normalizeGithubRepository($site->repo_url);
            if ($slug !== null) {
                $site->updateQuietly(['repo_slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['repo_slug']);
            $table->dropColumn('repo_slug');
        });
    }
};
