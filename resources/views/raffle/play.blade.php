@extends('layouts.app')

@section('title', $raffle->name)

@section('content')

    <div class="text-center mb-4">
        <h1 class="text-xl font-bold text-yellow-400">
            {{ $raffle->name }}
        </h1>

        <p class="text-yellow-300 text-lg font-semibold">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }}
        </p>
    </div>

    <!-- GRID -->
    <div class="grid grid-cols-5 gap-2 mb-4">

        @foreach($raffle->numbers as $n)

            <div onclick="toggleNumber('{{ $n->number }}','{{ $n->status }}')" id="num-{{ $n->number }}" class="p-4 text-center text-lg font-bold rounded-xl transition-all duration-200
                    @if($n->status == 'free') bg-green-500 hover:scale-105
                    @elseif($n->status == 'reserved') bg-yellow-400 text-black
                    @else bg-red-500
                    @endif">

                {{ $n->number }}

            </div>

        @endforeach

    </div>

    <!-- FORM -->
    <div class="bg-[#141414] p-5 rounded-2xl border border-yellow-500/30 shadow-lg">

        <!-- INPUT NOMBRE -->
        <input id="nombre" type="text" placeholder="👤 Escribí tu nombre" class="w-full p-4 text-lg rounded-xl bg-black text-white placeholder-gray-400
            border border-yellow-400 mb-3
            focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:shadow-[0_0_10px_#facc15]">

        <!-- SELECCIONADOS -->
        <div id="seleccionadosBox"
            class="mb-4 text-center text-yellow-300 text-sm bg-black p-3 rounded-xl border border-yellow-500/20">
            Ninguno seleccionado
        </div>

        <!-- BOTON -->
        <button onclick="reservarNumeros()" class="w-full bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-500 
            text-black py-4 text-lg rounded-xl font-bold
            hover:scale-[1.03] active:scale-[0.97] transition-all shadow-lg">

            🎯 RESERVAR AHORA
        </button>

    </div>

    <script>
        let seleccionados = [];

        function toggleNumber(num, status) {
            if (status !== 'free') return;

            let el = document.getElementById('num-' + num);

            if (seleccionados.includes(num)) {
                seleccionados = seleccionados.filter(n => n !== num);
                el.classList.remove('ring-4', 'ring-yellow-300', 'scale-110');
            } else {
                seleccionados.push(num);
                el.classList.add('ring-4', 'ring-yellow-300', 'scale-110');
            }

            document.getElementById('seleccionadosBox').innerText =
                seleccionados.length
                    ? "Seleccionados: " + seleccionados.join(', ')
                    : 'Ninguno seleccionado';
        }

        async function reservarNumeros() {

            let nombre = document.getElementById('nombre').value;

            if (!nombre || seleccionados.length === 0) {
                alert("⚠️ Completá tu nombre y seleccioná números");
                return;
            }

            let res = await fetch('/reservar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    name: nombre,
                    numbers: seleccionados,
                    raffle_id: {{ $raffle->id }}
            })
            });

            let data = await res.json();

            if (data.error) {
                alert(data.error);
                return;
            }

            let msg = `Hola quiero reservar:\n\n🎁 {{ $raffle->name }}\n🔢 ${seleccionados.join(', ')}\n👤 ${nombre}`;

            window.open(`https://wa.me/595986770148?text=${encodeURIComponent(msg)}`);

            location.reload();
        }
    </script>

@endsection