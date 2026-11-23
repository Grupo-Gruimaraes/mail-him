<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor)
    {
    }

    /**
     * Pede o código no login de quem já tem 2FA.
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $this->twoFactor->pendingUser($request);
        if (! $user) {
            return $this->expired($request);
        }
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $sendError = null;
        if ($user->two_factor_method === TwoFactor::METHOD_EMAIL && ! $this->twoFactor->hasActiveEmailCode($user)) {
            $sendError = $this->sendEmailCode($user);
        }

        return view('auth.two-factor.challenge', [
            'method' => $user->two_factor_method,
            'email' => $this->twoFactor->maskEmail($user->email),
            'resendIn' => $this->twoFactor->resendAvailableIn($user),
            'sendError' => $sendError,
        ]);
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $user = $this->twoFactor->pendingUser($request);
        if (! $user) {
            return $this->expired($request);
        }
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $code = $this->validatedCode($request);
        $this->ensureNotLocked('two-factor:'.$user->id);

        if (! $this->twoFactor->verifyLoginCode($user, $code)) {
            $this->failed('two-factor:'.$user->id);
        }

        $this->twoFactor->clearAttempts('two-factor:'.$user->id);
        $this->twoFactor->completeLogin($request, $user);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->twoFactor->pendingUser($request);
        $setupUser = $this->setupUser($request);
        $setupMethod = $request->session()->get('two_factor.setup.method');

        $user = match (true) {
            $pending?->hasTwoFactorEnabled() && $pending->two_factor_method === TwoFactor::METHOD_EMAIL => $pending,
            $setupUser !== null && $setupMethod === TwoFactor::METHOD_EMAIL => $setupUser,
            default => null,
        };
        if (! $user) {
            return $this->expired($request);
        }

        if ($wait = $this->twoFactor->resendAvailableIn($user)) {
            return back()->withErrors(['code' => "Aguarde {$wait} segundos para pedir outro código."]);
        }

        if ($error = $this->sendEmailCode($user)) {
            return back()->withErrors(['code' => $error]);
        }

        return back()->with('status', 'Enviamos um novo código para '.$this->twoFactor->maskEmail($user->email).'.');
    }

    /**
     * "Não tenho acesso": recupera a conta com as palavras de recuperação na ordem.
     */
    public function recovery(Request $request): View|RedirectResponse
    {
        $user = $this->twoFactor->pendingUser($request);
        if (! $user) {
            return $this->expired($request);
        }
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor.recovery', [
            'words' => $this->twoFactor->shuffledRecoveryWords($user),
        ]);
    }

    public function verifyRecovery(Request $request): RedirectResponse
    {
        $user = $this->twoFactor->pendingUser($request);
        if (! $user) {
            return $this->expired($request);
        }
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $request->validate(['words' => ['required', 'string']], [
            'words.required' => 'Clique nas palavras na ordem em que você guardou.',
        ]);

        $key = 'two-factor-recovery:'.$user->id;
        $this->ensureNotLocked($key, 'words');

        if (! $this->twoFactor->verifyRecoveryWords($user, $request->input('words'))) {
            $this->twoFactor->hit($key, 3600);

            throw ValidationException::withMessages(['words' => 'As palavras não conferem. Confira a ordem e tente de novo.']);
        }

        $this->twoFactor->clearAttempts($key);
        $this->twoFactor->disable($user);
        Log::info('2FA desativado com palavras de recuperação', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return redirect()->route('two-factor.setup')
            ->with('status', 'Verificação em duas etapas desativada. Cadastre um novo método para continuar.');
    }

    /**
     * Escolha do método (obrigatória para quem ainda não tem 2FA).
     */
    public function setup(Request $request): View|RedirectResponse
    {
        if ($this->twoFactor->pendingUser($request)?->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        $user = $this->setupUser($request);
        if (! $user) {
            return $this->expired($request);
        }

        return view('auth.two-factor.setup', [
            'changing' => $this->isChanging($request, $user),
        ]);
    }

    public function startSetup(Request $request): RedirectResponse
    {
        $user = $this->setupUser($request);
        if (! $user) {
            return $this->expired($request);
        }

        $method = $request->validate(['method' => ['required', 'in:app,email']])['method'];

        if ($method === TwoFactor::METHOD_APP) {
            $request->session()->put('two_factor.setup', [
                'method' => TwoFactor::METHOD_APP,
                'secret' => $this->twoFactor->generateSecret(),
            ]);

            return redirect()->route('two-factor.setup.confirm');
        }

        $request->session()->put('two_factor.setup', ['method' => TwoFactor::METHOD_EMAIL]);

        if ($this->twoFactor->resendAvailableIn($user) === 0 && $error = $this->sendEmailCode($user)) {
            return back()->withErrors(['method' => $error]);
        }

        return redirect()->route('two-factor.setup.confirm');
    }

    public function confirmSetup(Request $request): View|RedirectResponse
    {
        $user = $this->setupUser($request);
        if (! $user) {
            return $this->expired($request);
        }

        $setup = $request->session()->get('two_factor.setup');
        if (! $setup) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor.confirm', [
            'method' => $setup['method'],
            'qrCode' => $setup['method'] === TwoFactor::METHOD_APP ? $this->twoFactor->qrCodeSvg($user, $setup['secret']) : null,
            'secret' => $setup['method'] === TwoFactor::METHOD_APP ? trim(chunk_split($setup['secret'], 4, ' ')) : null,
            'email' => $this->twoFactor->maskEmail($user->email),
            'resendIn' => $this->twoFactor->resendAvailableIn($user),
        ]);
    }

    public function verifySetup(Request $request): RedirectResponse
    {
        $user = $this->setupUser($request);
        if (! $user) {
            return $this->expired($request);
        }

        $setup = $request->session()->get('two_factor.setup');
        if (! $setup) {
            return redirect()->route('two-factor.setup');
        }

        $code = $this->validatedCode($request);
        $key = 'two-factor:'.$user->id;
        $this->ensureNotLocked($key);

        $timestep = null;
        $valid = $setup['method'] === TwoFactor::METHOD_APP
            ? ($timestep = $this->twoFactor->verifyAppCode($setup['secret'], $code)) !== null
            : $this->twoFactor->verifyEmailCode($user, $code);

        if (! $valid) {
            $this->failed($key);
        }

        $this->twoFactor->clearAttempts($key);
        $changing = $this->isChanging($request, $user);
        $words = $this->twoFactor->enable($user, $setup['method'], $setup['secret'] ?? null, $timestep);
        $request->session()->forget(['two_factor.setup', 'two_factor.changing']);

        if ($changing) {
            $request->session()->put('two_factor.return_to', route('profile.edit'));
        } else {
            $this->twoFactor->completeLogin($request, $user);
        }

        $request->session()->put('two_factor.recovery_words', $words);

        return redirect()->route('two-factor.recovery-words');
    }

    /**
     * Mostra as palavras de recuperação uma única vez, logo depois da ativação.
     */
    public function recoveryWords(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $words = $request->session()->get('two_factor.recovery_words');

        if (! $user || ! $words || ! $this->twoFactor->hasPassed($request, $user)) {
            return redirect(RouteServiceProvider::HOME);
        }

        return view('auth.two-factor.recovery-words', ['words' => $words]);
    }

    public function finish(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor.recovery_words');
        $returnTo = $request->session()->pull('two_factor.return_to');

        return $returnTo
            ? redirect($returnTo)->with('status', 'two-factor-updated')
            : redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Troca de método a partir do perfil (rota protegida por senha).
     */
    public function change(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor.setup');
        $request->session()->put('two_factor.changing', true);

        return redirect()->route('two-factor.setup');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['two_factor.setup', 'two_factor.pending']);

        if ($request->session()->pull('two_factor.changing') && $request->user()) {
            return redirect()->route('profile.edit');
        }

        return redirect()->route('login');
    }

    private function setupUser(Request $request): ?User
    {
        if ($pending = $this->twoFactor->pendingUser($request)) {
            return $pending->hasTwoFactorEnabled() ? null : $pending;
        }

        $user = $request->user();

        return $user && $this->isChanging($request, $user) ? $user : null;
    }

    private function isChanging(Request $request, User $user): bool
    {
        return $request->user()?->is($user)
            && $this->twoFactor->hasPassed($request, $user)
            && $request->session()->get('two_factor.changing') === true;
    }

    private function validatedCode(Request $request): string
    {
        $request->merge(['code' => preg_replace('/\D/', '', (string) $request->input('code'))]);
        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Digite o código de 6 dígitos.',
            'code.digits' => 'O código tem 6 dígitos.',
        ]);

        return $request->input('code');
    }

    private function ensureNotLocked(string $key, string $field = 'code'): void
    {
        if ($seconds = $this->twoFactor->tooManyAttempts($key)) {
            throw ValidationException::withMessages([
                $field => 'Muitas tentativas. Tente de novo em '.ceil($seconds / 60).' min.',
            ]);
        }
    }

    private function failed(string $key): never
    {
        $this->twoFactor->hit($key);

        throw ValidationException::withMessages(['code' => 'Código inválido ou expirado.']);
    }

    private function sendEmailCode(User $user): ?string
    {
        try {
            $this->twoFactor->sendEmailCode($user);

            return null;
        } catch (Throwable $e) {
            Log::error('Falha ao enviar código de 2FA por e-mail', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return 'Não foi possível enviar o código agora. Tente de novo em instantes.';
        }
    }

    private function expired(Request $request): RedirectResponse
    {
        if ($request->user() && $this->twoFactor->hasPassed($request, $request->user())) {
            return redirect(RouteServiceProvider::HOME);
        }

        return redirect()->route('login')->with('status', 'Sua verificação expirou. Entre novamente.');
    }
}
