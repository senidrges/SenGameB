<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_account_without_profile_and_returns_tokens(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'Alice@Example.com ',
            'password' => 'secret123',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'phone_number' => '70 12 34 56',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_at', 'user' => ['id', 'email']])
            ->assertJsonPath('user.email', 'alice@example.com')
            ->assertJsonPath('profile', null);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com', 'phone_number' => '+22670123456']);
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'pas-un-email', 'password' => 'secret123'])
            ->assertJsonValidationErrors(['email' => 'Le champ email doit être une adresse email valide.']);
    }

    public function test_registration_rejects_an_incomplete_phone_number(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'alice@example.com',
            'password' => 'secret123',
            'phone_number' => '70 12 34',
        ])->assertJsonValidationErrors(['phone_number' => 'Numéro de téléphone invalide. Exemple : 70 12 34 56.']);
    }

    public function test_registration_rejects_an_email_already_used(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/auth/register', ['email' => 'alice@example.com', 'password' => 'secret123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Cet email est déjà utilisé. Veuillez vous connecter.']);
    }

    public function test_login_returns_tokens_and_profile(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'alice@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('profile.username', $user->username);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'alice@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    public function test_password_hashes_imported_from_supabase_are_accepted(): void
    {
        $supabaseHash = str_replace('$2y$', '$2a$', password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 10]));
        $user = User::factory()->create();
        // Written as the import does: raw, bypassing the "hashed" cast.
        DB::table('users')->where('id', $user->id)->update(['password' => preg_replace('/^\$2[ab]\$/', '\$2y\$', $supabaseHash)]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret123'])->assertOk();

        $this->assertFalse(Hash::needsRehash($user->fresh()->password), 'The imported hash is upgraded to the app cost on login.');
    }

    public function test_refresh_rotates_the_token_pair(): void
    {
        $tokens = $this->login();

        $refreshed = $this->withToken($tokens['refresh_token'])->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->json();

        $this->assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);

        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['refresh_token'])->postJson('/api/v1/auth/refresh')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['access_token'])->getJson('/api/v1/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($refreshed['access_token'])->getJson('/api/v1/me')->assertOk();
    }

    public function test_tokens_cannot_be_used_for_the_other_purpose(): void
    {
        $tokens = $this->login();

        $this->withToken($tokens['refresh_token'])->getJson('/api/v1/me')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['access_token'])->postJson('/api/v1/auth/refresh')->assertForbidden();
    }

    public function test_expired_access_tokens_are_rejected(): void
    {
        $tokens = $this->login();

        $this->travel(config('sanctum.access_token_ttl') + 1)->minutes();

        $this->withToken($tokens['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_revokes_both_tokens_of_the_device(): void
    {
        $tokens = $this->login();

        $this->withToken($tokens['access_token'])->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guests_cannot_reach_protected_routes(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_phone_lookup_only_tells_whether_the_number_exists(): void
    {
        User::factory()->create(['phone_number' => '+22670123456']);

        $this->postJson('/api/v1/auth/phone-lookup', ['phone_number' => '70 12 34 56'])
            ->assertExactJson(['exists' => true]);

        $this->postJson('/api/v1/auth/phone-lookup', ['phone_number' => '76999999'])
            ->assertExactJson(['exists' => false]);
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function login(): array
    {
        $user = User::factory()->create();

        $tokens = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->json();
        $this->app['auth']->forgetGuards();

        return $tokens;
    }
}
