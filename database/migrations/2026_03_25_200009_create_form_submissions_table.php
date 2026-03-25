<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('form_name')->default('contact');
            $table->json('data');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_spam')->default(false);
            $table->timestamp('created_at');

            $table->index(['site_id', 'is_read']);
            $table->index(['site_id', 'form_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
