<x-guest-layout>
    <h1 class="text-3xl text-center mb-4 font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Nova Campanha</h1>

    <div class="max-w-md mx-auto pt-6 rounded-xl overflow-hidden md:max-w-2xl">
        <form method="POST" action="{{ route('campaigns.store') }}" class="space-y-4" enctype="multipart/form-data">
            @csrf

            <div class="flex flex-col justify-start">
                <x-input-label for="name" :value="__('Nome da campanha')" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="flex flex-col justify-start mt-4">
                <x-input-label for="csv_file" :value="__('Arquivo CSV')" />
                <x-text-input id="csv_file" class="block mt-1 w-full" type="file" name="csv_file" required />
                <x-input-error :messages="$errors->get('csv_file')" class="mt-2" />
            </div>

            <div class="flex flex-col justify-start mt-4">
                <x-input-label for="webhook_url" :value="__('Webhook URL')" />
                <x-text-input id="webhook_url" class="block mt-1 w-full" type="url" name="webhook_url" required />
                <x-input-error :messages="$errors->get('webhook_url')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-primary-button class="w-full justify-center">
                    {{ __('Cadastrar') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
