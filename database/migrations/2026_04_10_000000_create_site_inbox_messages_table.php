<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_inbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 16); // inbound | outbound
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->string('source', 32)->nullable(); // form | webhook | dashboard
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index(['site_id', 'direction', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_inbox_messages');
    }
};
