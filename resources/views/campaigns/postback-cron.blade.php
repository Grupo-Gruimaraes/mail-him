<x-app-layout>
    <x-slot name="header" class="justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Iniciar Postbacks') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="/campaigns-postback-cron" method="post" class="space-y-4">
                        @csrf
                        <div class="flex flex-col justify-start">
                            <x-input-label for="campaign_id" :value="__('Selecione a Campanha')" />
                            <select id="campaign_id" name="campaign_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ $campaign->id == $selectedCampaign->id ? 'selected' : '' }}>
                                        {{ $campaign->name}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <x-input-error :messages="$errors->get('campaign_id')" class="mt-2" />

                        @php($timezone = config('app.display_timezone'))
                        @php($frequency = old('postback_frequency', $selectedCampaign->postback_frequency))
                        <div class="flex flex-col justify-start mt-4">
                            <x-input-label for="postback_frequency" :value="__('Postbacks por')" />
                            <select id="postback_frequency" name="postback_frequency" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="minute" {{ $frequency !== 'hour' ? 'selected' : '' }}>Minuto</option>
                                <option value="hour" {{ $frequency === 'hour' ? 'selected' : '' }}>Hora</option>
                            </select>
                        </div>

                        <div class="flex flex-col justify-start mt-4">
                            <x-input-label for="postback_count" :value="__('Número de postbacks')" />
                            <x-text-input id="postback_count" class="block mt-1 w-full" type="number" min="1" name="postback_count" placeholder="Número de postbacks" :value="old('postback_count', $selectedCampaign->postback_count)" required />
                            <x-input-error :messages="$errors->get('postback_count')" class="mt-2" />
                        </div>

                        <div class="flex flex-col justify-start mt-4">
                            <x-input-label for="starts_at" :value="__('Início (opcional)')" />
                            <x-text-input id="starts_at" class="block mt-1 w-full" type="datetime-local" name="starts_at" :value="old('starts_at', $selectedCampaign->starts_at?->setTimezone($timezone)->format('Y-m-d\TH:i'))" />
                            <p class="mt-1 text-sm text-gray-500">Em branco, começa agora.</p>
                        </div>

                        <div class="flex flex-col justify-start mt-4">
                            <x-input-label for="ends_at" :value="__('Término (opcional)')" />
                            <x-text-input id="ends_at" class="block mt-1 w-full" type="datetime-local" name="ends_at" :value="old('ends_at', $selectedCampaign->ends_at?->setTimezone($timezone)->format('Y-m-d\TH:i'))" />
                            <p class="mt-1 text-sm text-gray-500">No horário de término o envio para, mesmo que ainda tenha leads.</p>
                            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-primary-button class="w-full justify-center">
                                {{ __('Iniciar Postbacks') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
