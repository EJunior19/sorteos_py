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
        <button
            id="btn-whatsapp"
            onclick="enviarWhatsapp({{ $raffle->id ?? 0 }})"
            @if(!isset($raffle) || !$raffle) disabled @endif
            class="bg-green-500 hover:bg-green-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded-lg transition flex items-center gap-2">
            📲 WhatsApp
        </button>
        <a href="/admin/logout" class="bg-red-500 px-3 py-2 rounded text-sm font-bold text-white">
            Salir
        </a>
    </div>

    <!-- INFO SORTEO -->
    <div class="bg-[#1A1A1A] p-4 rounded-xl mb-4 text-center shadow-lg">

        <h2 class="text-yellow-400 font-bold text-lg">{{ $raffle->name }}</h2>

        <p class="text-yellow-300 text-sm">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }}
        </p>

        @if($raffle->prizes->isNotEmpty())
            <p class="text-gray-400 text-xs mt-1">
                🏆 {{ $raffle->prizes->count() }} premios
            </p>
        @endif

        <!-- PROGRESO -->
        <div class="mt-3">
            <div class="w-full bg-black rounded-full h-3">
                <div class="bg-yellow-400 h-3 rounded-full" style="width: {{ $progress }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ $progress }}% vendido</p>
        </div>

    </div>

    <!-- ── GANADORES (multi-premio) ───────────────────────────────── -->
    @if($raffle->prizes->isNotEmpty() && $raffle->prizes->whereNotNull('winner_number')->isNotEmpty())

        <div class="bg-[#1a1a1a] border border-yellow-500/30 rounded-xl p-4 mb-4 space-y-2">
            <h3 class="text-yellow-400 font-bold text-sm mb-3">🏆 Resultados del sorteo</h3>

            @foreach($raffle->prizes->sortByDesc('order') as $prize)
                @if($prize->winner_number)
                    <div class="flex justify-between items-center bg-green-900/40 border border-green-500/30 rounded-lg px-3 py-2">
                        <div>
                            <div class="text-green-400 font-bold text-sm">{{ $prize->name }}</div>
                            @if($prize->description)
                                <div class="text-gray-400 text-xs">{{ $prize->description }}</div>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="text-white font-black">Nº {{ $prize->winner_number }}</div>
                            <div class="text-yellow-300 text-xs">{{ $prize->winner_name }}</div>
                        </div>
                    </div>
                @else
                    <div class="flex justify-between items-center bg-gray-800/40 border border-gray-600/30 rounded-lg px-3 py-2 opacity-60">
                        <div class="text-gray-400 text-sm">{{ $prize->name }}</div>
                        <div class="text-gray-500 text-xs">Pendiente</div>
                    </div>
                @endif
            @endforeach
        </div>

    <!-- ── GANADOR LEGACY (1 solo premio) ────────────────────────── -->
    @elseif($raffle->winner_number)

        <div class="bg-green-500 text-white p-4 rounded-xl text-center font-bold mb-4 text-lg shadow-lg">
            🏆 GANADOR: {{ $raffle->winner_number }}
            @if($raffle->winner_name)
                <div class="text-sm font-normal mt-1">{{ $raffle->winner_name }}</div>
            @endif
        </div>

    @endif

    <!-- BOTON SORTEO -->
    @if($free == 0 && $raffle->status == 'active')
        <a href="{{ route('admin.roulette', $raffle->id) }}"
           class="block w-full bg-green-500 text-white py-3 rounded-xl font-bold shadow-lg text-center mb-4">
            🎯 REALIZAR SORTEO
        </a>
    @endif

    <!-- GRID NUMEROS -->
    <div class="grid grid-cols-5 gap-2">

        @foreach($raffle->numbers ?? [] as $n)

            <div class="text-center text-xs">

                <div class="p-3 rounded font-bold transition-all
                    @if($n->status == 'free') bg-green-500
                    @elseif($n->status == 'reserved') bg-yellow-400 text-black
                    @else bg-red-500
                    @endif">
                    {{ $n->number }}
                </div>

                <div class="text-[10px] mt-1 truncate text-gray-300">
                    {{ $n->customer_name ?? '-' }}
                </div>

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

<script>
async function enviarWhatsapp(raffleId) {
    if (!raffleId) return;
    const btn = document.getElementById('btn-whatsapp');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Generando...';
    try {
        const response = await fetch(`/admin/whatsapp/${raffleId}`);
        if (!response.ok) throw new Error('Error');
        const data = await response.json();
        await navigator.clipboard.writeText(data.mensaje);
        btn.innerHTML = '✅ ¡Copiado!';
        btn.classList.remove('bg-green-500','hover:bg-green-400');
        btn.classList.add('bg-green-700');
        setTimeout(() => {
            window.open('https://chat.whatsapp.com/IW4f2FC2Nwj6bbcWuAlLeD', '_blank');
            btn.disabled = false;
            btn.innerHTML = textoOriginal;
            btn.classList.remove('bg-green-700');
            btn.classList.add('bg-green-500','hover:bg-green-400');
        }, 1000);
    } catch (error) {
        btn.innerHTML = '❌ Error';
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = textoOriginal;
        }, 2000);
    }
}
</script>

@endsection
