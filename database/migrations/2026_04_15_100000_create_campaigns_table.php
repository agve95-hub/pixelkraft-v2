<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('name');

            // Content
            $table->string('headline');
            $table->text('body')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();

            // Trigger / behaviour
            $table->enum('trigger', ['on_load', 'on_scroll', 'on_exit', 'on_delay'])->default('on_load');
            $table->unsignedInteger('trigger_delay_ms')->nullable();

            // Targeting
            $table->json('target_pages')->nullable()->comment('Array of URL patterns where this popup appears');
            $table->json('audience_conditions')->nullable()->comment('Reserved for future audience-based targeting rules');

            // Schedule
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedTinyInteger('priority')->default(0)->comment('Higher = shown first when multiple campaigns match');

            // Behaviour
            $table->boolean('is_dismissible')->default(true);
            $table->json('dismissal_rules')->nullable();

            // Locale / state
            $table->string('locale', 10)->default('en');
            $table->boolean('is_enabled')->default(false);

            $table->timestamps();

            $table->index(['site_id', 'is_enabled', 'starts_at', 'ends_at'], 'campaigns_site_active_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
