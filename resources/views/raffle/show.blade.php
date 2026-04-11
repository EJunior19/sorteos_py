@extends('layouts.app')

@section('title', $raffle->name)

@section('content')

@if($raffle->status === 'finished')

@php
    $prizes = $raffle->prizes
        ? $raffle->prizes->sortBy('order')->values()
        : collect();
@endphp

{{-- ═══════════════════════════════════════════════════════
     SORTEO FINALIZADO — PÁGINA DE GANADORES
═══════════════════════════════════════════════════════ --}}

<canvas id="confetti-canvas"
        class="fixed inset-0 pointer-events-none z-50"
        style="width:100%;height:100%"></canvas>

<div class="pb-10">

    {{-- HEADER FESTIVO --}}
    <div class="text-center py-6 px-4">
        <div class="text-5xl mb-2">🏆</div>
        <h1 class="text-2xl font-black text-yellow-400 leading-tight">
            ¡Tenemos Ganadores!
        </h1>
        <p class="text-gray-300 text-sm mt-1">{{ $raffle->name }}</p>
        <p class="text-gray-500 text-xs mt-1">
            Sorteado el {{ optional($raffle->updated_at)->format('d/m/Y') }}
        </p>
    </div>

    {{-- IMAGEN DEL SORTEO --}}
    @if($raffle->image)
        <div class="mx-4 rounded-2xl overflow-hidden border border-yellow-500/30 mb-6">
            <img src="{{ asset('storage/' . $raffle->image) }}"
                 class="w-full h-48 object-cover opacity-80"
                 alt="{{ $raffle->name }}">
        </div>
    @endif

    {{-- PREMIOS / GANADORES --}}
    <div class="px-4 space-y-4">

        @if($prizes->isNotEmpty())

            <div class="mb-2 text-center">
                <p class="text-sm text-gray-400">
                    Resultados del sorteo
                </p>
            </div>

            @foreach($prizes as $index => $prize)

                @php
                    $position = $index + 1;

                    $colors = match($position) {
                        1 => ['ring' => 'ring-yellow-400', 'bg' => 'bg-yellow-400/10', 'num' => 'text-yellow-400', 'badge' => 'bg-yellow-400 text-black'],
                        2 => ['ring' => 'ring-gray-400',   'bg' => 'bg-gray-400/10',   'num' => 'text-gray-300',   'badge' => 'bg-gray-400 text-black'],
                        3 => ['ring' => 'ring-orange-400', 'bg' => 'bg-orange-400/10', 'num' => 'text-orange-400', 'badge' => 'bg-orange-500 text-white'],
                        default => ['ring' => 'ring-blue-400', 'bg' => 'bg-blue-400/10', 'num' => 'text-blue-300', 'badge' => 'bg-blue-500 text-white'],
                    };

                    $label = method_exists($prize, 'positionLabel')
                        ? $prize->positionLabel()
                        : ($position . '° Premio');
                @endphp

                <div class="rounded-2xl ring-2 {{ $colors['ring'] }} {{ $colors['bg'] }} p-4">

                    <div class="flex items-center gap-2 mb-3 flex-wrap">
                        <span class="text-xs font-bold px-2 py-1 rounded-full {{ $colors['badge'] }}">
                            {{ $label }}
                        </span>

                        <span class="text-white font-bold text-sm">
                            {{ $prize->name ?: 'Premio ' . $position }}
                        </span>
                    </div>

                    @if(!empty($prize->description))
                        <p class="text-gray-400 text-xs mb-3">{{ $prize->description }}</p>
                    @endif

                    @if(method_exists($prize, 'hasWinner') ? $prize->hasWinner() : !empty($prize->winner_number))
                        <div class="flex justify-between items-end gap-3">
                            <div class="min-w-0">
                                <p class="text-gray-500 text-xs">Ganador</p>
                                <p class="text-white font-bold break-words">
                                    {{ $prize->winner_name ?? 'Ganador confirmado' }}
                                </p>
                            </div>

                            <div class="text-right shrink-0">
                                <p class="text-gray-500 text-xs">Número</p>
                                <p class="font-black text-3xl {{ $colors['num'] }}">
                                    {{ str_pad($prize->winner_number, 2, '0', STR_PAD_LEFT) }}
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="bg-black/20 rounded-xl px-3 py-2">
                            <p class="text-gray-500 text-xs italic">
                                Ganador pendiente de asignación
                            </p>
                        </div>
                    @endif

                </div>

            @endforeach

        @elseif(!empty($raffle->winner_number))

            {{-- GANADOR LEGACY (1 solo premio) --}}
            <div class="rounded-2xl ring-2 ring-yellow-400 bg-yellow-400/10 p-6 text-center">
                <p class="text-xs text-gray-400 mb-1">Número Ganador</p>
                <p class="text-7xl font-black text-yellow-400">
                    {{ str_pad($raffle->winner_number, 2, '0', STR_PAD_LEFT) }}
                </p>

                @if(!empty($raffle->winner_name))
                    <p class="text-white font-bold text-lg mt-2">{{ $raffle->winner_name }}</p>
                @else
                    <p class="text-gray-400 text-sm mt-2">Ganador confirmado</p>
                @endif
            </div>

        @else

            <div class="text-center text-gray-500 py-6 bg-[#141414] rounded-2xl border border-white/5">
                Resultados próximamente
            </div>

        @endif

    </div>

    {{-- VOLVER --}}
    <div class="px-4 mt-8">
        <a href="/"
           class="block w-full text-center bg-[#141414] border border-yellow-500/30 text-yellow-400 py-3 rounded-xl font-semibold text-sm">
            ← Ver otros sorteos
        </a>
    </div>

</div>

