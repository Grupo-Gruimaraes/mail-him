<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public const METHOD_APP = 'app';
    public const METHOD_EMAIL = 'email';

    public const MAX_ATTEMPTS = 5;
    public const ATTEMPTS_DECAY = 300;
    public const PENDING_MINUTES = 15;
    public const EMAIL_CODE_MINUTES = 10;
    public const RESEND_SECONDS = 60;

    private const WORDS = [
        'Árvore', 'Maravilha', 'Eclipse', 'Trovão', 'Galáxia', 'Fênix', 'Relâmpago', 'Estrela', 'Horizonte',
        'Miragem', 'Aurora', 'Vulcão', 'Esmeralda', 'Safira', 'Rubi', 'Deserto', 'Oceano', 'Caverna', 'Neve',
        'Tempestade', 'Areia', 'Cristal', 'Floresta', 'Luar', 'Rocha', 'Vento', 'Névoa', 'Prisma', 'Montanha',
        'Átomo', 'Cometa', 'Orvalho', 'Planeta', 'Satélite', 'Universo', 'Labirinto', 'Gelo', 'Fulgor',
        'Cascata', 'Reflexo', 'Estalagmite', 'Nebulosa', 'Raio', 'Magma', 'Esfera', 'Fronteira', 'Terremoto',
        'Maré', 'Eco', 'Pântano', 'Lagoa', 'Penhasco', 'Fóssil', 'Tsunami', 'Vale', 'Caminho', 'Abismo',
        'Brisa', 'Diamante',
    ];

    public function __construct(private Google2FA $google2fa)
    {
    }

    // Login em andamento: senha conferida, falta o segundo fator

    public function startPending(Request $request, User $user, bool $remember): void
    {
        $request->session()->put('two_factor.pending', [
            'id' => $user->id,
            'remember' => $remember,
            'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
        ]);
    }

    public function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor.pending');

        if (! $pending || $pending['expires_at'] < now()->timestamp) {
            $request->session()->forget('two_factor.pending');

            return null;
        }

        return User::find($pending['id']);
    }

    public function completeLogin(Request $request, User $user): void
    {
        $remember = (bool) $request->session()->pull('two_factor.pending.remember', false);
        $request->session()->forget('two_factor.pending');

        Auth::guard('web')->login($user, $remember);
        $this->markPassed($request, $user);
    }

    public function markPassed(Request $request, User $user): void
    {
        $request->session()->put('two_factor.passed', $user->id);
    }

    public function hasPassed(Request $request, User $user): bool
    {
        return $request->session()->get('two_factor.passed') === $user->id;
    }

    // Aplicativo autenticador

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd()));

        return trim(preg_replace('/^<\?xml.*?\?>/', '', $writer->writeString($url)));
    }

    /**
     * Devolve o passo de tempo do código aceito, ou null. Códigos já usados não valem de novo.
     */
    public function verifyAppCode(string $secret, string $code, ?int $lastTimestep = null): ?int
    {
        $timestep = $this->google2fa->verifyKeyNewer($secret, $code, $lastTimestep ?? 0, 1);

        return $timestep === false ? null : (int) $timestep;
    }

    // Código por e-mail

    public function sendEmailCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->notify(new TwoFactorCode($code, self::EMAIL_CODE_MINUTES));

        Cache::put($this->emailCacheKey($user), [
            'hash' => hash_hmac('sha256', $code, config('app.key')),
            'sent_at' => now()->timestamp,
        ], now()->addMinutes(self::EMAIL_CODE_MINUTES));
    }

    public function hasActiveEmailCode(User $user): bool
    {
        return Cache::has($this->emailCacheKey($user));
    }

    public function resendAvailableIn(User $user): int
    {
        $sentAt = Cache::get($this->emailCacheKey($user))['sent_at'] ?? 0;

        return max(0, $sentAt + self::RESEND_SECONDS - now()->timestamp);
    }

    public function verifyEmailCode(User $user, string $code): bool
    {
        $stored = Cache::get($this->emailCacheKey($user));

        if (! $stored || ! hash_equals($stored['hash'], hash_hmac('sha256', $code, config('app.key')))) {
            return false;
        }

        Cache::forget($this->emailCacheKey($user));

        return true;
    }

    // Verificação do código no login (aplicativo ou e-mail)

    public function verifyLoginCode(User $user, string $code): bool
    {
        if ($user->two_factor_method === self::METHOD_EMAIL) {
            return $this->verifyEmailCode($user, $code);
        }

        $timestep = $this->verifyAppCode($user->two_factor_secret, $code, $user->two_factor_last_timestep);
        if ($timestep === null) {
            return false;
        }

        $user->forceFill(['two_factor_last_timestep' => $timestep])->save();

        return true;
    }

    // Ativação e palavras de recuperação

    /**
     * @return string[] palavras de recuperação, na ordem
     */
    public function enable(User $user, string $method, ?string $secret = null, ?int $timestep = null): array
    {
        $words = collect(self::WORDS)->shuffle()->take(10)->values()->all();

        $user->forceFill([
            'two_factor_method' => $method,
            'two_factor_secret' => $method === self::METHOD_APP ? $secret : null,
            'two_factor_recovery_words' => implode('-', $words),
            'two_factor_last_timestep' => $timestep,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $words;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_words' => null,
            'two_factor_last_timestep' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Cache::forget($this->emailCacheKey($user));
    }

    /**
     * @return string[]
     */
    public function shuffledRecoveryWords(User $user): array
    {
        return collect(explode('-', (string) $user->two_factor_recovery_words))->shuffle()->values()->all();
    }

    public function verifyRecoveryWords(User $user, string $words): bool
    {
        return $user->two_factor_recovery_words !== null
            && hash_equals($user->two_factor_recovery_words, $words);
    }

    // Limite de tentativas

    public function tooManyAttempts(string $key): ?int
    {
        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS) ? RateLimiter::availableIn($key) : null;
    }

    public function hit(string $key, int $decay = self::ATTEMPTS_DECAY): void
    {
        RateLimiter::hit($key, $decay);
    }

    public function clearAttempts(string $key): void
    {
        RateLimiter::clear($key);
    }

    public function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($name, 0, 2).str_repeat('*', max(3, mb_strlen($name) - 2)).'@'.$domain;
    }

    private function emailCacheKey(User $user): string
    {
        return 'two-factor-email:'.$user->id;
    }
}
