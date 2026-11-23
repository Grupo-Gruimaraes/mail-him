<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Providers\RouteServiceProvider;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, bool $remember = false)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => $remember ? 'on' : null]);
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /**
     * @return array{0: User, 1: string, 2: string[]}
     */
    private function userWithApp(): array
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->create();
        $words = app(TwoFactor::class)->enable($user, TwoFactor::METHOD_APP, $secret);

        return [$user->fresh(), $secret, $words];
    }

    private function sentCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, TwoFactorCode::class, function (TwoFactorCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    public function test_user_without_two_factor_must_set_it_up_before_accessing(): void
    {
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('two-factor.setup'));
        $this->assertGuest();

        $this->get('/campaigns')->assertRedirect(route('login'));
        $this->get(route('two-factor.setup'))->assertOk()->assertSee('Aplicativo autenticador')->assertSee('E-mail');
        $this->get(route('two-factor.challenge'))->assertRedirect(route('two-factor.setup'));
    }

    public function test_setup_with_authenticator_app(): void
    {
        $user = User::factory()->create();
        $this->login($user);

        $this->post(route('two-factor.setup'), ['method' => 'app'])->assertRedirect(route('two-factor.setup.confirm'));
        $this->get(route('two-factor.setup.confirm'))->assertOk()->assertSee('<svg', false);

        $secret = session('two_factor.setup.secret');

        $this->post(route('two-factor.setup.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.setup.confirm'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('two-factor.recovery-words'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame('app', $user->two_factor_method);
        $this->assertSame($secret, $user->two_factor_secret);

        $words = explode('-', $user->two_factor_recovery_words);
        $this->assertCount(10, $words);
        $this->get(route('two-factor.recovery-words'))->assertOk()->assertSeeInOrder($words);

        $this->post(route('two-factor.recovery-words'))->assertRedirect(RouteServiceProvider::HOME);
        $this->get(route('two-factor.recovery-words'))->assertRedirect(RouteServiceProvider::HOME);
        $this->get('/campaigns')->assertOk();
    }

    public function test_setup_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->login($user);

        $this->post(route('two-factor.setup'), ['method' => 'email'])->assertRedirect(route('two-factor.setup.confirm'));
        $code = $this->sentCode($user);

        $this->post(route('two-factor.setup.confirm'), ['code' => $code])->assertRedirect(route('two-factor.recovery-words'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('email', $user->fresh()->two_factor_method);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_login_with_authenticator_app_and_codes_are_single_use(): void
    {
        [$user, $secret] = $this->userWithApp();

        $this->login($user)->assertRedirect(route('two-factor.challenge'));
        $this->get(route('two-factor.challenge'))->assertOk();
        $this->assertGuest();

        $this->post(route('two-factor.challenge'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = $this->otp($secret);
        $this->post(route('two-factor.challenge'), ['code' => substr($code, 0, 3).' '.substr($code, 3)])
            ->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->login($user);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_login_by_email_sends_code_once_and_allows_resend_after_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        app(TwoFactor::class)->enable($user, TwoFactor::METHOD_EMAIL);

        $this->login($user)->assertRedirect(route('two-factor.challenge'));
        $this->get(route('two-factor.challenge'))->assertOk();
        $this->get(route('two-factor.challenge'))->assertOk();
        Notification::assertSentToTimes($user, TwoFactorCode::class, 1);

        $this->post(route('two-factor.resend'))->assertSessionHasErrors('code');
        $this->travel(61)->seconds();
        $this->post(route('two-factor.resend'))->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, TwoFactorCode::class, 2);

        $code = null;
        Notification::assertSentTo($user, TwoFactorCode::class, function (TwoFactorCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
    }

    public function test_too_many_wrong_codes_lock_the_user(): void
    {
        [$user, $secret] = $this->userWithApp();
        $this->login($user);

        foreach (range(1, 5) as $i) {
            $this->post(route('two-factor.challenge'), ['code' => '000000']);
        }

        $this->post(route('two-factor.challenge'), ['code' => $this->otp($secret)])
            ->assertSessionHasErrors(['code' => 'Muitas tentativas. Tente de novo em 5 min.']);
        $this->assertGuest();
    }

    public function test_recovery_words_disable_two_factor_and_force_new_setup(): void
    {
        [$user, , $words] = $this->userWithApp();
        $this->login($user);

        $this->get(route('two-factor.recovery'))->assertOk();

        $this->post(route('two-factor.recovery'), ['words' => implode('-', array_reverse($words))])
            ->assertSessionHasErrors('words');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->post(route('two-factor.recovery'), ['words' => implode('-', $words)])
            ->assertRedirect(route('two-factor.setup'));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertGuest();
    }

    public function test_pending_login_expires(): void
    {
        [$user] = $this->userWithApp();
        $this->login($user);

        $this->travel(16)->minutes();

        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_session_without_second_factor_is_logged_out(): void
    {
        [$user] = $this->userWithApp();

        $this->be($user)->get('/campaigns')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_change_method_from_profile(): void
    {
        Notification::fake();
        [$user] = $this->userWithApp();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Trocar método');

        $this->get(route('two-factor.change'))->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.change'))->assertRedirect(route('two-factor.setup'));

        $this->post(route('two-factor.setup'), ['method' => 'email']);
        $this->post(route('two-factor.setup.confirm'), ['code' => $this->sentCode($user)])
            ->assertRedirect(route('two-factor.recovery-words'));

        $this->assertSame('email', $user->fresh()->two_factor_method);
        $this->post(route('two-factor.recovery-words'))->assertRedirect(route('profile.edit'));
    }

    public function test_setup_is_not_reachable_without_login_or_profile_change(): void
    {
        $this->get(route('two-factor.setup'))->assertRedirect(route('login'));

        [$user] = $this->userWithApp();
        $this->actingAs($user)->get(route('two-factor.setup'))->assertRedirect(RouteServiceProvider::HOME);
        $this->post(route('two-factor.setup'), ['method' => 'app'])->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_reset_command(): void
    {
        [$user] = $this->userWithApp();

        $this->artisan('two-factor:reset', ['email' => $user->email])->assertSuccessful();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
