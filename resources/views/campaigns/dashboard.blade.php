    <x-app-layout>
        <x-slot name="header" class="justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>

            <a href="{{ url('/campaigns-create') }}">
                <x-primary-button>
                    {{ __('Nova Campanha')}}
                </x-primary-button>
            </a>
        </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-col divide-y divide-gray-200">
                        <div class="flex">
                            <div class="flex-1 p-4 font-bold">Campanha</div>
                            <div class="flex-1 p-4 font-bold">Data de Upload</div>
                            <div class="flex-1 p-4 font-bold">Estado de Envio</div>
                            <div class="flex-1 p-4 font-bold">Leads Enviados</div>
                            <div class="flex-1 p-4 font-bold">Total de Leads</div>
                            <div class="flex-1 p-4 font-bold">Enviar Postbacks</div>
                        </div>
                        @foreach($campaigns as $index => $campaign)
                        {{-- <div class="flex"> --}}
                        {{-- <div class="flex border border-slate-200 rounded-sm {{ $index % 2 == 0 ? 'bg-slate-400' : 'bg-slate-300' }}"> --}}
                        <div class="flex">
                            {{-- <div class="flex-1 p-4 border border-slate-200 rounded-sm">{{ $campaign->name }}</div> --}}
                            <div class="flex-1 p-4">{{ $campaign->name }}</div>
                            <div class="flex-1 p-4">{{ $campaign->created_at }}</div>
                            <div class="flex-1 p-4">{{ $campaign->sendState }}</div>
                            <div class="flex-1 p-4">{{ $campaign->sendedLeads }}</div>
                            <div class="flex-1 p-4">{{ $campaign->totalLeads }}</div>
                            <div class="flex-1 p-4"> 
                                <a href="{{ url('/campaigns-postback-cron-form') }}">
                                    <x-primary-button>
                                        {{ __('Iniciar')}}
                                    </x-primary-button>
                                </a>    
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
