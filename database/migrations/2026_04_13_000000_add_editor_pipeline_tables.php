<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editable_regions', function (Blueprint $table) {
            $table->string('render_selector')->nullable()->after('selector');
            $table->json('dom_fingerprint')->nullable()->after('source_location');
            $table->json('source_anchor')->nullable()->after('dom_fingerprint');
            $table->timestamp('last_verified_at')->nullable()->after('source_anchor');

            $table->index(['page_id', 'marker_id']);
        });

        Schema::create('edit_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('page_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('base_commit_sha', 64)->nullable();
            $table->string('working_branch')->nullable();
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'page_id', 'status']);
            $table->index(['site_id', 'started_at']);
        });

        Schema::create('git_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('edit_session_id')->nullable()->constrained('edit_sessions')->nullOnDelete();
            $table->string('operation', 32);
            $table->string('status', 32)->default('started');
            $table->string('branch')->nullable();
            $table->string('working_branch')->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->json('files')->nullable();
            $table->longText('output_log')->nullable();
            $table->longText('error_output')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'operation', 'started_at']);
            $table->index(['site_id', 'status']);
        });

        Schema::create('deployment_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 32);
            $table->string('host')->nullable();
            $table->string('deploy_path')->nullable();
            $table->string('runtime_type', 32)->default('static');
            $table->string('health_check_url')->nullable();
            $table->string('release_strategy', 32)->default('replace');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'environment']);
        });

        Schema::create('deployment_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deployment_target_id')->nullable()->constrained('deployment_targets')->nullOnDelete();
            $table->foreignUuid('deploy_log_id')->nullable()->constrained('deploy_logs')->nullOnDelete();
            $table->foreignUuid('rollback_of_release_id')->nullable()->constrained('deployment_releases')->nullOnDelete();
            $table->string('source_commit_sha', 64)->nullable();
            $table->string('source_branch')->nullable();
            $table->string('artifact_path')->nullable();
            $table->string('tracking_version')->nullable();
            $table->string('status', 32)->default('building');
            $table->boolean('is_current')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status', 'created_at']);
            $table->index(['site_id', 'is_current']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 100);
            $table->string('path')->nullable();
            $table->string('visitor_id', 120)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_hash', 128)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['site_id', 'event_name', 'occurred_at']);
            $table->index(['site_id', 'path', 'occurred_at']);
        });

        Schema::create('tracking_installations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('platform');
            $table->string('measurement_id')->nullable();
            $table->string('container_id')->nullable();
            $table->string('script_route')->nullable();
            $table->string('collector_path')->nullable();
            $table->boolean('consent_mode')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'provider']);
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->foreignUuid('site_id')->nullable()->after('repository')->constrained()->nullOnDelete();
            $table->string('status', 32)->default('received')->after('site_id');
            $table->json('headers')->nullable()->after('status');
            $table->json('payload')->nullable()->after('headers');
            $table->timestamp('processed_at')->nullable()->after('received_at');

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'status']);
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn([
                'status',
                'headers',
                'payload',
                'processed_at',
            ]);
        });

        Schema::dropIfExists('tracking_installations');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('deployment_releases');
        Schema::dropIfExists('deployment_targets');
        Schema::dropIfExists('git_operations');
        Schema::dropIfExists('edit_sessions');

        Schema::table('editable_regions', function (Blueprint $table) {
            $table->dropIndex(['page_id', 'marker_id']);
            $table->dropColumn([
                'render_selector',
                'dom_fingerprint',
                'source_anchor',
                'last_verified_at',
            ]);
        });
    }
};
