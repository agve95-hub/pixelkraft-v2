<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('number');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('status', 20)->default('unpaid'); // unpaid|paid
            $table->string('currency_code', 3)->default('EUR');
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->string('payment_terms', 32)->default('net30');
            $table->text('notes')->nullable();
            $table->text('payment_details')->nullable();
            $table->text('from_address')->nullable();
            $table->text('bill_to')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 14, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
