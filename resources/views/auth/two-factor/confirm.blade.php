<x-guest-layout>
    @if ($method === 'app')
        <x-two-factor-header title="Configure o aplicativo autenticador">
            1. No aplicativo, adicione uma conta e escaneie o QR Code abaixo.
        </x-two-factor-header>

        <div class="flex justify-center">
            <div class="p-3 bg-white rounded-lg border border-gray-200">{!! $qrCode !!}</div>
        </div>

        <div class="mt-4 text-center" x-data="{ copied: false }">
            <p class="text-xs text-gray-600 dark:text-gray-400">Não consegue escanear? Digite esta chave no aplicativo:</p>
            <p class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 break-all" x-ref="secret">{{ $secret }}</p>
            <button type="button" class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 underline"
                x-on:click="navigator.clipboard.writeText($refs.secret.innerText.replace(/\s/g, '')); copied = true; setTimeout(() => copied = false, 2000)"
                x-text="copied ? 'Copiada!' : 'Copiar chave'">Copiar chave</button>
        </div>

        <p class="mt-6 mb-2 text-sm text-gray-600 dark:text-gray-400">2. Digite o código que aparece no aplicativo para confirmar.</p>
    @else
        <x-two-factor-header title="Confirme seu e-mail">
            Enviamos um código para <strong>{{ $email }}</strong>. Digite-o abaixo para ativar a verificação por e-mail.
        </x-two-factor-header>
    @endif

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('two-factor.setup.confirm') }}">
        @csrf
        @include('auth.two-factor.partials.code-input')

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('two-factor.setup') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
                Escolher outro método
            </a>
            <x-primary-button>Ativar</x-primary-button>
        </div>
    </form>

    @if ($method === 'email')
        <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            @include('auth.two-factor.partials.resend')
        </div>
    @endif
</x-guest-layout>
