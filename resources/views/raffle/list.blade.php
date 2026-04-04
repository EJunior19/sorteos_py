@extends('layouts.app')

@section('title', 'Sorteos')

@section('content')

<div class="px-3 pb-6">

    <!-- 🔥 HEADER -->
    <div class="text-center mb-4">
        <h1 class="text-xl font-bold text-yellow-400">
            🎁 Sorteos Disponibles
        </h1>
        <p class="text-gray-400 text-sm">
            Participá y ganá premios increíbles
        </p>
    </div>

    <!-- 📱 GRID MOBILE PRO -->
    <div class="space-y-4">

        @forelse($raffles as $r)

            <a href="/sorteo/{{ $r->id }}"
               class="block bg-[#141414] rounded-2xl border border-yellow-500/20 shadow-lg overflow-hidden active:scale-95 transition duration-150">

                <!-- 🖼 IMAGEN -->
                <div class="relative">
                    @if($r->image)
                        <img src="{{ asset('storage/' . $r->image) }}"
                             class="w-full h-44 object-cover">
                    @else
                        <div class="w-full h-44 flex items-center justify-center bg-black text-yellow-400 text-lg">
                            🎁 Sin imagen
                        </div>
                    @endif

                    <!-- 🏷 BADGE -->
                    <div class="absolute top-2 right-2 bg-yellow-400 text-black text-xs px-2 py-1 rounded-lg font-bold shadow">
                        ACTIVO
                    </div>
                </div>

                <!-- 📄 INFO -->
                <div class="p-4">

                    <!-- NOMBRE -->
                    <h2 class="text-lg font-bold text-yellow-300 leading-tight">
                        {{ $r->name }}
                    </h2>

                    <!-- PRECIO -->
                    <p class="text-base mt-1 text-yellow-200 font-semibold">
                        💰 Gs. {{ number_format($r->price, 0, ',', '.') }}
                    </p>

                    <!-- NUMEROS -->
                    <p class="text-sm text-gray-400">
                        🎟 {{ $r->total_numbers }} números disponibles
                    </p>

                    <!-- PROGRESO (PRO UX 🔥) -->
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

                    <!-- BOTÓN -->
                    <div class="mt-4">
                        <div class="w-full bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-500 
                                    text-black text-center py-3 rounded-xl font-bold shadow-lg text-sm">
                            🚀 PARTICIPAR AHORA
                        </div>
                    </div>

                </div>

            </a>

        @empty

            <div class="text-center text-yellow-400 mt-10">
                🎁 No hay sorteos disponibles aún
            </div>

        @endforelse

    </div>

</div>

@endsection