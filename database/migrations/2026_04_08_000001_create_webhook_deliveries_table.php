<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('delivery_id', 255);
            $table->string('event', 100)->nullable();
            $table->string('repository', 255)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['provider', 'delivery_id']);
            $table->index(['provider', 'event']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
