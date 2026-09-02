<?php

namespace Tests;

use App\Support\AbsSchemaRepair;
use App\Support\LegacyMigrationBaseline;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests use the reviewed schema reconciler instead of executing every
     * historical migration found in a developer's working folder. This prevents
     * stale legacy create-users migrations from colliding with the current schema
     * when PHPUnit uses its isolated in-memory test database. The application runtime is MySQL-first.
     */
    protected function migrateDatabases(): void
    {
        $this->artisan('db:wipe', ['--force' => true]);
        AbsSchemaRepair::repair();
        LegacyMigrationBaseline::synchronize();
    }
}
