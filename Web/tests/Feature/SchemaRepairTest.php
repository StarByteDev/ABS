<?php

namespace Tests\Feature;

use App\Support\AbsSchemaRepair;
use App\Support\LegacyMigrationBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_recreates_a_missing_products_table(): void
    {
        Schema::dropIfExists('products');

        $this->assertFalse(Schema::hasTable('products'));

        AbsSchemaRepair::repair();

        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(AbsSchemaRepair::diagnose()['ready']);
    }

    public function test_legacy_create_migration_is_baselined_when_its_tables_already_exist(): void
    {
        DB::table('migrations')
            ->where('migration', '0001_01_01_000000_create_users_table')
            ->delete();

        $result = LegacyMigrationBaseline::synchronize();

        $this->assertContains('0001_01_01_000000_create_users_table', $result['baselined']);
        $this->assertDatabaseHas('migrations', [
            'migration' => '0001_01_01_000000_create_users_table',
        ]);
    }

    public function test_api_returns_controlled_setup_response_when_schema_is_incomplete(): void
    {
        Schema::dropIfExists('products');
        Cache::forget('abs.installation.diagnosis.v12.2');

        $this->getJson('/api/v1/products')
            ->assertStatus(503)
            ->assertJsonPath('repair_command', 'php artisan abs:repair --seed');
    }
}
