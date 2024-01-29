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
                    <div class="mt-4">
                        {{ $campaigns->links('pagination::tailwind') }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($campaigns as $campaign)
                            <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden shadow-md">
                                <div class="p-4">
                                    <div class="font-bold text-xl mb-2">{{ $campaign->name }}</div>
                                    <p class="text-gray-700 dark:text-gray-400 text-base">
                                        Data de Upload: <span class="font-semibold">{{ $campaign->created_at->format('d/m/Y') }}</span>
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
                                </div>
                                <div class="p-4 flex justify-between">
                                    <form action="{{ url('/campaigns-delete/' . $campaign->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <x-danger-button type="submit" class="x-danger-button" onclick="return confirm('Tem certeza que deseja excluir a campanha?')">
                                            {{ __('Excluir') }}
                                        </x-danger-button>
                                    </form>
                                    <a href="{{ url('/campaigns-postback-cron-form/' . $campaign->id) }}">
                                        <x-primary-button>
                                            {{ __('Iniciar')}}
                                        </x-primary-button>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        {{ $campaigns->links('pagination::tailwind') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
