<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity', 16)->default('warning');
            $table->string('code', 64)->nullable();
            $table->string('message');
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'resolved_at']);
            $table->index(['page_id', 'resolved_at']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_done', 'due_date']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'expense_date']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('report_date');
            $table->text('summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('seo_issues');
    }
};
