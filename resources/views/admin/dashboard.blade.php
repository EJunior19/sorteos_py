@extends('layouts.app')

@section('title', 'Admin')

@section('content')

@if(!isset($raffle) || !$raffle)

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
    <div class="flex justify-between mb-4 items-center gap-2">
        <a href="/admin/create" class="bg-yellow-400 text-black px-3 py-2 rounded text-sm font-bold">
            + Crear
        </a>
        @if(session('role') === 'admin')
            <a href="{{ route('admin.drafts.index') }}" class="bg-[#1A1A1A] border border-yellow-500/30 text-yellow-300 px-3 py-2 rounded text-sm font-bold">
                Borradores
            </a>
        @endif
        <button
            id="btn-whatsapp"
            onclick="enviarWhatsapp({{ $raffle->id }})"
            class="bg-green-500 hover:bg-green-400 text-white font-bold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm">
            📲 WhatsApp
        </button>
        <a href="/admin/logout" class="bg-red-500 px-3 py-2 rounded text-sm font-bold text-white">
            Salir
        </a>
    </div>

    <!-- PANEL PROMOCIÓN DESCUENTO -->
    <div id="panel-promo" class="mb-4 rounded-xl border {{ $raffle->discount_active ? 'border-orange-500/60 bg-orange-950/30' : 'border-white/10 bg-[#1A1A1A]' }} p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-white font-bold text-sm flex items-center gap-2">
                    🎁 Promoción de Descuento
                    @if($raffle->discount_active)
                        <span class="bg-orange-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full animate-pulse">ACTIVA</span>
                    @endif
                </p>
                @if($raffle->discount_active)
                    <p class="text-orange-400 text-xs mt-0.5">{{ $raffle->discount_pct }}% OFF — Precio promo: Gs. {{ number_format($raffle->price * (1 - $raffle->discount_pct/100), 0, ',', '.') }}</p>
                @else
                    <p class="text-gray-500 text-xs mt-0.5">Sin promo activa</p>
                @endif
            </div>
            @if($raffle->discount_active)
                <button onclick="desactivarPromo({{ $raffle->id }})" class="bg-gray-700 hover:bg-gray-600 text-white text-xs font-bold px-3 py-2 rounded-lg">
                    Desactivar
                </button>
            @else
                <button onclick="document.getElementById('form-promo').classList.toggle('hidden')" class="bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold px-3 py-2 rounded-lg">
                    + Crear
                </button>
            @endif
        </div>

        <div id="form-promo" class="{{ $raffle->discount_active ? 'hidden' : 'hidden' }} mt-3 pt-3 border-t border-white/10">
            <p class="text-gray-400 text-xs mb-2">¿Cuánto % de descuento querés dar?</p>
            <div class="flex gap-2">
                <div class="flex gap-1.5">
                    @foreach([10, 15, 20, 25, 30] as $pct)
                        <button onclick="activarPromo({{ $raffle->id }}, {{ $pct }})"
                            class="bg-orange-700 hover:bg-orange-500 text-white text-sm font-bold px-3 py-2 rounded-lg transition">
                            {{ $pct }}%
                        </button>
                    @endforeach
                </div>
            </div>
            <p class="text-gray-600 text-[10px] mt-2">Al activar, los mensajes de WhatsApp incluirán automáticamente el precio con descuento.</p>
        </div>
    </div>

    <!-- INFO SORTEO -->
    <div class="bg-[#1A1A1A] p-4 rounded-xl mb-4 text-center shadow-lg">
        <h2 class="text-yellow-400 font-bold text-lg">{{ $raffle->name }}</h2>
        <p class="text-yellow-300 text-sm">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }}
        </p>
        @if($raffle->prizes && $raffle->prizes->count() > 0)
            <p class="text-gray-400 text-xs mt-1">
                🏆 {{ $raffle->prizes->count() }} premios
            </p>
        @endif
        <div class="mt-3">
            <div class="w-full bg-black rounded-full h-3">
                <div class="bg-yellow-400 h-3 rounded-full" style="width: {{ $progress ?? 0 }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ $progress ?? 0 }}% vendido</p>
        </div>
    </div>

    <!-- PANEL RENTABILIDAD -->
    @php
        $precioNormal = (int)$raffle->price;
        $precioPromoCalc = $raffle->discount_active ? round($precioNormal * (1 - $raffle->discount_pct / 100)) : $precioNormal;
        $recaudadoConfirmado = $paid * $precioNormal;
        $recaudadoPendiente  = $pendientes * $precioNormal;
        $recaudadoLibresPromo = $free * ($raffle->discount_active ? $precioPromoCalc : $precioNormal);
        $recaudadoTotal = $recaudadoConfirmado + $recaudadoPendiente + $recaudadoLibresPromo;
        $gananciaTotal  = $recaudadoTotal - $totalCost;
        $gananciaSocio  = $gananciaTotal / 2;
    @endphp
    <div id="panel-rentabilidad"
        data-precio-normal="{{ $precioNormal }}"
        data-paid="{{ $paid }}"
        data-pendientes="{{ $pendientes }}"
        data-free="{{ $free }}"
        data-total-cost="{{ $totalCost }}"
        data-discount-active="{{ $raffle->discount_active ? 1 : 0 }}"
        data-discount-pct="{{ (int)$raffle->discount_pct }}"
        class="mb-4 rounded-xl border border-white/10 bg-[#1A1A1A] overflow-hidden">
        <button onclick="document.getElementById('rentabilidad-body').classList.toggle('hidden')"
            class="w-full flex justify-between items-center p-4 text-left">
            <span class="text-white font-bold text-sm flex items-center gap-2">
                📈 Rentabilidad del sorteo
                @if($totalCost > 0)
                    <span id="rentabilidad-resumen" class="text-{{ $gananciaTotal >= 0 ? 'green' : 'red' }}-400 text-xs font-bold">
                        {{ $gananciaTotal >= 0 ? '+' : '' }}Gs. {{ number_format($gananciaTotal, 0, ',', '.') }}
                    </span>
                @else
                    <span class="text-gray-500 text-xs">Sin costos cargados</span>
                @endif
            </span>
            <span class="text-gray-400 text-xs">▼</span>
        </button>

        <div id="rentabilidad-body" class="hidden px-4 pb-4 space-y-4">

            <!-- COSTOS POR PREMIO -->
            <div>
                <p class="text-gray-400 text-xs font-bold mb-2">🏆 Costo de premios</p>
                <div class="space-y-1.5" id="lista-costos">
                    @foreach($raffle->prizes->sortByDesc('order') as $prize)
                    <div class="flex justify-between items-center gap-2">
                        <span class="text-white text-xs flex-1 truncate">{{ $prize->name }}</span>
                        <div class="flex items-center gap-1">
                            <span class="text-gray-500 text-xs">Gs.</span>
                            <input type="number"
                                id="cost-{{ $prize->id }}"
                                value="{{ $prize->cost }}"
                                min="0"
                                step="1000"
                                onchange="recalcularCostos()"
                                class="bg-[#2a2a2a] border border-white/10 text-white text-xs rounded px-2 py-1 w-28 text-right focus:border-yellow-400 focus:outline-none">
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="flex justify-between items-center mt-2 pt-2 border-t border-white/10">
                    <span class="text-gray-400 text-xs font-bold">Total costo</span>
                    <span id="total-costo-display" class="text-red-400 text-sm font-bold">
                        Gs. {{ number_format($totalCost, 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex gap-2 mt-2">
                    <button onclick="guardarCostos({{ $raffle->id }}, this)"
                        class="flex-1 bg-yellow-600 hover:bg-yellow-500 text-white text-xs font-bold py-2 rounded-lg transition">
                        💾 Guardar
                    </button>
                    <button onclick="exportarRentabilidad(this)"
                        class="flex-1 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-2 rounded-lg transition">
                        📷 Imagen
                    </button>
                </div>
            </div>

            <!-- PROYECCIÓN DE INGRESOS -->
            <div class="border-t border-white/10 pt-3">
                <p class="text-gray-400 text-xs font-bold mb-2">💰 Proyección de ingresos</p>
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-300">✅ Confirmados ({{ $paid }})</span>
                        <span class="text-green-400 font-bold">Gs. {{ number_format($recaudadoConfirmado, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-300">⏳ Pendientes ({{ $pendientes }})</span>
                        <span class="text-yellow-400 font-bold">Gs. {{ number_format($recaudadoPendiente, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-300">
                            🆓 Libres ({{ $free }})
                            <span id="rentabilidad-promo-label" class="text-orange-400 {{ $raffle->discount_active ? '' : 'hidden' }}">{{ $raffle->discount_pct }}% off</span>
                        </span>
                        <span id="rentabilidad-libres" class="text-orange-400 font-bold">Gs. {{ number_format($recaudadoLibresPromo, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs border-t border-white/10 pt-1.5 mt-1">
                        <span class="text-white font-bold">Total si cobran todos</span>
                        <span id="rentabilidad-total-proyectado" class="text-white font-bold">Gs. {{ number_format($recaudadoTotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <!-- GANANCIA FINAL -->
            @if($totalCost > 0)
            <div class="border-t border-white/10 pt-3">
                <p class="text-gray-400 text-xs font-bold mb-2">🤝 Resultado final</p>
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-300">Total ingresos</span>
                        <span id="rentabilidad-total-ingresos" class="text-white font-bold">Gs. {{ number_format($recaudadoTotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-300">Total costos</span>
                        <span class="text-red-400 font-bold">- Gs. {{ number_format($totalCost, 0, ',', '.') }}</span>
                    </div>
                    <div id="rentabilidad-ganancia-box" class="flex justify-between items-center bg-{{ $gananciaTotal >= 0 ? 'green' : 'red' }}-900/30 border border-{{ $gananciaTotal >= 0 ? 'green' : 'red' }}-500/30 rounded-lg px-3 py-2 mt-1">
                        <span class="text-white text-sm font-bold">💰 Ganancia limpia</span>
                        <span id="rentabilidad-ganancia" class="text-{{ $gananciaTotal >= 0 ? 'green' : 'red' }}-400 text-sm font-bold">
                            Gs. {{ number_format($gananciaTotal, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center bg-blue-900/20 border border-blue-500/20 rounded-lg px-3 py-2">
                        <span class="text-blue-300 text-sm font-bold">👤 Para cada socio</span>
                        <span id="rentabilidad-socio" class="text-blue-300 text-sm font-bold">
                            Gs. {{ number_format($gananciaSocio, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            @else
            <div class="border-t border-white/10 pt-3 text-center">
                <p class="text-gray-500 text-xs">Cargá los costos de los premios para ver la ganancia 👆</p>
            </div>
            @endif

        </div>
    </div>

    <!-- GANADORES (multi-premio) -->
    @if($raffle->prizes && $raffle->prizes->count() > 0)
        @php
            $hasWinners = $raffle->prizes->filter(fn($p) => !is_null($p->winner_number))->count() > 0;
        @endphp
        @if($hasWinners)
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
                                <div class="text-yellow-300 text-xs">{{ $prize->winner_name ?? 'N/A' }}</div>
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
        @endif

    @elseif($raffle->winner_number)
        <div class="bg-green-500 text-white p-4 rounded-xl text-center font-bold mb-4 text-lg shadow-lg">
            🏆 GANADOR: {{ $raffle->winner_number }}
            @if($raffle->winner_name)
                <div class="text-sm font-normal mt-1">{{ $raffle->winner_name }}</div>
            @endif
        </div>
    @endif

    <!-- GANADORES PROMO -->
    @if($raffle->promoResults && $raffle->promoResults->count() > 0)
        <div class="bg-[#1a1a1a] border border-yellow-500/30 rounded-xl p-4 mb-4 space-y-2">
            <h3 class="text-yellow-400 font-bold text-sm mb-3">🎁 Ganadores de la Promo</h3>
            @foreach($raffle->promoResults as $r)
                <div class="flex justify-between items-center bg-yellow-900/20 border border-yellow-500/20 rounded-lg px-3 py-2">
                    <div class="text-yellow-300 text-sm font-bold">{{ $r->prize_text }}</div>
                    <div class="text-right">
                        <div class="text-white font-black text-sm">Nº {{ str_pad($r->raffleNumber->number ?? '?', 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-gray-300 text-xs">{{ $r->customer_name }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- BOTON SORTEO -->
    @if(($free ?? 1) == 0 && ($raffle->status ?? '') == 'active')
        <a href="{{ route('admin.roulette', $raffle->id) }}"
           class="block w-full bg-green-500 text-white py-3 rounded-xl font-bold shadow-lg text-center mb-4">
            🎯 REALIZAR SORTEO
        </a>
    @endif

    <!-- GRID NUMEROS -->
    @if($raffle->numbers && $raffle->numbers->count() > 0)
        <div class="grid grid-cols-5 gap-2">
            @foreach($raffle->numbers as $n)
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
                        <form method="POST" action="/admin/confirmar/{{ $n->id }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="bg-blue-500 text-xs mt-1 px-2 py-1 rounded w-full text-white hover:bg-blue-600 transition">
                                ✔ Confirmar
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

@endif

<!-- Modal mensajes WhatsApp -->
<div id="modal-whatsapp" class="fixed inset-0 z-50 hidden items-center justify-center px-3" style="background-color: rgba(0,0,0,0.95);">
    <div class="bg-gradient-to-b from-[#0a0a0a] to-[#000000] border border-green-500/60 rounded-2xl w-full max-w-md shadow-2xl max-h-[90vh] flex flex-col">

        <!-- Header fijo -->
        <div class="flex justify-between items-center p-4 border-b border-green-500/20 bg-[#111] rounded-t-2xl shrink-0">
            <h2 class="text-green-400 font-bold text-lg">📲 Mensajes WhatsApp</h2>
            <button onclick="cerrarModal()" class="text-gray-400 hover:text-white text-2xl leading-none p-1 rounded">×</button>
        </div>

        <!-- Loading -->
        <div id="modal-loading" class="p-8 text-center text-gray-400 shrink-0">
            <div class="text-4xl mb-3">⏳</div>
            <p>Generando mensajes...</p>
        </div>

        <!-- Lista scrolleable — incluye el botón imagen al final -->
        <div id="modal-contenido" class="hidden flex-1 overflow-y-auto p-4 space-y-2 pb-6"></div>

    </div>
</div>

<script>
const RAFFLE_STATUS = '{{ $raffle->status ?? "active" }}';
const RAFFLE_IMAGE  = '{{ $raffle->image ?? "" }}';

const ETIQUETAS = {
    mensaje_completo:   { titulo: '📋 Completo',           color: 'blue'   },
    urgencia:           { titulo: '🔥 Urgencia',           color: 'red'    },
    promocion:          { titulo: '🎁 Promoción',          color: 'orange' },
    recordatorio_pago:  { titulo: '💸 Recordatorio',       color: 'yellow' },
    invitacion:         { titulo: '🎉 Invitación',         color: 'purple' },
    flash:              { titulo: '⚡ Flash',              color: 'green'  },
    ultima_oportunidad: { titulo: '🚨 Última Oportunidad', color: 'red'    },
    anuncio_ganadores:  { titulo: '🏆 Ganadores',          color: 'gold'   },
    agradecimiento:     { titulo: '🙏 Gracias',            color: 'pink'   },
    compartir_grupo:    { titulo: '🔗 Compartir Grupo',    color: 'teal'   },
};

const COLOR_MAP = {
    blue:   'border-blue-500/40 bg-blue-900/20',
    red:    'border-red-500/40 bg-red-900/20',
    yellow: 'border-yellow-500/40 bg-yellow-900/20',
    purple: 'border-purple-500/40 bg-purple-900/20',
    green:  'border-green-500/40 bg-green-900/20',
    gold:   'border-yellow-400/40 bg-yellow-900/20',
    pink:   'border-pink-500/40 bg-pink-900/20',
    teal:   'border-teal-500/40 bg-teal-900/20',
    orange: 'border-orange-500/40 bg-orange-900/20',
};

const BTN_MAP = {
    blue:   'bg-blue-600 hover:bg-blue-500',
    red:    'bg-red-600 hover:bg-red-500',
    yellow: 'bg-yellow-600 hover:bg-yellow-500',
    purple: 'bg-purple-600 hover:bg-purple-500',
    green:  'bg-green-600 hover:bg-green-500',
    gold:   'bg-yellow-500 hover:bg-yellow-400',
    pink:   'bg-pink-600 hover:bg-pink-500',
    teal:   'bg-teal-600 hover:bg-teal-500',
    orange: 'bg-orange-600 hover:bg-orange-500',
};

// --- Rotación de variantes ---
const ROT_MS = 4000;
let variantesMap = {};
let variantIdxs  = {};
let rotTimers    = {};
let barTimers    = {};

function iniciarRotaciones() {
    Object.keys(variantesMap).forEach(key => {
        variantIdxs[key] = 0;
        _aplicarVariante(key);
        if (rotTimers[key]) clearInterval(rotTimers[key]);
        if (barTimers[key]) clearInterval(barTimers[key]);
        _iniciarBarra(key);
        rotTimers[key] = setInterval(() => {
            variantIdxs[key] = (variantIdxs[key] + 1) % variantesMap[key].length;
            _aplicarVariante(key);
            _iniciarBarra(key);
        }, ROT_MS);
    });
}

function _iniciarBarra(key) {
    const bar = document.getElementById('bar-' + key);
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.width = '0%';
    if (barTimers[key]) clearTimeout(barTimers[key]);
    barTimers[key] = setTimeout(() => {
        bar.style.transition = 'width ' + ROT_MS + 'ms linear';
        bar.style.width = '100%';
    }, 30);
}

function _aplicarVariante(key) {
    const idx = variantIdxs[key];
    const el  = document.getElementById('msg-' + key);
    if (el) el.textContent = variantesMap[key][idx];
    document.querySelectorAll('#dots-' + key + ' span').forEach((d, i) => {
        d.className = 'w-2 h-2 rounded-full inline-block mx-px transition-all ' +
            (i === idx ? 'bg-white scale-125' : 'bg-white/30');
    });
}

function _detenerRotaciones() {
    Object.values(rotTimers).forEach(clearInterval);
    Object.values(barTimers).forEach(clearTimeout);
    rotTimers = {}; barTimers = {}; variantesMap = {}; variantIdxs = {};
}

function cerrarModal() {
    _detenerRotaciones();
    document.getElementById('modal-whatsapp').classList.add('hidden');
    document.getElementById('modal-whatsapp').classList.remove('flex');
}

async function copiarImagenSorteo(imagenPath) {
    if (!imagenPath) { alert('El sorteo no tiene imagen'); return; }
    const btn = document.getElementById('btn-copiar-imagen-modal');
    const original = btn.innerHTML;
    btn.innerHTML = '⏳ Copiando...';
    btn.disabled = true;
    try {
        const response = await fetch(`/storage/${imagenPath}`);
        const blob = await response.blob();
        const img = new Image();
        img.onload = async function() {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            canvas.getContext('2d').drawImage(img, 0, 0);
            canvas.toBlob(async (pngBlob) => {
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob })]);
                    btn.innerHTML = '✅ Imagen copiada!';
                    setTimeout(() => {
                        btn.innerHTML = original;
                        btn.disabled = false;
                    }, 1500);
                } catch (e) {
                    alert('Error: ' + e.message);
                    btn.innerHTML = original;
                    btn.disabled = false;
                }
            }, 'image/png');
        };
        img.onerror = () => {
            alert('No se pudo cargar la imagen');
            btn.innerHTML = original;
            btn.disabled = false;
        };
        img.src = URL.createObjectURL(blob);
    } catch (e) {
        alert('Error: ' + e.message);
        btn.innerHTML = original;
        btn.disabled = false;
    }
}

async function enviarWhatsapp(raffleId) {
    if (!raffleId) return;
    const btn = document.getElementById('btn-whatsapp');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Cargando...';

    const modal = document.getElementById('modal-whatsapp');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('modal-loading').classList.remove('hidden');
    document.getElementById('modal-contenido').classList.add('hidden');
    document.getElementById('modal-contenido').innerHTML = '';

    try {
        const response = await fetch(`/admin/whatsapp/${raffleId}`);
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Error del servidor');

        const mensajes = generarMensajes(data);
        const contenido = document.getElementById('modal-contenido');

        const ordenActive = [
            'mensaje_completo',
            'urgencia',
            'promocion',
            'recordatorio_pago',
            'invitacion',
            'flash',
            'ultima_oportunidad',
            'compartir_grupo',
        ];

        const ordenFinished = [
            'anuncio_ganadores',
            'agradecimiento',
            'compartir_grupo',
        ];

        const orden = RAFFLE_STATUS === 'finished' ? ordenFinished : ordenActive;

        variantesMap = {}; variantIdxs = {};
        for (const key of orden) {
            if (!mensajes[key]) continue;
            const meta    = ETIQUETAS[key];
            const esArray = Array.isArray(mensajes[key]);

            if (esArray) {
                variantesMap[key] = mensajes[key];
                const dots = mensajes[key].map((_, i) =>
                    `<span class="w-2 h-2 rounded-full inline-block mx-px transition-all ${i===0?'bg-white scale-125':'bg-white/30'}"></span>`
                ).join('');
                contenido.innerHTML += `
                <div class="rounded-lg border ${COLOR_MAP[meta.color]} overflow-hidden">
                    <div class="p-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-white text-sm font-bold">${meta.titulo}</span>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <div id="dots-${key}" class="flex items-center gap-px">${dots}</div>
                                    <span class="text-white/40 text-[10px]">cambia solo</span>
                                </div>
                            </div>
                            <button onclick="copiarMensaje('${key}', this)"
                                class="${BTN_MAP[meta.color]} text-white text-xs font-bold px-4 py-2 rounded-lg whitespace-nowrap">
                                Copiar
                            </button>
                        </div>
                    </div>
                    <div id="bar-${key}" style="height:2px;width:0%;background:rgba(255,255,255,0.5);"></div>
                    <div id="msg-${key}" style="display:none;">${escapeHtml(mensajes[key][0])}</div>
                </div>`;
            } else {
                contenido.innerHTML += `
                <div class="flex justify-between items-center p-3 rounded-lg border ${COLOR_MAP[meta.color]}">
                    <span class="text-white text-sm font-bold">${meta.titulo}</span>
                    <button onclick="copiarMensaje('${key}', this)"
                        class="${BTN_MAP[meta.color]} text-white text-xs font-bold px-4 py-2 rounded-lg whitespace-nowrap">
                        Copiar
                    </button>
                    <div id="msg-${key}" style="display:none;">${escapeHtml(mensajes[key])}</div>
                </div>`;
            }
        }

        // Botón copiar imagen AL FINAL de la lista
        contenido.innerHTML += `
            <div class="pt-2 border-t border-green-500/20 mt-2">
                <button
                    id="btn-copiar-imagen-modal"
                    onclick="copiarImagenSorteo('${RAFFLE_IMAGE}')"
                    class="w-full bg-blue-500 hover:bg-blue-400 text-white font-bold py-3 px-4 rounded-lg transition flex items-center justify-center gap-2">
                    🖼️ Copiar Imagen del Sorteo
                </button>
            </div>`;

        iniciarRotaciones();
        document.getElementById('modal-loading').classList.add('hidden');
        document.getElementById('modal-contenido').classList.remove('hidden');

    } catch (error) {
        document.getElementById('modal-loading').innerHTML = `
            <div class="text-4xl mb-3">❌</div>
            <p class="text-red-400">Error: ${error.message}</p>
            <button onclick="cerrarModal()" class="mt-4 bg-red-500 text-white px-4 py-2 rounded">Cerrar</button>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
    }
}

function generarMensajes(data) {
    const {
        raffle_name = 'Sorteo',
        price = 0,
        titular_name = 'Junior Enciso',
        alias = '7130138',
        prizes = [],
        numbers = [],
        discount_active = false,
        discount_pct = 0,
    } = data;

    const fmt = v => new Intl.NumberFormat('es-PY', { useGrouping: true, minimumFractionDigits: 0 }).format(v).replace(/\s/g, '.');
    const precioFormato  = fmt(price);
    const precioPromo    = discount_active ? fmt(Math.round(price * (1 - discount_pct / 100))) : null;
    const ahorro         = discount_active ? fmt(price - Math.round(price * (1 - discount_pct / 100))) : null;

    const totalNumeros      = numbers.length;
    const numerosVendidos   = numbers.filter(n => n.status === 'sold' || n.status === 'reserved').length;
    const numerosLibres     = numbers.filter(n => n.status === 'free').length;
    const porcentajeVendido = totalNumeros > 0 ? Math.round((numerosVendidos / totalNumeros) * 100) : 0;

    const medallas = ['🥇','🥈','🥉','🎁','🎀','🌟','💫','✨','🎯','🎪'];

    let listaPremios = '🏆 *PREMIOS:*\n';
    if (prizes.length > 0) {
        prizes.forEach((p, i) => {
            listaPremios += `${medallas[i] || '🎁'} *${i+1}° Premio:* ${p.name || 'Premio'}`;
            if (p.description) listaPremios += ` ${p.description}`;
            listaPremios += '\n';
        });
    } else {
        listaPremios += '🎁 *Premios sorpresa!*\n';
    }

    let listaNumeros = '';
    numbers.forEach(num => {
        const nombre = num.customer_name || '';
        if (num.status === 'sold' || num.paid) {
            listaNumeros += `${num.number} - ${nombre} 💵\n`;
        } else if (nombre) {
            listaNumeros += `${num.number} - ${nombre}\n`;
        } else {
            listaNumeros += `${num.number}\n`;
        }
    });

    const disponibles = numbers.filter(n => n.status === 'free');
    let numerosDisponiblesLista = '';
    disponibles.forEach((num, idx) => {
        numerosDisponiblesLista += num.number;
        if ((idx + 1) % 10 === 0 || idx === disponibles.length - 1) {
            numerosDisponiblesLista += '\n';
        } else {
            numerosDisponiblesLista += ', ';
        }
    });

    let listaGanadores = '';
    if (prizes.length > 0) {
        prizes.forEach((p, i) => {
            const ganador = p.winner_name || 'Por sortear';
            const numero  = p.winner_number ? `Nº ${p.winner_number}` : '---';
            listaGanadores += `${medallas[i] || '🎁'} *${i+1}° Premio:* ${p.name || p.description || 'Premio'}\n`;
            listaGanadores += `   👤 ${ganador} — ${numero}\n`;
        });
    } else {
        listaGanadores = '🎁 Sin premios registrados\n';
    }

    let mensajeUrgencia = [];
    if (numerosLibres === 0) {
        mensajeUrgencia = [
`🔥🔥🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 *¡SE AGOTÓ!* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya no tiene números disponibles

¡Gracias a todos los que participaron! 🙏
🍀 ¡Hasta el próximo sorteo!`,

`🎊 *¡SOLD OUT TOTAL!* 🎊
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está LLENO 🙌

¡Todos los números tienen dueño!
💪 ¡Qué grupo más comprometido!

🔔 Mantenete atento para el próximo sorteo
🍀 ¡Buena suerte a todos!`,
        ];

    } else if (numerosLibres <= 5) {
        mensajeUrgencia = [
`🔥 *¡ÚLTIMOS ${numerosLibres} NÚMEROS!* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💸 Transferí y mandá el comprobante
✅ ¡Y ya estás adentro!
🍀 ¡No te pierdas esta oportunidad!`,

`⏰ *¡AHORA O NUNCA!* ⏰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
😱 Solo quedan *${numerosLibres}* número(s)!
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El que deja para mañana, se queda sin nada 😤
🏆 *¡RESERVÁ AHORA MISMO!*`,

`💥 *¡CORRAN QUE SE VAN!* 💥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Quedan *${numerosLibres}* número(s) sin dueño 👀
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 Avisá a tus amigos AHORA MISMO
👉 Primero en llegar, primero en participar!`,

`🏃 *¡NO TE QUEDES AFUERA!* 🏃
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️ Solo *${numerosLibres}* número(s) sin reservar!
${numerosDisponiblesLista}
Transferí Gs. ${precioFormato} — Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍀 *¡Tu número de la suerte te está esperando!* 🍀`,
        ];

    } else if (porcentajeVendido >= 70) {
        mensajeUrgencia = [
`⚠️ *¡SE ACERCA EL FINAL!* ⚠️
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Ya se vendió el *${porcentajeVendido}%*!
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ¡Todavía hay tiempo, pero poco!
🍀 ¡Buena suerte!`,

`🔥 *¡MOMENTO CRÍTICO!* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${porcentajeVendido}% vendido* y subiendo 📈
🎫 *LO QUE QUEDA:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚡ ¡Cada minuto que pasa hay menos números!
🏆 *¡ANOTATE YA!*`,

`📢 *¡ÚLTIMA LLAMADA!* 📢
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Solo quedan *${numerosLibres}* números disponibles 😬
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 No esperes más, el cupo se llena!
🍀 ¡Participá antes que sea tarde!`,

`⏳ *¡EL RELOJ CORRE!* ⏳
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¡Ya casi listo! *${porcentajeVendido}%* completado 🎯
🎫 *QUEDAN:*
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️ ¡Los que no se apuren, se pierden el sorteo!
🍀 *¡Animate ahora!*`,
        ];

    } else if (porcentajeVendido >= 40) {
        mensajeUrgencia = [
`⚡ *¡SE VENDE RÁPIDO!* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 *${porcentajeVendido}% vendido* — ¡vamos bien!
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ¡Anotate antes que se agoten!
🍀 ¡Buena suerte!`,

`🚀 *¡ESTÁN VOLANDO LOS NÚMEROS!* 🚀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya vendimos *${numerosVendidos}* de *${totalNumeros}* 🔥
🎫 *LIBRES TODAVÍA:*
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¡Avisá a quien falta! 📣
👉 *¡Que no se quede nadie afuera!*`,

`👀 *¡MIRÁ CÓMO VUELAN!* 👀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${porcentajeVendido}%* del sorteo ya reservado 😮
Todavía podés entrar:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 ¡Elegí el tuyo y transferí ya!
🍀 *¡La suerte está de tu lado!*`,

`💨 *¡A ESTE RITMO NO DURAN!* 💨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📈 Ya somos *${numerosVendidos}* participantes!
Números disponibles:
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🏃 ¡No te quedes mirando!
🏆 *¡Participá y podés ganar!*`,
        ];

    } else {
        mensajeUrgencia = [
`🎉 *¡TODAVÍA HAY NÚMEROS!* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👉 ¡Elegí tu número favorito y participá!
🍀 *¡Buena suerte a todos!*`,

`🌟 *¡ABIERTA LA INSCRIPCIÓN!* 🌟
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
¡Los números están disponibles!
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔥 ¡Empezá el año ganando!
🍀 *¡El tuyo te está esperando!*`,

`🎊 *¡UNITE AL GRUPO GANADOR!* 🎊
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📣 ¡El sorteo está en marcha!
Quedan *${numerosLibres}* números disponibles:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¡Cada número es una oportunidad de ganar! 🏆
💪 *¡Animense, que todavía hay lugar!*`,

`💫 *¡TU SUERTE TE ESPERA HOY!* 💫
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No esperes más, participá en *${raffle_name}* 🎰
Números libres:
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🌈 ¿Y si hoy es tu día de suerte?
🍀 *¡No lo sabrás si no participás!*`,
        ];
    }

    return {
        mensaje_completo:
`🎰✨ *¡SORTEO EN CURSO!* ✨🎰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 *Precio:* Gs. ${precioFormato}
💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 *Estado:* ${porcentajeVendido}% vendido (${numerosVendidos}/${totalNumeros})
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *LISTA DE NÚMEROS:*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaNumeros}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💵 = Pagado confirmado
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 *¿QUERÉS PARTICIPAR?*
👉 Elegí tu número
💸 Realizá tu transferencia
📩 Envianos el comprobante
✅ ¡Y listo!
🍀 *¡Buena suerte a todos!* 🍀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 *Los premios se sortean de menor a mayor*`,

        urgencia: mensajeUrgencia,

        promocion: (discount_active && numerosLibres > 0 ? [
`🎁 *¡OFERTA ESPECIAL — ${discount_pct}% OFF!* 🎁
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
Solo quedan *${numerosLibres} números* y queremos cerrar con todos adentro! 🙌
💥 Normal: ~~Gs. ${precioFormato}~~ → *Gs. ${precioPromo}*
Ahorrás *Gs. ${ahorro}* por número! 💸
${listaPremios}💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📩 Transferí *Gs. ${precioPromo}* y mandá el comprobante
⏰ Válido hasta completar el cupo — *¡ya mismo!*`,

`🔥 *¡${discount_pct}% OFF — CERRAMOS HOY!* 🔥
*${raffle_name}*
${numerosLibres} números 👉 *Gs. ${precioPromo}* c/u *(antes Gs. ${precioFormato})*
${listaPremios}💳 Alias: *${alias}*
⚡ Elegí, transferí, confirmado ✅
🚨 *Solo ${numerosLibres} lugares — se va rápido!*`,

`😱 *¡NO LO VAS A CREER!* 😱
*${discount_pct}% de descuento* en *${raffle_name}*
🏷️ Gs. ${precioFormato} → *Gs. ${precioPromo}*
${listaPremios}💳 *${alias}* — ¡ya mismo!
🍀 *Últimos ${numerosLibres} números — animate!*`,

`💣 *¡BOMBA!* 💣
*${raffle_name}* — últimos *${numerosLibres}* números
💰 *Gs. ${precioPromo}* *(${discount_pct}% OFF)*
${listaPremios}Alias: *${alias}*
🚨 *Transferí ahora y ya sos del sorteo!* 🚨`,

`⚡ *ÚLTIMO MOMENTO* ⚡
${numerosLibres} números en *${raffle_name}*
🔥 *${discount_pct}% OFF = Gs. ${precioPromo}*
~~Normal: Gs. ${precioFormato}~~
Alias: *${alias}* 💳
👇 *¡Mandá el comprobante y listo!*`,

`🎯 *¿TODAVÍA NO PARTICIPASTE?* 🎯
Te damos *${discount_pct}% de descuento* para que no te quedes afuera!
*${raffle_name}* — *Gs. ${precioPromo}* por número
${listaPremios}💳 Alias: *${alias}*
⏰ *Solo por hoy — solo ${numerosLibres} lugares*`,
        ] : null),

        recordatorio_pago:
`💸 *RECORDATORIO DE PAGO* 💸
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Hola! 👋

Aún no confirmamos tu pago para el
🎟️ *${raffle_name}*

💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}

📌 *Pasos:*
1️⃣ Transferí Gs. ${precioFormato}
2️⃣ Compartí el comprobante aquí
3️⃣ ¡Listo! Ya estás confirmado ✅

😊 Si ya transferiste, ignorá este mensaje

¡Gracias! 🙌`,

        invitacion: [
`🎉 *¡TE INVITAMOS A PARTICIPAR!* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
💰 Solo Gs. ${precioFormato} por número

📲 *¿Cómo participar?*
1️⃣ Elegí tu número favorito
2️⃣ Realizá tu transferencia
3️⃣ Envianos el comprobante

🏆 ¡Todos tenemos chance de ganar!
🍀 *¡Buena suerte!* 🍀`,

`🎯 *¡TU MOMENTO ES AHORA!* 🎯
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¿Cuándo vas a animarte si no es hoy? 😏
👉 Elegí, transferí, y ganás!
🍀 *¡La suerte favorece a los valientes!*`,

`🤩 *¿TE ANIMÁS A GANAR?* 🤩
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya está en marcha 🚀

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💸 Solo Gs. ${precioFormato} y podés llevarte todo
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Imaginate ganando esto 🏆
¡Mandá tu número y entrá al juego!
🍀 *¡Buena vibra para todos!*`,

`🌟 *¡PROBÁ TU SUERTE HOY!* 🌟
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No hay excusas para no participar 😄
🎟️ *${raffle_name}*

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔥 ¡Avisá a tus amigos, que hay para todos!
🍀 *¡Hoy puede ser tu gran día!*`,
        ],

        flash: [
`⚡ *FLASH* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎰 *${raffle_name}*

📊 ${porcentajeVendido}% vendido — *${numerosLibres}* libres
💰 Gs. ${precioFormato} por número
🎁 ¡Premios increíbles!
🚀 ¡Participá ahora!

💳 Alias: *${alias}*

Compartí con quien quieras 📢`,

`💬 *ATENCIÓN GRUPO* 💬
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* — ${porcentajeVendido}% vendido 🔥
*${numerosLibres}* números todavía disponibles!

💰 Gs. ${precioFormato} — Alias: *${alias}*

¡Los que no se anotan hoy, se arrepienten mañana! 😤
🏆 *¡Sumate al grupo ganador!*`,

`🎰 *AL TOQUE* 🎰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*
📈 ${porcentajeVendido}% listo | *${numerosLibres}* quedan
💰 Gs. ${precioFormato} | Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 Rápido, elegí y transferí
¡Estamos esperando tu comprobante! 📩`,
        ],

        ultima_oportunidad:
`🚨 *¡ÚLTIMA OPORTUNIDAD!* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

📍 *Quedan: ${numerosLibres} números*
⏰ *¡El tiempo se acaba!*

Los que no reserven ahora
se quedan sin participar ❌

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👇 *¡RESERVÁ AHORA!* 👇`,

        anuncio_ganadores:
`🏆✨ *¡TENEMOS GANADORES!* ✨🏆
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎉 *RESULTADOS OFICIALES:*

${listaGanadores}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎊 *¡Felicitaciones a los ganadores!*
📞 Serán contactados a la brevedad

🙏 Gracias a todos los participantes
🍀 *¡Hasta el próximo sorteo!*`,

        agradecimiento:
`🙏 *¡GRACIAS A TODOS!* 🙏
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Queremos agradecer a cada uno de ustedes
por su confianza y participación ❤️

🌟 Sin ustedes esto no sería posible
🤝 Cada número es una muestra
   de su apoyo incondicional

💪 Seguimos trabajando para traerles
   más y mejores sorteos

🔔 *¡Estén atentos al próximo sorteo!*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍀 *¡Hasta pronto y buena suerte!* 🍀`,

        compartir_grupo:
`🔗 *¡UNITE A NUESTRO GRUPO!* 🔗
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎰 *${raffle_name}*

¿Querés participar en nuestros sorteos?
¡Unite al grupo y enterate de todo! 👇

📲 *GRUPO OFICIAL:*
https://chat.whatsapp.com/JTTNQCrB8cjDKMukLUTY9G?mode=gi_t

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Sorteos en vivo
✅ Resultados al instante
✅ Premios increíbles
✅ ¡100% confiable!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Compartí con tus amigos 📢
🍀 *¡Buena suerte a todos!* 🍀`
    };
}

async function copiarMensaje(key, btnEl) {
    const el = document.getElementById('msg-' + key);
    const texto = el ? (el.textContent || el.innerText || '') : '';
    if (!texto.trim()) { alert('No hay texto para copiar'); return; }
    try {
        await navigator.clipboard.writeText(texto);
        const original = btnEl.innerHTML;
        btnEl.innerHTML = '✅';
        setTimeout(() => { btnEl.innerHTML = original; }, 800);
    } catch (e) {
        alert('Error al copiar: ' + e.message);
    }
}

function escapeHtml(str) {
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

async function exportarRentabilidad(btn) {
    const panel = document.getElementById('panel-rentabilidad');
    const body  = document.getElementById('rentabilidad-body');
    actualizarRentabilidad();

    // Asegurar que el panel esté abierto
    const estabaCerrado = body.classList.contains('hidden');
    if (estabaCerrado) body.classList.remove('hidden');

    btn.innerHTML = '⏳';
    btn.disabled  = true;

    try {
        const canvas = await html2canvas(panel, {
            backgroundColor: '#1A1A1A',
            scale: 2,
            useCORS: true,
            logging: false,
        });

        const nombre = `rentabilidad-{{ $raffle->name ?? 'sorteo' }}-${new Date().toLocaleDateString('es-PY').replace(/\//g,'-')}.png`;

        // Web Share API (mobile) → comparte directo a WhatsApp
        if (navigator.share && navigator.canShare) {
            canvas.toBlob(async (blob) => {
                const file = new File([blob], nombre, { type: 'image/png' });
                if (navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file], title: 'Rentabilidad sorteo' });
                } else {
                    _descargarCanvas(canvas, nombre);
                }
            }, 'image/png');
        } else {
            _descargarCanvas(canvas, nombre);
        }
    } catch(e) {
        alert('Error al generar imagen: ' + e.message);
    } finally {
        btn.innerHTML = '📷 Imagen';
        btn.disabled  = false;
        if (estabaCerrado) body.classList.add('hidden');
    }
}

function _descargarCanvas(canvas, nombre) {
    const a = document.createElement('a');
    a.href     = canvas.toDataURL('image/png');
    a.download = nombre;
    a.click();
}

function recalcularCostos() {
    let total = 0;
    document.querySelectorAll('[id^="cost-"]').forEach(input => {
        total += parseInt(input.value || 0);
    });
    const el = document.getElementById('total-costo-display');
    if (el) el.textContent = 'Gs. ' + total.toLocaleString('es-PY');
    const panel = document.getElementById('panel-rentabilidad');
    if (panel) {
        panel.dataset.totalCost = total;
        actualizarRentabilidad();
    }
}

function formatoGs(valor) {
    return 'Gs. ' + Math.round(valor).toLocaleString('es-PY');
}

function setTexto(id, texto) {
    const el = document.getElementById(id);
    if (el) el.textContent = texto;
}

function setGananciaColor(ganancia) {
    const resumen = document.getElementById('rentabilidad-resumen');
    const gananciaEl = document.getElementById('rentabilidad-ganancia');
    const box = document.getElementById('rentabilidad-ganancia-box');

    if (resumen) resumen.className = ganancia >= 0
        ? 'text-green-400 text-xs font-bold'
        : 'text-red-400 text-xs font-bold';
    if (gananciaEl) gananciaEl.className = ganancia >= 0
        ? 'text-green-400 text-sm font-bold'
        : 'text-red-400 text-sm font-bold';
    if (box) {
        box.className = ganancia >= 0
            ? 'flex justify-between items-center bg-green-900/30 border border-green-500/30 rounded-lg px-3 py-2 mt-1'
            : 'flex justify-between items-center bg-red-900/30 border border-red-500/30 rounded-lg px-3 py-2 mt-1';
    }
}

function actualizarRentabilidad(discountActive = null, discountPct = null) {
    const panel = document.getElementById('panel-rentabilidad');
    if (!panel) return;

    if (discountActive !== null) panel.dataset.discountActive = discountActive ? '1' : '0';
    if (discountPct !== null) panel.dataset.discountPct = discountPct;

    const precioNormal = parseInt(panel.dataset.precioNormal || 0);
    const paid = parseInt(panel.dataset.paid || 0);
    const pendientes = parseInt(panel.dataset.pendientes || 0);
    const free = parseInt(panel.dataset.free || 0);
    const totalCost = parseInt(panel.dataset.totalCost || 0);
    const promoActiva = panel.dataset.discountActive === '1';
    const pct = parseInt(panel.dataset.discountPct || 0);

    const precioLibre = promoActiva ? Math.round(precioNormal * (1 - pct / 100)) : precioNormal;
    const recaudadoConfirmado = paid * precioNormal;
    const recaudadoPendiente = pendientes * precioNormal;
    const recaudadoLibres = free * precioLibre;
    const recaudadoTotal = recaudadoConfirmado + recaudadoPendiente + recaudadoLibres;
    const gananciaTotal = recaudadoTotal - totalCost;
    const gananciaSocio = gananciaTotal / 2;

    const label = document.getElementById('rentabilidad-promo-label');
    if (label) {
        label.textContent = promoActiva ? `${pct}% off` : '';
        label.classList.toggle('hidden', !promoActiva);
    }

    setTexto('rentabilidad-libres', formatoGs(recaudadoLibres));
    setTexto('rentabilidad-total-proyectado', formatoGs(recaudadoTotal));
    setTexto('rentabilidad-total-ingresos', formatoGs(recaudadoTotal));
    setTexto('rentabilidad-ganancia', formatoGs(gananciaTotal));
    setTexto('rentabilidad-socio', formatoGs(gananciaSocio));
    setTexto('rentabilidad-resumen', `${gananciaTotal >= 0 ? '+' : ''}${formatoGs(gananciaTotal)}`);
    setGananciaColor(gananciaTotal);
}

async function guardarCostos(raffleId, btn) {
    const costs = {};
    document.querySelectorAll('[id^="cost-"]').forEach(input => {
        const prizeId = input.id.replace('cost-', '');
        costs[prizeId] = parseInt(input.value || 0);
    });
    const res = await fetch(`/admin/costos/${raffleId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ costs })
    });
    const data = await res.json();
    if (data.ok) {
        document.getElementById('panel-rentabilidad').dataset.totalCost = data.total_cost;
        actualizarRentabilidad();
        btn.innerHTML = '✅ Guardado!';
        setTimeout(() => { btn.innerHTML = '💾 Guardar costos'; location.reload(); }, 1200);
    }
}

async function activarPromo(raffleId, pct) {
    const res = await fetch(`/admin/descuento/${raffleId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ pct })
    });
    if ((await res.json()).ok) {
        actualizarRentabilidad(true, pct);
        location.reload();
    }
}

async function desactivarPromo(raffleId) {
    const res = await fetch(`/admin/descuento/${raffleId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    });
    if ((await res.json()).ok) {
        actualizarRentabilidad(false, 0);
        location.reload();
    }
}

document.addEventListener('DOMContentLoaded', () => actualizarRentabilidad());
</script>

@endsection