{{-- CONFETI --}}
<script>
(function () {
    const canvas = document.getElementById('confetti-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    resize();
    window.addEventListener('resize', resize);

    const COLORS = ['#facc15', '#f97316', '#22c55e', '#3b82f6', '#ec4899', '#a855f7'];

    const pieces = Array.from({ length: 120 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height - canvas.height,
        w: 6 + Math.random() * 8,
        h: 10 + Math.random() * 6,
        color: COLORS[Math.floor(Math.random() * COLORS.length)],
        speed: 2 + Math.random() * 3,
        angle: Math.random() * Math.PI * 2,
        spin: (Math.random() - 0.5) * 0.15,
        drift: (Math.random() - 0.5) * 1.5,
    }));

    let running = true;
    let frames = 0;
    const MAX_FRAMES = 300;

    function draw() {
        if (!running) return;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        pieces.forEach(p => {
            ctx.save();
            ctx.translate(p.x + p.w / 2, p.y + p.h / 2);
            ctx.rotate(p.angle);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = frames < MAX_FRAMES ? 1 : Math.max(0, 1 - (frames - MAX_FRAMES) / 60);
            ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            ctx.restore();

            p.y += p.speed;
            p.x += p.drift;
            p.angle += p.spin;

            if (p.y > canvas.height + 20) {
                p.y = -20;
                p.x = Math.random() * canvas.width;
            }
        });

        frames++;

        if (frames > MAX_FRAMES + 60) {
            running = false;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            return;
        }

        requestAnimationFrame(draw);
    }

    draw();
})();
</script>

@else

{{-- ═══════════════════════════════════════════════════════
     SORTEO ACTIVO — DETALLE Y RESERVA
═══════════════════════════════════════════════════════ --}}

<div class="pb-10">

    {{-- IMAGEN --}}
    @if($raffle->image)
        <div class="rounded-b-2xl overflow-hidden -mx-4 -mt-4 mb-5">
            <img src="{{ asset('storage/' . $raffle->image) }}"
                 class="w-full h-52 object-cover"
                 alt="{{ $raffle->name }}">
        </div>
    @else
        <div class="h-36 flex items-center justify-center bg-[#111] rounded-2xl mb-5 text-4xl">
            🎁
        </div>
    @endif

    {{-- INFO PRINCIPAL --}}
    <div class="px-1">

        <div class="flex items-start justify-between gap-2 mb-1">
            <h1 class="text-xl font-black text-yellow-300 leading-tight">
                {{ $raffle->name }}
            </h1>
            <span class="shrink-0 text-xs bg-yellow-400 text-black font-bold px-2 py-1 rounded-lg mt-0.5">
                ACTIVO
            </span>
        </div>

        <p class="text-yellow-200 font-semibold text-lg mb-4">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }} por número
        </p>

        {{-- ESTADÍSTICAS --}}
        <div class="grid grid-cols-3 gap-2 mb-5">

            <div class="bg-[#141414] rounded-xl p-3 text-center border border-white/5">
                <p class="text-xl font-black text-white">{{ $total }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Total</p>
            </div>

            <div class="bg-[#141414] rounded-xl p-3 text-center border border-green-500/20">
                <p class="text-xl font-black text-green-400">{{ $free }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Disponibles</p>
            </div>

            <div class="bg-[#141414] rounded-xl p-3 text-center border border-yellow-500/20">
                <p class="text-xl font-black text-yellow-400">{{ $sold + $reserved }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Vendidos</p>
            </div>

        </div>

        {{-- BARRA DE PROGRESO --}}
        <div class="mb-6">
            <div class="flex justify-between text-xs text-gray-400 mb-1">
                <span>Progreso de venta</span>
                <span class="font-bold text-yellow-400">{{ $progress }}%</span>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-3">
                <div class="bg-gradient-to-r from-yellow-400 to-yellow-500 h-3 rounded-full transition-all"
                     style="width: {{ $progress }}%"></div>
            </div>
        </div>

        {{-- FECHA --}}
        @if($raffle->draw_date)
            <div class="bg-[#141414] border border-white/5 rounded-xl px-4 py-3 flex items-center gap-3 mb-6">
                <span class="text-2xl">📅</span>
                <div>
                    <p class="text-xs text-gray-500">Fecha del sorteo</p>
                    <p class="text-white font-semibold text-sm">
                        {{ \Carbon\Carbon::parse($raffle->draw_date)->format('d/m/Y') }}
                    </p>
                </div>
            </div>
        @endif

        {{-- BOTÓN WHATSAPP --}}
        <button onclick="reservarWhatsApp()"
                class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-black py-4 rounded-2xl text-base shadow-lg shadow-green-900/40 active:scale-95 transition duration-150 flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M11.999 2C6.477 2 2 6.477 2 12c0 1.99.573 3.842 1.562 5.404L2 22l4.734-1.54A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.522 2 11.999 2zm.001 18a8 8 0 110-16 8 8 0 010 16z"/>
            </svg>
            Reservar por WhatsApp
        </button>

        {{-- VOLVER --}}
        <a href="/"
           class="block w-full text-center mt-3 text-gray-500 text-sm py-2">
            ← Volver al inicio
        </a>

    </div>

</div>

<script>
function reservarWhatsApp() {
    const nombre = {!! json_encode($raffle->name) !!};
    const precio = {!! json_encode(number_format($raffle->price, 0, ',', '.')) !!};

    const msg = "Hola! Quiero reservar un número para el sorteo *" + nombre +
                "* (Gs. " + precio + " por número). ¿Cuáles están disponibles?";

    const url = "https://wa.me/595986770148?text=" + encodeURIComponent(msg);

    window.open(url, "_blank");
}
</script>

@endif

@endsection