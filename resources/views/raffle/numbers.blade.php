@extends('layouts.app')

@section('title', 'Elegir números')

@section('content')

@php
    $numbers = $raffle->numbers->keyBy('number');
    $urgencyMessages = $raffle->urgency_messages ?: [];
@endphp

<div class="px-3 pb-6">

    <h2 class="text-yellow-400 text-lg font-bold text-center mb-4">
        🎯 Elegí tus números
    </h2>

    @if(!empty($urgencyMessages))
        <div class="bg-[#1A1A1A] border border-yellow-500/30 rounded-xl p-3 mb-4 text-center">
            <p id="urgencyMessage" class="text-yellow-300 text-sm font-bold leading-snug">
                {{ $urgencyMessages[0] }}
            </p>
        </div>
    @endif

    @include('partials._promo_badge', ['raffle' => $raffle])

    <!-- GRID -->
    <div class="grid grid-cols-5 gap-2 mb-4">
        @for($i = 1; $i <= $raffle->total_numbers; $i++)
            @php
                $n = $numbers[$i] ?? null;
                $status = $n->status ?? 'free';
            @endphp

            <div onclick="toggleNumber('{{ $i }}','{{ $status }}')"
                 id="num-{{ $i }}"
                 class="p-3 text-center font-bold rounded-xl cursor-pointer transition
                    @if($status == 'free') bg-green-500 hover:scale-105
                    @elseif($status == 'reserved') bg-yellow-400 text-black cursor-not-allowed
                    @else bg-red-500 cursor-not-allowed
                    @endif">
                {{ $i }}
            </div>
        @endfor
    </div>

    <!-- FORM -->
    <input id="nombre" type="text" placeholder="Tu nombre"
        class="w-full p-3 rounded-xl bg-black text-white border border-yellow-400 mb-3">

    <div id="seleccionadosBox" class="text-yellow-300 text-sm text-center mb-3">
        Ninguno seleccionado
    </div>

    <button type="button" onclick="reservarNumeros()"
        class="w-full bg-yellow-400 text-black py-3 rounded-xl font-bold">
        Reservar
    </button>

</div>

<script>
let seleccionados = [];
const urgencyMessages = @json($urgencyMessages);
let urgencyIndex = 0;

if (urgencyMessages.length > 1) {
    setInterval(() => {
        urgencyIndex = (urgencyIndex + 1) % urgencyMessages.length;
        const el = document.getElementById('urgencyMessage');
        if (el) el.innerText = urgencyMessages[urgencyIndex];
    }, 6000);
}

function toggleNumber(num, status) {
    if (status !== 'free') return;

    const el = document.getElementById('num-' + num);

    if (seleccionados.includes(num)) {
        seleccionados = seleccionados.filter(n => n !== num);
        el.classList.remove('ring-4', 'ring-yellow-300');
    } else {
        seleccionados.push(num);
        el.classList.add('ring-4', 'ring-yellow-300');
    }

    document.getElementById('seleccionadosBox').innerText =
        seleccionados.length
            ? 'Seleccionados: ' + seleccionados.join(', ')
            : 'Ninguno seleccionado';
}

async function reservarNumeros() {
    const nombre = document.getElementById('nombre').value.trim();

    if (!nombre) {
        alert('Completá tu nombre');
        return;
    }

    if (seleccionados.length === 0) {
        alert('Seleccioná al menos un número');
        return;
    }

    try {
        const res = await fetch('/reservar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                raffle_id: {{ $raffle->id }},
                name: nombre,
                numbers: seleccionados
            })
        });

        const data = await res.json();

        if (!res.ok || data.error) {
            alert(data.error || 'Error al reservar');
            return;
        }

        alert('✅ Números reservados correctamente');
        window.location.reload();

    } catch (error) {
        console.error(error);
        alert('Ocurrió un error al reservar');
    }
}
</script>

@endsection
