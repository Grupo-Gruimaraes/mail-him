<x-guest-layout>
    <x-two-factor-header title="Guarde suas palavras de recuperação">
        Verificação em duas etapas ativada. Se um dia você perder acesso ao método escolhido, estas 10 palavras, nesta ordem, liberam sua conta. Anote em um lugar seguro e não compartilhe: elas não aparecem de novo.
    </x-two-factor-header>

    <ol class="grid grid-cols-2 gap-2">
        @foreach ($words as $i => $word)
            <li class="px-3 py-2 rounded-md bg-gray-100 dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100">
                <span class="text-gray-500 dark:text-gray-400">{{ $i + 1 }}.</span> {{ $word }}
            </li>
        @endforeach
    </ol>

    <div class="mt-3 text-center" x-data="{ copied: false }">
        <button type="button" class="text-sm text-indigo-600 dark:text-indigo-400 underline"
            x-on:click="navigator.clipboard.writeText(@js(implode(' ', $words))); copied = true; setTimeout(() => copied = false, 2000)"
            x-text="copied ? 'Copiadas!' : 'Copiar palavras'">Copiar palavras</button>
    </div>

    <form method="POST" action="{{ route('two-factor.recovery-words') }}" class="mt-6" x-data="{ saved: false }">
        @csrf
        <label class="inline-flex items-center">
            <input type="checkbox" x-model="saved"
                class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Anotei as palavras em um lugar seguro</span>
        </label>

        <div class="flex justify-end mt-4">
            <x-primary-button x-bind:disabled="!saved" class="disabled:opacity-50">Continuar</x-primary-button>
        </div>
    </form>
</x-guest-layout>
