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
            <div class="bg-white dark:bg-gray-800 p-1 border border-gray-300 dark:border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('message'))
                        <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/40 px-4 py-2 text-sm text-green-800 dark:text-green-200">
                            {{ session('message') }}
                        </div>
                    @endif
                    @if ($campaigns->isEmpty())
                        <div class="text-center font-semibold text-gray-500 dark:text-gray-400">
                            <p>Nenhuma campanha cadastrada</p>
                        </div>
                    @else
                        <div class="mt-4">
                            {{ $campaigns->links('pagination::tailwind') }}
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @php($timezone = config('app.display_timezone'))
                            @foreach($campaigns as $campaign)
                                <div class="flex flex-col bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden shadow-md">
                                    <div class="p-4 flex-1">
                                        <div class="font-bold text-xl mb-2">{{ $campaign->name }}</div>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Data de Upload: <span class="font-semibold">{{ $campaign->created_at->setTimezone($timezone)->format('d/m/Y') }}</span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Estado de Envio: <span class="font-semibold">{{ $campaign->sendState }}</span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Postbacks Enviados: <span class="font-semibold">{{ $campaign->sendedLeads }}</span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Total de Leads: <span class="font-semibold">{{ $campaign->totalLeads }}</span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Leads restantes: <span class="font-semibold">{{ $campaign->remaining_leads }}</span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Envios últimas 24h: <span class="font-semibold">{{ $campaign->sent_last_24h }}</span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-400 text-base">
                                            Último envio: <span class="font-semibold">{{ $campaign->last_sent_at ? Carbon\Carbon::parse($campaign->last_sent_at)->setTimezone($timezone)->format('d/m/Y H:i') : '—' }}</span>
                                        </p>
                                        @if ($campaign->postback_count)
                                            <p class="text-gray-700 dark:text-gray-400 text-base">
                                                Ritmo: <span class="font-semibold">{{ $campaign->postback_count }} por {{ $campaign->postback_frequency === 'hour' ? 'hora' : 'minuto' }}</span>
                                            </p>
                                        @endif
                                        @if ($campaign->starts_at)
                                            <p class="text-gray-700 dark:text-gray-400 text-base">
                                                Início: <span class="font-semibold">{{ $campaign->starts_at->setTimezone($timezone)->format('d/m/Y H:i') }}</span>
                                            </p>
                                        @endif
                                        @if ($campaign->ends_at)
                                            <p class="text-gray-700 dark:text-gray-400 text-base">
                                                Término: <span class="font-semibold">{{ $campaign->ends_at->setTimezone($timezone)->format('d/m/Y H:i') }}</span>
                                            </p>
                                        @endif
                                    </div>
                                    <div class="p-4 flex flex-wrap justify-between gap-2">
                                        <form action="{{ url('/campaigns-delete/' . $campaign->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <x-danger-button type="submit" class="x-danger-button" onclick="return confirm('Tem certeza que deseja excluir a campanha?')">
                                                {{ __('Excluir') }}
                                            </x-danger-button>
                                        </form>
                                        <div class="flex flex-wrap gap-2">
                                            @if ($campaign->isActive())
                                                <form action="{{ route('campaigns.pause', $campaign) }}" method="POST">
                                                    @csrf
                                                    <x-secondary-button type="submit">{{ __('Pausar') }}</x-secondary-button>
                                                </form>
                                            @elseif ($campaign->sendState === App\Models\Campaign::PAUSED)
                                                <form action="{{ route('campaigns.resume', $campaign) }}" method="POST">
                                                    @csrf
                                                    <x-secondary-button type="submit">{{ __('Retomar') }}</x-secondary-button>
                                                </form>
                                            @endif
                                            @if ($campaign->sendState !== App\Models\Campaign::FINISHED)
                                                <a href="{{ url('/campaigns-postback-cron-form/' . $campaign->id) }}">
                                                    <x-primary-button>
                                                        {{ in_array($campaign->sendState, [App\Models\Campaign::WAITING, App\Models\Campaign::ENDED]) ? __('Iniciar') : __('Editar envio') }}
                                                    </x-primary-button>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            {{ $campaigns->links('pagination::tailwind') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
