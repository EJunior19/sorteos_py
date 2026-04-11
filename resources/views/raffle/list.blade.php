@extends('layouts.app')

@section('title', 'Sorteos')

@section('content')

@php
    $rafflesCollection = collect($raffles ?? []);
    $activeRaffles = $rafflesCollection->where('status', '!=', 'finished')->values();
    $finishedRaffles = $rafflesCollection->where('status', 'finished')->sortByDesc('created_at')->values();
    $latestFinishedRaffle = $finishedRaffles->first();
@endphp

<div class="px-3 pb-6">

    <!-- 🔥 SORTEOS ACTIVOS -->
    <div class="text-center mb-4">
        <h1 class="text-xl font-bold text-yellow-400">
            🎁 Sorteos Disponibles
        </h1>
        <p class="text-gray-400 text-sm">
            Participá y ganá premios increíbles
        </p>
    </div>

    <div class="space-y-4">
        @forelse($activeRaffles as $r)
            <a href="/sorteo/{{ $r->id }}"
               class="block bg-[#141414] rounded-2xl border border-yellow-500/20 shadow-lg overflow-hidden active:scale-95 transition duration-150">

                <div class="relative">
                    @if($r->image)
                        <img src="{{ asset('storage/' . $r->image) }}"
                             class="w-full h-44 object-cover"
                             alt="{{ $r->name }}">
                    @else
                        <div class="w-full h-44 flex items-center justify-center bg-black text-yellow-400 text-lg">
                            🎁 Sin imagen
                        </div>
                    @endif

                    <div class="absolute top-2 right-2 bg-yellow-400 text-black text-xs px-2 py-1 rounded-lg font-bold shadow">
                        ACTIVO
                    </div>
                </div>

                <div class="p-4">
                    <h2 class="text-lg font-bold text-yellow-300 leading-tight">
                        {{ $r->name }}
                    </h2>

                    <p class="text-base mt-1 text-yellow-200 font-semibold">
                        💰 Gs. {{ number_format($r->price, 0, ',', '.') }}
                    </p>

                    <p class="text-sm text-gray-400">
                        🎟 {{ $r->total_numbers }} números disponibles
                    </p>

                    @php
                        $sold = $r->numbers->where('status','sold')->count();
                        $total = $r->total_numbers;
                        $percent = $total > 0 ? ($sold / $total) * 100 : 0;
                    @endphp

                    <div class="mt-3">
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-yellow-400 h-2 rounded-full"
                                 style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ round($percent) }}% vendido
                        </p>
                    </div>

                    <div class="mt-4">
                        <div class="w-full bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-500 text-black text-center py-3 rounded-xl font-bold shadow-lg text-sm">
                            🚀 PARTICIPAR AHORA
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center text-yellow-400 mt-10">
                🎁 No hay sorteos activos en este momento
            </div>
        @endforelse
    </div>

    <!-- 🏆 ÚLTIMO SORTEO FINALIZADO -->
    @if($latestFinishedRaffle)
        <div class="mt-8 mb-4">
            <h2 class="text-lg font-bold text-green-400 mb-3 text-center">
                🏆 Último sorteo finalizado
            </h2>

            <a href="/sorteo/{{ $latestFinishedRaffle->id }}"
               class="block bg-green-500/10 border border-green-400/30 rounded-2xl p-4 shadow-lg">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-white font-semibold">
                            {{ $latestFinishedRaffle->name }}
                        </p>
                    </div>

                    <span class="shrink-0 bg-green-500 text-white text-xs font-bold px-3 py-2 rounded-xl">
                        VER RESULTADO
                    </span>
                </div>
            </a>
        </div>
    @endif

    <!-- 📜 SORTEOS FINALIZADOS -->
    @if($finishedRaffles->count() > 1)
        <div class="mt-6">
            <h2 class="text-lg font-bold text-red-400 mb-3 text-center">
                📜 Sorteos finalizados
            </h2>

            <div class="space-y-2">
                @foreach($finishedRaffles->skip(1) as $r)
                    <a href="/sorteo/{{ $r->id }}"
                       class="block bg-[#141414] border border-red-500/20 rounded-xl px-4 py-3">
                        <p class="text-white font-medium">
                            {{ $r->name }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</div>

@endsection