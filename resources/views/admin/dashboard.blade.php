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

            <a href="/admin/logout" class="bg-red-500 px-3 py-2 rounded text-sm font-bold">
                Salir
            </a>
        </div>

        <!-- INFO SORTEO -->
        <div class="bg-[#1A1A1A] p-3 rounded-xl mb-4 text-center">
            <h2 class="text-yellow-400 font-bold">
                {{ $raffle->name }}
            </h2>
            <p class="text-sm text-yellow-300">
                Gs. {{ number_format($raffle->price, 0, ',', '.') }}
            </p>
        </div>

        <!-- GRID NUMEROS -->
        <div class="grid grid-cols-5 gap-2">

            @foreach($raffle->numbers ?? [] as $n)

                <div class="text-center text-xs">

                    <div class="p-3 rounded font-bold
                                @if($n->status == 'free') bg-green-500
                                @elseif($n->status == 'reserved') bg-yellow-400 text-black
                                @else bg-red-500
                                @endif">

                        {{ $n->number }}
                    </div>

                    <div class="text-[10px] mt-1 truncate">
                        {{ $n->customer_name ?? '-' }}
                    </div>

                    @if($n->status == 'reserved')
                        <form method="POST" action="/admin/confirmar/{{ $n->id }}">
                            @csrf
                            <button class="bg-blue-500 text-xs mt-1 px-2 py-1 rounded w-full">
                                ✔
                            </button>
                        </form>
                    @endif

                </div>

            @endforeach

        </div>

    @endif

@endsection