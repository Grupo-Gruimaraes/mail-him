<x-guest-layout>
    <x-two-factor-header title="Recuperação de conta">
        Clique nas suas palavras de recuperação na ordem em que você anotou. Se estiverem certas, a verificação atual é desativada e você cadastra um novo método.
    </x-two-factor-header>

    <div x-data="{ words: @js($words), selected: [] }">
        <div class="flex flex-wrap gap-2 p-3 min-h-[3.5rem] rounded-lg border border-gray-200 dark:border-gray-700">
            <template x-for="word in words.filter(w => ! selected.includes(w))" :key="word">
                <button type="button" x-on:click="selected.push(word)" x-text="word"
                    class="px-3 py-1 rounded-full text-sm bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-800 hover:bg-gray-700 dark:hover:bg-white"></button>
            </template>
        </div>

        <p class="mt-4 mb-2 text-sm text-gray-600 dark:text-gray-400">Sua ordem (clique numa palavra para tirá-la):</p>
        <div class="flex flex-wrap gap-2 p-3 min-h-[3.5rem] rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
            <template x-for="(word, i) in selected" :key="word">
                <button type="button" x-on:click="selected.splice(i, 1)" x-text="(i + 1) + '. ' + word"
                    class="px-3 py-1 rounded-full text-sm bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-100"></button>
            </template>
        </div>

        <form method="POST" action="{{ route('two-factor.recovery') }}" class="mt-4">
            @csrf
            <input type="hidden" name="words" x-bind:value="selected.join('-')">
            <x-input-error :messages="$errors->get('words')" class="mb-2" />

            <div class="flex items-center justify-between">
                <a href="{{ route('two-factor.challenge') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">Voltar</a>
                <x-primary-button x-bind:disabled="selected.length !== words.length" class="disabled:opacity-50">Confirmar</x-primary-button>
            </div>
        </form>
    </div>

    <p class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
        Perdeu as palavras também? Peça ao administrador para redefinir sua verificação em duas etapas.
    </p>
</x-guest-layout>
