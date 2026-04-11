@extends('layouts.app')

@section('title', 'Ganadores')

@section('content')

<div class="max-w-4xl mx-auto px-4 py-10 text-white">

    <h1 class="text-3xl font-bold text-yellow-400 mb-6 text-center">
        🏆 Últimos Ganadores
    </h1>

    <div class="space-y-4">

        @forelse($winners as $raffle)

            <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">

                <!-- ENCABEZADO DEL SORTEO -->
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-700">
                    <div>
                        <div class="text-lg font-bold">
                            🎯 {{ $raffle->name ?? 'Sorteo #' . $raffle->id }}
                        </div>
                        <div class="text-gray-400 text-xs">
                            {{ $raffle->updated_at->format('d/m/Y') }}
                        </div>
                    </div>

                    @if($raffle->prizes->isEmpty())
                        {{-- Badge para sorteos legacy (1 premio) --}}
                        <span class="text-xs bg-gray-700 text-gray-400 px-2 py-1 rounded-full">
                            1 premio
                        </span>
                    @else
                        <span class="text-xs bg-yellow-500/20 text-yellow-400 border border-yellow-500/40 px-2 py-1 rounded-full">
                            {{ $raffle->prizes->count() }} premios
                        </span>
                    @endif
                </div>

                <!-- PREMIOS MÚLTIPLES -->
                @if($raffle->prizes->isNotEmpty())

                    <div class="divide-y divide-gray-700">
                        @foreach($raffle->prizes as $prize)
                            <div class="flex justify-between items-center px-5 py-3">
                                <div>
                                    <div class="text-sm font-bold text-yellow-300">
                                        {{ $prize->name }}
                                    </div>
                                    @if($prize->description)
                                        <div class="text-gray-400 text-xs">
                                            {{ $prize->description }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="text-green-400 font-black">
                                        Nº {{ str_pad($prize->winner_number, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                    <div class="text-gray-300 text-xs">
                                        {{ $prize->winner_name ?? 'Ganador' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                <!-- GANADOR LEGACY (1 solo premio) -->
                @elseif($raffle->winner_number)

                    <div class="flex justify-between items-center px-5 py-4">
                        <div class="text-gray-400 text-sm">Ganador único</div>
                        <div class="text-right">
                            <div class="text-green-400 text-xl font-black">
                                Nº {{ str_pad($raffle->winner_number, 2, '0', STR_PAD_LEFT) }}
                            </div>
                            <div class="text-yellow-300 text-sm">
                                {{ $raffle->winner_name ?? 'Ganador' }}
                            </div>
                        </div>
                    </div>

                @endif

            </div>

        @empty

            <div class="text-center text-gray-400 py-10">
                No hay ganadores aún
            </div>

        @endforelse

    </div>

</div>

@endsection
