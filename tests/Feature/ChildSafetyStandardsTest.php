<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChildSafetyStandardsTest extends TestCase
{
    public function test_the_child_safety_standards_are_public(): void
    {
        $this->get('/securite-enfants')
            ->assertOk()
            ->assertSee('Normes de sécurité des enfants de LoveGames')
            ->assertSee('georgesseni1@gmail.com');
    }
}
