@extends('layouts.app')

@section('title', 'Sorteo')

@section('content')

<div class="text-center">

    <h1 class="text-2xl text-yellow-400 mb-5 font-bold">
        🎰 SORTEO EN VIVO
    </h1>

    <!-- RULETA -->
    <div id="roulette"
         class="flex overflow-hidden border-4 border-yellow-400 rounded-xl p-3 bg-black">

        <div id="numbers" class="flex gap-2 text-white text-xl font-bold">
            @foreach($raffle->numbers as $n)
                <div class="px-4 py-3 bg-gray-700 rounded">
                    {{ $n->number }}
                </div>
            @endforeach
        </div>

    </div>

    <!-- RESULTADO -->
    <div id="winnerBox" class="hidden mt-6 text-3xl font-bold text-green-400">
        🏆 GANADOR: <span id="winner"></span>
    </div>

    <!-- BOTON -->
    <button onclick="startRoulette()"
        class="mt-6 bg-yellow-400 text-black px-6 py-3 rounded-xl font-bold">
        🎯 GIRAR RULETA
    </button>

</div>

<script>

let numbers = @json($raffle->numbers->pluck('number'));
let container = document.getElementById('numbers');

function startRoulette() {

    let speed = 50;
    let duration = 3000;

    let interval = setInterval(() => {
        container.appendChild(container.firstElementChild);
    }, speed);

    setTimeout(() => {
        clearInterval(interval);

        let winner = numbers[Math.floor(Math.random() * numbers.length)];

        document.getElementById('winner').innerText = winner;
        document.getElementById('winnerBox').classList.remove('hidden');

        // 🔥 enviar al backend
        fetch(`/admin/sortear/{{ $raffle->id }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

    }, duration);
}

</script>

@endsection