<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // deploy_failed|ssl_expiring|uptime_down|form_received|lighthouse_drop|broken_links
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignUuid('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->json('data')->nullable();
            $table->timestamp('created_at');

            $table->index(['is_read', 'created_at']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
