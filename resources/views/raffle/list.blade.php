@extends('layouts.app')

@section('title', 'Sorteos')

@section('content')

<div class="px-3 pb-6">

    <!-- 🏆 ÚLTIMOS GANADORES -->
    @if(isset($winners) && $winners->count())
    <div class="mb-6">

        <h2 class="text-lg font-bold text-green-400 mb-3 text-center">
            🏆 Últimos Ganadores
        </h2>

        <div class="space-y-2">

            @foreach($winners as $w)
                <div class="bg-green-500/10 border border-green-400/30 rounded-xl p-3 flex justify-between items-center">

                    <div>
                        <p class="text-sm text-gray-300">
                            {{ $w->name ?? 'Sorteo #' . $w->id }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $w->created_at->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-green-400 font-bold text-lg">
                            Nº {{ $w->winner_number }}
                        </p>
                        <p class="text-yellow-300 text-xs">
                            {{ $w->winner_name ?? 'Ganador' }}
                        </p>
                    </div>

                </div>
            @endforeach

        </div>
    </div>
    @endif

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

                    <!-- PROGRESO -->
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