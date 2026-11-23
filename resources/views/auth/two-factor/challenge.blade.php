<x-guest-layout>
    <x-two-factor-header title="Verificação em duas etapas">
        @if ($method === 'email')
            Enviamos um código para <strong>{{ $email }}</strong>. Digite-o abaixo para entrar.
        @else
            Abra seu aplicativo autenticador e digite o código do {{ config('app.name') }}.
        @endif
    </x-two-factor-header>

    <x-auth-session-status class="mb-4" :status="session('status')" />
    @if ($sendError)
        <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $sendError }}</p>
    @endif

    <form method="POST" action="{{ route('two-factor.challenge') }}">
        @csrf
        @include('auth.two-factor.partials.code-input')

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('two-factor.recovery') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
                Não tenho acesso
            </a>
            <x-primary-button>Entrar</x-primary-button>
        </div>
    </form>

    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        @include('auth.two-factor.partials.cancel')
        @if ($method === 'email')
            @include('auth.two-factor.partials.resend')
        @endif
    </div>
</x-guest-layout>
