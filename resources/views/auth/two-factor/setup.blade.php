<x-guest-layout>
    <x-two-factor-header :title="$changing ? 'Trocar método de verificação' : 'Ative a verificação em duas etapas'">
        @if ($changing)
            Escolha o novo método. O atual continua valendo até você confirmar o novo.
        @else
            Para proteger sua conta, o acesso ao {{ config('app.name') }} agora exige um código além da senha. Escolha como quer receber esse código:
        @endif
    </x-two-factor-header>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('two-factor.setup') }}" class="space-y-3">
        @csrf
        <button type="submit" name="method" value="app"
            class="w-full text-left p-4 rounded-lg border border-gray-300 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-500 hover:bg-gray-50 dark:hover:bg-gray-900 transition">
            <span class="flex items-center justify-between">
                <span class="font-semibold text-gray-900 dark:text-gray-100">Aplicativo autenticador</span>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200">Recomendado</span>
            </span>
            <span class="block mt-1 text-sm text-gray-600 dark:text-gray-400">
                Google Authenticator, Microsoft Authenticator, Authy ou similar. Funciona mesmo sem internet no celular.
            </span>
        </button>

        <button type="submit" name="method" value="email"
            class="w-full text-left p-4 rounded-lg border border-gray-300 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-500 hover:bg-gray-50 dark:hover:bg-gray-900 transition">
            <span class="font-semibold text-gray-900 dark:text-gray-100">E-mail</span>
            <span class="block mt-1 text-sm text-gray-600 dark:text-gray-400">
                Receba um código no seu e-mail a cada acesso.
            </span>
        </button>
    </form>
    <x-input-error :messages="$errors->get('method')" class="mt-2" />

    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        @include('auth.two-factor.partials.cancel', ['label' => $changing ? 'Cancelar' : 'Voltar para o login'])
    </div>
</x-guest-layout>
