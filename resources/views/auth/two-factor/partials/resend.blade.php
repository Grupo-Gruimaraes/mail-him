<form method="POST" action="{{ route('two-factor.resend') }}"
    x-data="{ wait: {{ (int) $resendIn }} }" x-init="setInterval(() => { if (wait > 0) wait-- }, 1000)">
    @csrf
    <button type="submit" x-bind:disabled="wait > 0"
        class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100 disabled:no-underline disabled:opacity-60"
        x-text="wait > 0 ? 'Reenviar código em ' + wait + 's' : 'Reenviar código'">Reenviar código</button>
</form>
