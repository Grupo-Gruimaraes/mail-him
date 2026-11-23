<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Verificação em duas etapas') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            @if ($user->hasTwoFactorEnabled())
                Ativa por {{ $user->two_factor_method === 'email' ? 'código no e-mail' : 'aplicativo autenticador' }}
                desde {{ $user->two_factor_confirmed_at->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') }}.
                Trocar o método também gera novas palavras de recuperação.
            @else
                Ainda não configurada.
            @endif
        </p>
    </header>

    <div class="flex items-center gap-4 mt-6">
        <a href="{{ route('two-factor.change') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
            {{ $user->hasTwoFactorEnabled() ? __('Trocar método') : __('Configurar') }}
        </a>

        @if (session('status') === 'two-factor-updated')
            <p
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 3000)"
                class="text-sm text-gray-600 dark:text-gray-400"
            >{{ __('Método atualizado.') }}</p>
        @endif
    </div>
</section>
