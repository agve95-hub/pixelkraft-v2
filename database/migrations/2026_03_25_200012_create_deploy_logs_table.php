<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('started'); // started|building|deploying|success|failed
            $table->string('commit_sha')->nullable();
            $table->string('commit_message')->nullable();
            $table->longText('output_log')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('triggered_by')->default('manual'); // manual|save|webhook|schedule
            $table->string('snapshot_tag')->nullable(); // for rollback
            $table->timestamp('created_at');

            $table->index(['site_id', 'created_at']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_logs');
    }
};
