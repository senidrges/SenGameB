<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_the_privacy_policy_is_public(): void
    {
        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('Règles de confidentialité de LoveGames')
            ->assertSee('georgesseni1@gmail.com');
    }
}
