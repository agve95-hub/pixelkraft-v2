<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uptime_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_up')->default(true);
            $table->timestamp('checked_at');

            $table->index(['site_id', 'checked_at']);
            $table->index(['site_id', 'is_up']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uptime_checks');
    }
};
