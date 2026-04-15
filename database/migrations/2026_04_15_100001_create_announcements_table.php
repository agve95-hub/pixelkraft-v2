<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();

            // Content (HTML will be sanitized before storage by the Livewire component)
            $table->text('message');
            $table->enum('style', ['info', 'warning', 'error', 'success', 'promo'])->default('info');
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();

            // Placement
            $table->enum('placement', ['top_bar', 'inline', 'floating'])->default('top_bar');
            $table->boolean('is_dismissible')->default(true);

            // Schedule
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedTinyInteger('priority')->default(0);

            // Locale / state
            $table->string('locale', 10)->default('en');
            $table->boolean('is_enabled')->default(false);

            $table->timestamps();

            $table->index(['site_id', 'is_enabled', 'starts_at', 'ends_at'], 'announcements_site_active_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
