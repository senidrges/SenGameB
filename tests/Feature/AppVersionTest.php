<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_the_app_learns_the_play_store_version_without_logging_in(): void
    {
        config([
            'services.play_store.latest_version' => '1.2.0',
            'services.play_store.min_version' => '1.0.0',
        ]);

        $this->getJson('/api/v1/app-version')
            ->assertOk()
            ->assertExactJson(['android' => [
                'latest_version' => '1.2.0',
                'min_version' => '1.0.0',
                'store_url' => 'https://play.google.com/store/apps/details?id=com.sedrge.LoveGames',
            ]]);
    }
}
