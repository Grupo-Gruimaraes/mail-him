<x-app-layout>
    <x-slot name="header" class="justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Iniciar Postbacks') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-md mx-auto pt-6 rounded-xl overflow-hidden md:max-w-2xl">
            <form action="/campaigns-postback-cron" method="post" class="space-y-4">
                @csrf
                
                <div class="flex flex-col justify-start">
                    <x-input-label for="campaign_id" :value="__('Selecione a Campanha')" />
                    <select id="campaign_id" name="campaign_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex flex-col justify-start mt-4">
                    <x-input-label for="postback_frequency" :value="__('Postbacks por')" />
                    <select id="postback_frequency" name="postback_frequency" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <option value="minute">Minuto</option>
                        <option value="hour">Hora</option>
                    </select>
                </div>
                
                <div class="flex flex-col justify-start mt-4">
                    <x-input-label for="postback_count" :value="__('Número de postbacks')" />
                    <x-text-input id="postback_count" class="block mt-1 w-full" type="number" name="postback_count" placeholder="Número de postbacks" required />
                </div>
                
                <div class="mt-4">
                    <x-primary-button class="w-full justify-center">
                        {{ __('Iniciar Postbacks') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
