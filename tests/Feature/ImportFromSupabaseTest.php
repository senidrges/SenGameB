<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImportFromSupabaseTest extends TestCase
{
    public function test_the_import_explains_how_to_configure_the_source_database(): void
    {
        config(['database.connections.supabase.url' => null]);

        $this->artisan('supabase:import')
            ->expectsOutputToContain('SUPABASE_DB_URL')
            ->assertFailed();
    }
}
