<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->longText('body_html');
            $table->foreignUuid('template_id')->nullable()->constrained('content_templates')->nullOnDelete();
            $table->json('segment_filter')->nullable();
            $table->string('status')->default('draft'); // draft|scheduled|sending|sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('stats')->nullable(); // {sent, opened, clicked, bounced}
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
