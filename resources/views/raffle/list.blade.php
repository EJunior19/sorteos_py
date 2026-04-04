@extends('layouts.app')

@section('title', 'Sorteos')

@section('content')

    <h1 class="text-2xl font-bold text-yellow-400 text-center mb-5">
        🎁 Sorteos Disponibles
    </h1>

    <div class="grid grid-cols-1 gap-5">

        @forelse($raffles as $r)

            <a href="/sorteo/{{ $r->id }}"
                class="bg-[#141414] rounded-2xl border border-yellow-500/30 shadow-lg overflow-hidden active:scale-95 transition">

                <!-- 🖼 IMAGEN -->
                @if($r->image)
                    <img src="{{ asset('storage/' . $r->image) }}" class="w-full h-40 object-cover">
                @else
                    <div class="w-full h-40 flex items-center justify-center bg-black text-yellow-400">
                        🎁 Sin imagen
                    </div>
                @endif

                <!-- INFO -->
                <div class="p-4">

                    <h2 class="text-lg font-bold text-yellow-300">
                        {{ $r->name }}
                    </h2>

                    <p class="text-sm mt-1 text-yellow-200">
                        💰 Gs. {{ number_format($r->price, 0, ',', '.') }}
                    </p>

                    <p class="text-sm text-gray-300">
                        🎟 {{ $r->total_numbers }} números
                    </p>

                    <!-- BOTON -->
                    <div class="mt-3 bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-500 
                                            text-black text-center py-2 rounded-xl font-bold shadow">
                        PARTICIPAR
                    </div>

                </div>

            </a>

        @empty

            <div class="text-center text-yellow-400 mt-10">
                No hay sorteos disponibles aún
            </div>

        @endforelse

    </div>

@endsection