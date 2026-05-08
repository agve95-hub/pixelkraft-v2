<?php

namespace Tests\Feature;

use Tests\TestCase;

class UIComponentContractTest extends TestCase
{
    public function test_disabled_link_button_does_not_render_a_navigable_anchor(): void
    {
        $html = (string) $this->blade('<x-ui.button href="/danger-zone" disabled>Delete</x-ui.button>');

        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('href="/danger-zone"', $html);
    }

    public function test_enabled_link_button_renders_a_real_anchor(): void
    {
        $html = (string) $this->blade('<x-ui.button href="/dashboard">Dashboard</x-ui.button>');

        $this->assertStringContainsString('<a href="/dashboard"', $html);
        $this->assertStringContainsString('role="button"', $html);
    }
}
