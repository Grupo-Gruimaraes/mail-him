<x-input-label for="code" :value="__('Código de 6 dígitos')" />
<x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
    maxlength="7" required autofocus
    class="block mt-1 w-full text-center text-2xl font-mono tracking-[0.5em]" />
<x-input-error :messages="$errors->get('code')" class="mt-2" />
