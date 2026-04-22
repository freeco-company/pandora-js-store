<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Guards the 3 Google OAuth callback branches. Regression lock for the
 * 2026-04-22 incident where first-time login 500'd because the code
 * tried to INSERT a customer with an email that already existed from a
 * guest-checkout row, hitting the unique constraint instead of linking.
 */
class GoogleOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $id, string $email, string $name = 'Test User'): void
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_new_user_gets_created(): void
    {
        $this->mockGoogleUser('google-111', 'newbie@example.com', 'Newbie');

        $response = $this->get('/api/auth/google/callback?code=fake');
        $response->assertRedirect();

        $customer = Customer::where('email', 'newbie@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame('google-111', $customer->google_id);
        $this->assertSame('Newbie', $customer->name);
    }

    public function test_returning_google_user_reuses_row(): void
    {
        Customer::create([
            'google_id' => 'google-222',
            'email' => 'regular@example.com',
            'name' => 'Regular',
            'membership_level' => 'regular',
            'password' => bcrypt('x'),
        ]);

        $this->mockGoogleUser('google-222', 'regular@example.com', 'Regular Updated');

        $this->get('/api/auth/google/callback?code=fake')->assertRedirect();

        $this->assertSame(1, Customer::where('email', 'regular@example.com')->count());
    }

    public function test_existing_email_user_gets_google_linked(): void
    {
        // Customer previously created via guest checkout — has email but no google_id.
        $existing = Customer::create([
            'google_id' => null,
            'email' => 'guest@example.com',
            'name' => '',
            'membership_level' => 'regular',
            'password' => bcrypt('x'),
        ]);

        $this->mockGoogleUser('google-333', 'guest@example.com', 'Real Name');

        $response = $this->get('/api/auth/google/callback?code=fake');
        $response->assertRedirect();
        // Must not 500 — was the original bug.
        $this->assertNotEquals(500, $response->status());

        // Same row, now linked
        $this->assertSame(1, Customer::where('email', 'guest@example.com')->count());
        $existing->refresh();
        $this->assertSame('google-333', $existing->google_id);
        $this->assertSame('Real Name', $existing->name);
    }
}
