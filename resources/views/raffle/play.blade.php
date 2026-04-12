@extends('layouts.app')

@section('title', $raffle->name)

@section('content')

<div class="px-4 pb-6">

    <!-- IMAGEN -->
    <div class="mb-4">
        <img src="{{ asset('storage/' . $raffle->image) }}"
             class="w-full h-56 object-cover rounded-2xl shadow-xl">
    </div>

    <!-- INFO -->
    <div class="text-center mb-5">
        <h1 class="text-2xl font-bold text-yellow-400">
            {{ $raffle->name }}
        </h1>

        <p class="text-yellow-300 text-xl font-semibold mt-1">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }}
        </p>
    </div>

    @php
        $total = $raffle->total_numbers;
        $sold = $raffle->numbers->where('status','sold')->count();
        $reserved = $raffle->numbers->where('status','reserved')->count();
        $free = $raffle->numbers->where('status','free')->count();
        $percent = $total > 0 ? (($sold + $reserved) / $total) * 100 : 0;
    @endphp

    <!-- 🔥 METRICAS -->
    <div class="grid grid-cols-4 gap-2 mb-5">

        <!-- TOTAL -->
        <div class="bg-[#141414] p-3 rounded-2xl text-center border border-white/10 shadow">
            <p class="text-lg font-bold text-white">{{ $total }}</p>
            <p class="text-xs text-gray-400">Total</p>
        </div>

        <!-- DISPONIBLES (DESTACADO) -->
        <div onclick="window.location.href='/sorteo/{{ $raffle->id }}/numeros'"
             class="bg-green-500/10 p-3 rounded-2xl text-center border border-green-500/40 shadow cursor-pointer hover:scale-105 active:scale-95 transition">

            <p class="text-lg font-bold text-green-400">{{ $free }}</p>
            <p class="text-xs text-gray-300">Disponibles</p>
        </div>

        <!-- RESERVADOS -->
        <div class="bg-yellow-400/10 p-3 rounded-2xl text-center border border-yellow-400/30 shadow">
            <p class="text-lg font-bold text-yellow-400">{{ $reserved }}</p>
            <p class="text-xs text-gray-400">Reservados</p>
        </div>

        <!-- VENDIDOS -->
        <div class="bg-red-500/10 p-3 rounded-2xl text-center border border-red-500/30 shadow">
            <p class="text-lg font-bold text-red-400">{{ $sold }}</p>
            <p class="text-xs text-gray-400">Vendidos</p>
        </div>

    </div>

    <!-- 🔥 PROGRESO -->
    <div class="bg-[#141414] p-4 rounded-2xl border border-white/10 shadow">

        <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-400">Progreso</span>
            <span class="text-yellow-400 font-bold">{{ round($percent) }}%</span>
        </div>

        <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden">
            <div class="bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-500 h-3 rounded-full transition-all duration-500"
                 style="width: {{ $percent }}%">
            </div>
        </div>

        <!-- TEXTO EXTRA -->
        <p class="text-xs text-gray-500 mt-2 text-center">
            {{ $sold + $reserved }} de {{ $total }} ocupados
        </p>

    </div>

</div>

@endsection