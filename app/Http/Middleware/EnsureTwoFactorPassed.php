<?php

namespace App\Http\Middleware;

use App\Services\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorPassed
{
    public function __construct(private TwoFactor $twoFactor)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $this->twoFactor->hasPassed($request, $user)) {
            return $next($request);
        }

        // O cookie de "lembrar de mim" só é emitido depois do segundo fator
        if (Auth::guard('web')->viaRemember() && $user->hasTwoFactorEnabled()) {
            $this->twoFactor->markPassed($request, $user);

            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Por segurança, entre novamente para confirmar a verificação em duas etapas.');
    }
}
