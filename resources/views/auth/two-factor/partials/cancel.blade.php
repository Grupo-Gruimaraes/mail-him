<form method="POST" action="{{ route('two-factor.cancel') }}">
    @csrf
    <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
        {{ $label ?? 'Voltar para o login' }}
    </button>
</form>
