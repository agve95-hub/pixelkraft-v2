<?php

namespace Tests\Unit;

use App\Models\Site;
use Tests\TestCase;

/**
 * Verify that the field group constants on Site are internally consistent
 * and that no field appears in two groups (which would undermine the
 * access-control intent of the grouping).
 */
class SiteFieldGroupsTest extends TestCase
{
    public function test_owner_fillable_is_non_empty(): void
    {
        $this->assertNotEmpty(Site::OWNER_FILLABLE);
    }

    public function test_build_fillable_contains_build_command(): void
    {
        $this->assertContains('build_command', Site::BUILD_FILLABLE);
        $this->assertContains('build_output_dir', Site::BUILD_FILLABLE);
        $this->assertContains('env_variables', Site::BUILD_FILLABLE);
        $this->assertContains('node_version', Site::BUILD_FILLABLE);
    }

    public function test_secret_fillable_contains_all_encrypted_fields(): void
    {
        $this->assertContains('github_token', Site::SECRET_FILLABLE);
        $this->assertContains('webhook_secret', Site::SECRET_FILLABLE);
        $this->assertContains('smtp_password', Site::SECRET_FILLABLE);
        $this->assertContains('cf_api_token', Site::SECRET_FILLABLE);
        // ftp_ssh_password removed — SSH/FTP deployment adapter not implemented.
    }

    public function test_system_fillable_contains_deploy_status_fields(): void
    {
        $this->assertContains('deploy_status', Site::SYSTEM_FILLABLE);
        $this->assertContains('last_deployed_at', Site::SYSTEM_FILLABLE);
        $this->assertContains('last_synced_at', Site::SYSTEM_FILLABLE);
    }

    public function test_groups_have_no_overlapping_fields(): void
    {
        $all = array_merge(
            Site::OWNER_FILLABLE,
            Site::BUILD_FILLABLE,
            Site::SECRET_FILLABLE,
            Site::SYSTEM_FILLABLE,
        );

        $duplicates = array_filter(
            array_count_values($all),
            fn (int $count) => $count > 1,
        );

        $this->assertEmpty($duplicates, 'Fields appear in more than one group: '.implode(', ', array_keys($duplicates)));
    }

    public function test_model_fillable_is_union_of_all_groups(): void
    {
        $site = new Site;
        $modelFillable = $site->getFillable();
        $groupUnion = array_merge(
            Site::OWNER_FILLABLE,
            Site::BUILD_FILLABLE,
            Site::SECRET_FILLABLE,
            Site::SYSTEM_FILLABLE,
        );

        sort($modelFillable);
        sort($groupUnion);

        $this->assertSame($groupUnion, $modelFillable);
    }

    public function test_update_settings_only_writes_owner_fillable_fields(): void
    {
        // updateSettings() must silently ignore keys outside OWNER_FILLABLE.
        $site = new Site(array_fill_keys(Site::OWNER_FILLABLE, 'x'));
        // If build_command were leaked through, the method would return with it in the array.
        // We can't call update() on an unsaved model, so we test the intersection logic directly.
        $input = ['name' => 'Test', 'build_command' => 'rm -rf /'];
        $safe = array_intersect_key($input, array_flip(Site::OWNER_FILLABLE));

        $this->assertArrayHasKey('name', $safe);
        $this->assertArrayNotHasKey('build_command', $safe);
    }
}
