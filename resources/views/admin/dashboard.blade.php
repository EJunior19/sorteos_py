@extends('layouts.app')

@section('title', 'Admin')

@section('content')

@if(!$raffle)

    <div class="text-center text-yellow-400 mt-10">
        No hay sorteos creados aún
    </div>

    <div class="text-center mt-4">
        <a href="/admin/create" class="bg-yellow-400 text-black px-4 py-2 rounded-lg font-bold">
            Crear primer sorteo
        </a>
    </div>

@else

    <!-- HEADER -->
    <div class="flex justify-between mb-4 items-center">
        <a href="/admin/create" class="bg-yellow-400 text-black px-3 py-2 rounded text-sm font-bold">
            + Crear
        </a>

        <a href="/admin/logout" class="bg-red-500 px-3 py-2 rounded text-sm font-bold text-white">
            Salir
        </a>
    </div>

    <!-- INFO SORTEO -->
    <div class="bg-[#1A1A1A] p-4 rounded-xl mb-4 text-center shadow-lg">

        <h2 class="text-yellow-400 font-bold text-lg">
            {{ $raffle->name }}
        </h2>

        <p class="text-yellow-300 text-sm">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }}
        </p>

        <!-- PROGRESO -->
        <div class="mt-3">
            <div class="w-full bg-black rounded-full h-3">
                <div class="bg-yellow-400 h-3 rounded-full"
                     style="width: {{ $progress }}%">
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-1">
                {{ $progress }}% vendido
            </p>
        </div>

    </div>

    <!-- GANADOR -->
    @if($raffle->winner_number)

        <div class="bg-green-500 text-white p-4 rounded-xl text-center font-bold mb-4 text-lg shadow-lg">
            🏆 GANADOR: {{ $raffle->winner_number }}
        </div>

    @endif

    <!-- BOTON SORTEO -->
    @if($free == 0 && $raffle->status == 'active')

        <form method="POST" action="{{ route('admin.sortear', $raffle->id) }}" class="mb-4">
            @csrf
            <button class="w-full bg-green-500 text-white py-3 rounded-xl font-bold shadow-lg">
                🎯 REALIZAR SORTEO
            </button>
        </form>

    @endif

    <!-- GRID NUMEROS -->
    <div class="grid grid-cols-5 gap-2">

        @foreach($raffle->numbers ?? [] as $n)

            <div class="text-center text-xs">

                <!-- NUMERO -->
                <div class="p-3 rounded font-bold transition-all
                    @if($n->status == 'free') bg-green-500
                    @elseif($n->status == 'reserved') bg-yellow-400 text-black
                    @else bg-red-500
                    @endif">

                    {{ $n->number }}
                </div>

                <!-- CLIENTE -->
                <div class="text-[10px] mt-1 truncate text-gray-300">
                    {{ $n->customer_name ?? '-' }}
                </div>

                <!-- CONFIRMAR -->
                @if($n->status == 'reserved')
                    <form method="POST" action="/admin/confirmar/{{ $n->id }}">
                        @csrf
                        <button class="bg-blue-500 text-xs mt-1 px-2 py-1 rounded w-full text-white">
                            ✔ Confirmar
                        </button>
                    </form>
                @endif

            </div>

        @endforeach

    </div>

@endif

@endsection