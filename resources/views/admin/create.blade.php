@extends('layouts.app')

@section('title', 'Crear Sorteo')

@section('content')

<div class="bg-[#141414] p-6 rounded-2xl border border-yellow-500/30 shadow-lg">

    <h1 class="text-2xl text-yellow-400 mb-5 text-center font-bold">
        🎁 Crear Sorteo
    </h1>

    @if ($errors->any())
        <div class="bg-red-500 text-white p-3 rounded mb-4">
            @foreach ($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <!-- NOMBRE -->
        <input
            name="name"
            required
            placeholder="Nombre del sorteo"
            value="{{ old('name') }}"
            class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
        >

        <!-- PRECIO -->
        <div class="space-y-2">
            <input
                id="price"
                name="price"
                required
                type="text"
                inputmode="numeric"
                placeholder="10.000"
                value="{{ old('price') }}"
                class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
            >

            <label class="flex items-center gap-2 text-sm text-yellow-100">
                <input
                    id="freeRaffle"
                    name="free_raffle"
                    type="checkbox"
                    value="1"
                    class="h-4 w-4 rounded border-yellow-400 bg-black text-yellow-400"
                    @checked(old('price') === '0')
                >
                Sorteo gratis (precio 0 Gs)
            </label>
        </div>

        <!-- CANTIDAD DE NÚMEROS -->
        <input
            name="total_numbers"
            required
            type="number"
            min="1"
            placeholder="Cantidad de números (ej: 50)"
            value="{{ old('total_numbers') }}"
            class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
        >

        <!-- IMAGEN -->
        <input
            type="file"
            name="image"
            accept="image/*"
            required
            class="w-full p-3 rounded-xl bg-black text-white border border-yellow-400"
        >
        <img id="preview" class="mt-3 rounded-xl hidden w-full h-40 object-cover" />

        <!-- SEPARADOR -->
        <div class="border-t border-yellow-500/30 pt-4">
            <h2 class="text-yellow-400 font-bold mb-1">🏆 Premios del sorteo</h2>
            <p class="text-gray-400 text-xs mb-4">
                El sorteo irá del último premio al 1er premio para generar suspenso.
            </p>

            <!-- CANTIDAD DE PREMIOS -->
            <div class="flex items-center gap-3 mb-4">
                <label class="text-white text-sm whitespace-nowrap">Cantidad de premios:</label>
                <input
                    id="prizesCount"
                    name="prizes_count"
                    type="number"
                    min="1"
                    max="20"
                    value="{{ old('prizes_count', 1) }}"
                    class="w-24 p-3 rounded-xl bg-black text-white border border-yellow-400 text-center font-bold"
                >
            </div>

            <!-- PREMIOS DINÁMICOS -->
            <div id="prizesContainer" class="space-y-3"></div>
        </div>

        <!-- ── PROMO ─────────────────────────────────────────────────── -->
        <div class="border-t border-yellow-500/30 pt-4">

            <div class="flex items-center gap-3 mb-3">
                <input
                    type="checkbox"
                    id="promoEnabled"
                    name="promo_enabled"
                    value="1"
                    {{ old('promo_enabled') ? 'checked' : '' }}
                    onchange="togglePromo(this.checked)"
                    class="w-5 h-5 accent-yellow-400 cursor-pointer"
                >
                <label for="promoEnabled" class="text-white font-bold cursor-pointer">
                    🎁 Activar promo especial
                </label>
            </div>

            <div id="promoFields" class="{{ old('promo_enabled') ? '' : 'hidden' }} space-y-3 pl-2">

                <select
                    name="promo_type"
                    id="promoType"
                    onchange="togglePromoLimit(this.value)"
                    class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
                >
                    <option value="early_numbers" {{ old('promo_type', 'early_numbers') === 'early_numbers' ? 'selected' : '' }}>
                        Primeros números adquiridos
                    </option>
                    <option value="most_numbers" {{ old('promo_type') === 'most_numbers' ? 'selected' : '' }}>
                        Quien más números compre
                    </option>
                </select>

                <div id="promoLimitField" class="{{ old('promo_type') === 'most_numbers' ? 'hidden' : '' }}">
                    <input
                        type="number"
                        name="promo_limit"
                        min="1"
                        placeholder="Primeros X números que participan (ej: 20)"
                        value="{{ old('promo_limit') }}"
                        class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
                    >
                </div>

                <input
                    type="number"
                    name="promo_winner_count"
                    min="1"
                    placeholder="Cantidad de ganadores de la promo (ej: 1)"
                    value="{{ old('promo_winner_count') }}"
                    class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
                >

                <input
                    type="text"
                    name="promo_prize_text"
                    placeholder="Premio de la promo (ej: Canasta navideña)"
                    value="{{ old('promo_prize_text') }}"
                    class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
                >

                <p id="promoHint" class="text-gray-500 text-xs">
                    Los primeros <strong class="text-gray-300">X</strong> números en reservar participan.
                    Solo los confirmados como pagados entran al sorteo de la promo.
                    Se ejecuta al final, después de todos los premios principales.
                </p>

            </div>
        </div>

        <!-- ── SORTEO PONDERADO ───────────────────────────────────────── -->
        <div class="border-t border-yellow-500/30 pt-4">

            <div class="flex items-center gap-3 mb-3">
                <input
                    type="checkbox"
                    id="weightedDrawEnabled"
                    name="weighted_draw_enabled"
                    value="1"
                    {{ old('weighted_draw_enabled') ? 'checked' : '' }}
                    onchange="toggleWeightedDraw(this.checked)"
                    class="w-5 h-5 accent-yellow-400 cursor-pointer"
                >
                <label for="weightedDrawEnabled" class="text-white font-bold cursor-pointer">
                    ⚖️ Activar sorteo ponderado
                </label>
            </div>

            <div id="weightedDrawFields" class="{{ old('weighted_draw_enabled') ? '' : 'hidden' }} space-y-3 pl-2">
                <p class="text-gray-400 text-xs leading-relaxed">
                    Reduce las probabilidades de ganar para quienes ya ganaron un premio en este sorteo.
                    <strong class="text-gray-300">Peso 1.0</strong> = probabilidad normal ·
                    <strong class="text-gray-300">Peso 0.0</strong> = bloqueado.
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-[#1a1a1a] border border-yellow-500/20 rounded-xl p-3">
                        <div class="text-xs text-yellow-300 font-bold mb-1">Sin victorias</div>
                        <div class="text-xs text-gray-500 mb-2">Peso por número</div>
                        <input
                            type="number" step="0.01" min="0" max="1"
                            name="win_weights[0]"
                            value="{{ old('win_weights.0', 1.0) }}"
                            class="w-full p-2 rounded-lg bg-black text-white border border-gray-600 text-sm text-center font-bold"
                        >
                    </div>
                    <div class="bg-[#1a1a1a] border border-orange-500/20 rounded-xl p-3">
                        <div class="text-xs text-orange-300 font-bold mb-1">Ganó 1 premio</div>
                        <div class="text-xs text-gray-500 mb-2">Peso por número</div>
                        <input
                            type="number" step="0.01" min="0" max="1"
                            name="win_weights[1]"
                            value="{{ old('win_weights.1', 0.5) }}"
                            class="w-full p-2 rounded-lg bg-black text-white border border-gray-600 text-sm text-center font-bold"
                        >
                    </div>
                    <div class="bg-[#1a1a1a] border border-red-500/20 rounded-xl p-3">
                        <div class="text-xs text-red-300 font-bold mb-1">Ganó 2 premios</div>
                        <div class="text-xs text-gray-500 mb-2">Peso por número</div>
                        <input
                            type="number" step="0.01" min="0" max="1"
                            name="win_weights[2]"
                            value="{{ old('win_weights.2', 0.25) }}"
                            class="w-full p-2 rounded-lg bg-black text-white border border-gray-600 text-sm text-center font-bold"
                        >
                    </div>
                    <div class="bg-[#1a1a1a] border border-gray-500/20 rounded-xl p-3">
                        <div class="text-xs text-gray-400 font-bold mb-1">Ganó 3+ premios</div>
                        <div class="text-xs text-gray-500 mb-2">Peso por número</div>
                        <input
                            type="number" step="0.01" min="0" max="1"
                            name="win_weights[3]"
                            value="{{ old('win_weights.3', 0.0) }}"
                            class="w-full p-2 rounded-lg bg-black text-white border border-gray-600 text-sm text-center font-bold"
                        >
                    </div>
                </div>

                <p class="text-gray-600 text-xs">
                    Esta regla aplica solo dentro del mismo sorteo. No afecta sorteos futuros.
                </p>
            </div>
        </div>

        <button
            type="submit"
            id="submitBtn"
            class="w-full bg-yellow-400 text-black py-4 rounded-xl font-bold mt-2"
        >
            Crear Sorteo
        </button>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Protección doble clic ──────────────────────────────────
    const form      = document.querySelector('form');
    const submitBtn = document.getElementById('submitBtn');
    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creando sorteo…';
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    });

    // ── Formato precio ─────────────────────────────────────────
    const priceInput = document.getElementById('price');
    const freeRaffle = document.getElementById('freeRaffle');

    function syncFreeRaffle() {
        if (!priceInput || !freeRaffle) return;

        if (freeRaffle.checked) {
            priceInput.value = '0';
            priceInput.readOnly = true;
            priceInput.classList.add('opacity-70');
        } else {
            priceInput.readOnly = false;
            priceInput.classList.remove('opacity-70');
        }
    }

    if (priceInput) {
        priceInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value === '') {
                e.target.value = '';
                return;
            }
            e.target.value = new Intl.NumberFormat('es-PY').format(value);
        });
    }
    if (freeRaffle) {
        freeRaffle.addEventListener('change', syncFreeRaffle);
        syncFreeRaffle();
    }

    // ── Preview imagen ─────────────────────────────────────────
    const fileInput = document.querySelector('input[name="image"]');
    const preview   = document.getElementById('preview');
    fileInput.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        }
    });

    // ── Premios dinámicos ──────────────────────────────────────
    const prizesCountInput = document.getElementById('prizesCount');
    const prizesContainer  = document.getElementById('prizesContainer');

    const oldPrizes = @json(old('prizes', []));

    function ordinalLabel(pos, total) {
        if (pos === 1) return '🥇 1er Premio <span class="text-yellow-300 text-xs">(Principal — se sortea al final)</span>';
        if (pos === 2) return '🥈 2do Premio';
        if (pos === 3) return '🥉 3er Premio';
        return `🏅 ${pos}to Premio`;
    }

    function renderPrizes(count) {
        prizesContainer.innerHTML = '';

        for (let i = 0; i < count; i++) {
            const pos       = i + 1;           // 1 = más importante
            const oldName   = oldPrizes[i] ? oldPrizes[i].name        : '';
            const oldDesc   = oldPrizes[i] ? oldPrizes[i].description : '';
            const isLast    = pos === count;
            const extraNote = isLast && count > 1
                ? '<span class="text-gray-500 text-xs ml-2">(se sortea primero)</span>'
                : '';

            prizesContainer.innerHTML += `
                <div class="bg-[#1a1a1a] border border-yellow-500/20 rounded-xl p-4 space-y-2">
                    <div class="text-sm font-bold text-yellow-400">
                        ${ordinalLabel(pos, count)}${extraNote}
                    </div>
                    <input
                        name="prizes[${i}][name]"
                        required
                        placeholder="Nombre del premio (ej: Moto 0km Honda)"
                        value="${escHtml(oldName)}"
                        class="w-full p-3 rounded-lg bg-black text-white border border-gray-600 focus:border-yellow-400 text-sm"
                    >
                    <input
                        name="prizes[${i}][description]"
                        placeholder="Descripción adicional (opcional)"
                        value="${escHtml(oldDesc)}"
                        class="w-full p-3 rounded-lg bg-black text-gray-300 border border-gray-700 focus:border-yellow-400 text-sm"
                    >
                </div>
            `;
        }
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    prizesCountInput.addEventListener('input', function () {
        const val = Math.max(1, Math.min(20, parseInt(this.value) || 1));
        this.value = val;
        renderPrizes(val);
    });

    // Render inicial
    renderPrizes(parseInt(prizesCountInput.value) || 1);
});

function togglePromo(enabled) {
    document.getElementById('promoFields').classList.toggle('hidden', !enabled);
}

function toggleWeightedDraw(enabled) {
    document.getElementById('weightedDrawFields').classList.toggle('hidden', !enabled);
}

function togglePromoLimit(type) {
    const limitField = document.getElementById('promoLimitField');
    const hint       = document.getElementById('promoHint');
    const isMost     = type === 'most_numbers';

    limitField.classList.toggle('hidden', isMost);

    hint.innerHTML = isMost
        ? 'Gana el cliente que más números haya comprado y pagado. En caso de empate, gana quien compró primero.'
        : 'Los primeros <strong class="text-gray-300">X</strong> números en reservar participan. Solo los confirmados como pagados entran al sorteo de la promo. Se ejecuta al final, después de todos los premios principales.';
}
</script>

@endsection
