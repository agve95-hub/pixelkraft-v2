<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editable_regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained()->cascadeOnDelete();
            $table->string('selector');
            $table->string('marker_id')->nullable();
            $table->string('region_type')->default('text'); // text|image|link|section|list|meta
            $table->boolean('is_static')->default(false);
            $table->string('detection_method')->default('auto'); // auto|manual|marker
            $table->float('confidence_score')->default(0);
            $table->longText('current_content')->nullable();
            $table->json('source_location')->nullable(); // {file, line_start, line_end}
            $table->timestamps();

            $table->index(['page_id', 'is_static']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editable_regions');
    }
};
