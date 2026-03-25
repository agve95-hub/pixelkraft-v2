<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('repo_url');
            $table->string('branch')->default('main');
            $table->text('github_token')->nullable(); // encrypted
            $table->string('project_type')->default('static_html');
            $table->string('build_command')->nullable();
            $table->string('build_output_dir')->nullable();
            $table->string('node_version')->default('20');
            $table->json('env_variables')->nullable();
            $table->string('domain')->nullable();
            $table->string('ssl_status')->default('pending'); // pending|active|expired|error
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('deploy_status')->default('idle'); // idle|building|deploying|live|failed
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('ga_property_id')->nullable();
            $table->string('cf_zone_id')->nullable();
            $table->string('gsc_property')->nullable();
            $table->string('r2_bucket_prefix')->nullable();
            $table->string('nginx_conf_path')->nullable();
            $table->string('deploy_path')->nullable();
            $table->string('repo_path')->nullable();
            $table->text('pre_deploy_hook')->nullable();
            $table->text('post_deploy_hook')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
