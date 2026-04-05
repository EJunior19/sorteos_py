@extends('layouts.app')

@section('title', 'Ganadores')

@section('content')

<div class="max-w-4xl mx-auto px-4 py-10 text-white">

    <h1 class="text-3xl font-bold text-yellow-400 mb-6 text-center">
        🏆 Últimos Ganadores
    </h1>

    <div class="space-y-4">

        @forelse($ganadores as $g)
            <div class="bg-gray-800 rounded-xl p-5 flex justify-between items-center shadow-lg">

                <div>
                    <div class="text-lg font-bold">
                        🎯 {{ $g->name ?? 'Sorteo #' . $g->id }}
                    </div>

                    <div class="text-gray-400 text-sm">
                        {{ $g->created_at->format('d/m/Y') }}
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-green-400 text-xl font-bold">
                        Nº {{ $g->winner_number }}
                    </div>

                    <div class="text-yellow-300 text-sm">
                        {{ $g->winner_name ?? 'Ganador' }}
                    </div>
                </div>

            </div>
        @empty
            <div class="text-center text-gray-400">
                No hay ganadores aún
            </div>
        @endforelse

    </div>

</div>

@endsection