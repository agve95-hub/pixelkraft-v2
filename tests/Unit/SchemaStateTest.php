<?php

namespace Tests\Unit;

use App\Support\SchemaState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SchemaState::reset();
    }

    protected function tearDown(): void
    {
        SchemaState::reset();
        parent::tearDown();
    }

    public function test_has_table_returns_true_for_existing_table(): void
    {
        $this->assertTrue(SchemaState::hasTable('sites'));
    }

    public function test_has_table_returns_false_for_nonexistent_table(): void
    {
        $this->assertFalse(SchemaState::hasTable('nonexistent_table_xyz'));
    }

    public function test_has_table_caches_result_and_does_not_call_schema_twice(): void
    {
        Schema::shouldReceive('hasTable')->with('sites')->andReturn(true)->once();

        SchemaState::reset();
        $first = SchemaState::hasTable('sites');
        $second = SchemaState::hasTable('sites');

        $this->assertTrue($first);
        $this->assertTrue($second);
    }

    public function test_has_column_returns_true_for_existing_column(): void
    {
        $this->assertTrue(SchemaState::hasColumn('sites', 'slug'));
    }

    public function test_has_column_returns_false_for_nonexistent_column(): void
    {
        $this->assertFalse(SchemaState::hasColumn('sites', 'nonexistent_col_xyz'));
    }

    public function test_has_column_returns_false_for_nonexistent_table(): void
    {
        $this->assertFalse(SchemaState::hasColumn('nonexistent_table_xyz', 'id'));
    }

    public function test_reset_clears_table_cache(): void
    {
        Schema::shouldReceive('hasTable')->with('sites')->andReturn(true)->twice();

        SchemaState::reset();
        SchemaState::hasTable('sites');
        SchemaState::reset();
        SchemaState::hasTable('sites');
        // Mockery will assert exactly two calls on teardown
    }

    public function test_reset_clears_column_cache(): void
    {
        Schema::shouldReceive('hasTable')->with('sites')->andReturn(true)->twice();
        Schema::shouldReceive('hasColumn')->with('sites', 'slug')->andReturn(true)->twice();

        SchemaState::reset();
        SchemaState::hasColumn('sites', 'slug');
        SchemaState::reset();
        SchemaState::hasColumn('sites', 'slug');
    }
}
