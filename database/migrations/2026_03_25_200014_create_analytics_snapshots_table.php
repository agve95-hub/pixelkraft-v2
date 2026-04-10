<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('source'); // google_analytics_organic|cloudflare|...
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('pageviews')->default(0);
            $table->float('bounce_rate')->nullable();
            $table->unsignedInteger('avg_session_sec')->nullable();
            $table->json('custom_events')->nullable();
            $table->timestamp('created_at');

            $table->unique(['page_id', 'date', 'source']);
            $table->index(['page_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
