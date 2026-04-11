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
        <input
            id="price"
            name="price"
            required
            type="text"
            placeholder="10.000"
            value="{{ old('price') }}"
            class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400"
        >

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

        <button
            type="submit"
            class="w-full bg-yellow-400 text-black py-4 rounded-xl font-bold mt-2"
        >
            Crear Sorteo
        </button>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Formato precio ─────────────────────────────────────────
    const priceInput = document.getElementById('price');
    if (priceInput) {
        priceInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = new Intl.NumberFormat('es-PY').format(value);
        });
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
</script>

@endsection
