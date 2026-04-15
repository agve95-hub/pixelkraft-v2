<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // blog_posts: queries that order published posts by date
        // (e.g. "published posts for site X, newest first") need a covering index
        // on (site_id, published_at) rather than scanning the whole table.
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index(['site_id', 'published_at'], 'blog_posts_site_id_published_at_index');
        });

        // content_revisions: revision history is always fetched for a specific
        // region ordered by creation time. Without this index the query degrades
        // to a full table scan as revision history grows.
        Schema::table('content_revisions', function (Blueprint $table) {
            $table->index(['region_id', 'created_at'], 'content_revisions_region_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('content_revisions', function (Blueprint $table) {
            $table->dropIndex('content_revisions_region_id_created_at_index');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex('blog_posts_site_id_published_at_index');
        });
    }
};
