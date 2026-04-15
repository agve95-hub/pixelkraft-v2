<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two existing indexes on analytics_events are:
     *   (site_id, event_name, occurred_at)
     *   (site_id, path,       occurred_at)
     *
     * Queries that filter only on (site_id, occurred_at) — e.g. the "total events
     * in last N days" aggregation and the EXISTS check in syncFirstPartyTracker —
     * cannot use a 3-column index efficiently when the second column is not
     * supplied.  A dedicated (site_id, occurred_at) index covers those queries
     * and also speeds up DELETE/prune operations that work by age.
     */
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index(['site_id', 'occurred_at'], 'analytics_events_site_id_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_events_site_id_occurred_at_index');
        });
    }
};
