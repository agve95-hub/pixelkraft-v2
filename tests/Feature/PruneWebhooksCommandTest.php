<?php

namespace Tests\Feature;

use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneWebhooksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_old_deliveries(): void
    {
        WebhookDelivery::create([
            'provider' => 'github',
            'delivery_id' => 'old-delivery',
            'event' => 'push',
            'repository' => 'acme/old',
            'received_at' => now()->subDays(60),
        ]);

        WebhookDelivery::create([
            'provider' => 'github',
            'delivery_id' => 'new-delivery',
            'event' => 'push',
            'repository' => 'acme/new',
            'received_at' => now()->subDays(5),
        ]);

        $this->artisan('pixelkraft:prune-webhooks', ['--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseMissing('webhook_deliveries', ['delivery_id' => 'old-delivery']);
        $this->assertDatabaseHas('webhook_deliveries', ['delivery_id' => 'new-delivery']);
    }

    public function test_prune_dry_run_does_not_delete(): void
    {
        WebhookDelivery::create([
            'provider' => 'github',
            'delivery_id' => 'dry-old',
            'event' => 'push',
            'repository' => 'acme/dry',
            'received_at' => now()->subDays(60),
        ]);

        $this->artisan('pixelkraft:prune-webhooks', ['--days' => 30, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('webhook_deliveries', ['delivery_id' => 'dry-old']);
    }

    public function test_prune_uses_config_default_when_days_omitted(): void
    {
        config(['pixelkraft.monitoring.webhook_deliveries_retention_days' => 10]);

        WebhookDelivery::create([
            'provider' => 'github',
            'delivery_id' => 'cfg-old',
            'event' => 'push',
            'repository' => 'acme/cfg',
            'received_at' => now()->subDays(20),
        ]);

        $this->artisan('pixelkraft:prune-webhooks')->assertSuccessful();

        $this->assertDatabaseMissing('webhook_deliveries', ['delivery_id' => 'cfg-old']);
    }
}
