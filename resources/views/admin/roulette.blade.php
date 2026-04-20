@extends('layouts.app')

@section('title', 'Sorteo en vivo')

@section('content')
@php
    $soldNumbers = $raffle->numbers->where('status', 'sold')->values();

    $items = $soldNumbers->map(function ($n) {
        return [
            'id'     => $n->id,
            'number' => str_pad($n->number, 2, '0', STR_PAD_LEFT),
            'name'   => $n->customer_name ?? 'Participante',
        ];
    })->values();

    // Premios ordenados de menor a mayor (orden de sorteo: último primero)
    $prizes        = $raffle->prizes->sortBy('order')->values();
    $isMultiPrize  = $prizes->isNotEmpty();

    // Promo
    $promoEnabled   = (bool) ($raffle->promo_enabled ?? false);
    $promoResults   = $raffle->promoResults ?? collect();
    $allPrizesDrawn = $allPrizesDrawn ?? false;
@endphp

<div class="max-w-6xl mx-auto px-4 py-8 text-center">

    <h1 class="text-4xl font-extrabold text-yellow-400 mb-2">🎰 SORTEO EN VIVO</h1>
    <p class="text-gray-400 text-sm mb-6">{{ $raffle->name }}</p>

    <audio id="spinSound" src="/sounds/spin.mp3"></audio>
    <audio id="winSound"  src="/sounds/win.mp3"></audio>

    <div id="confetti" class="pointer-events-none fixed inset-0 overflow-hidden z-40"></div>

    <!-- Cuenta regresiva -->
    <div id="countdownOverlay"
         class="hidden fixed inset-0 z-50 bg-black/75 flex items-center justify-center">
        <div id="countdownValue"
             class="text-8xl md:text-9xl font-black text-yellow-400 drop-shadow-[0_0_30px_rgba(250,204,21,0.8)] animate-pulse">
            3
        </div>
    </div>

    @if($isMultiPrize)
    <!-- ═══════════════════════════════════════════════════════════
         BARRA DE PROGRESO DE PREMIOS
    ═══════════════════════════════════════════════════════════ -->
    <div id="prizesProgress" class="flex flex-wrap justify-center gap-2 mb-6">
        @foreach($prizes->sortByDesc('order') as $prize)
            <div id="prize-pill-{{ $prize->order }}"
                 class="px-3 py-1 rounded-full text-xs font-bold border transition-all
                        {{ $prize->winner_number ? 'bg-green-500/20 border-green-500 text-green-400' : 'bg-gray-800 border-gray-600 text-gray-500' }}">
                {{ $prize->name }}
                @if($prize->winner_number)
                    <span class="ml-1">✓</span>
                @endif
            </div>
        @endforeach
    </div>

    <!-- HEADER DEL PREMIO ACTUAL -->
    <div id="currentPrizeHeader" class="mb-6">
        <div id="currentPrizeLabel"
             class="text-2xl font-extrabold text-yellow-400 animate-pulse">
            —
        </div>
        <div id="currentPrizeDesc" class="text-gray-400 text-sm mt-1"></div>
    </div>
    @endif

    <!-- Indicador de giro -->
    <div id="spinStatusBox"
         class="hidden max-w-md mx-auto mb-8 rounded-2xl border-2 border-yellow-400 bg-yellow-400/10 px-6 py-5 shadow-xl">
        <div class="text-yellow-300 text-lg font-bold mb-2">🎯 Sorteando...</div>
        <div id="spinTimer" class="text-5xl font-black text-white">6</div>
        <div class="text-gray-300 mt-2 font-semibold">segundos restantes</div>
    </div>

    <!-- RULETA -->
    <div class="relative max-w-4xl mx-auto">
        <div class="absolute left-1/2 -translate-x-1/2 -top-4 z-20">
            <div class="w-0 h-0 border-l-[18px] border-r-[18px] border-b-[28px] border-l-transparent border-r-transparent border-b-red-500 drop-shadow-lg"></div>
        </div>

        <div class="relative rounded-3xl border-4 border-yellow-400 bg-gradient-to-b from-gray-900 to-black shadow-2xl px-6 py-10 overflow-hidden">
            <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-1 bg-yellow-300/40 z-10"></div>
            <div class="overflow-hidden">
                <div id="track" class="flex gap-4 items-center will-change-transform"></div>
            </div>
        </div>
    </div>

    <!-- GANADOR -->
    <div id="winnerBox"
         class="hidden mt-10 max-w-2xl mx-auto rounded-2xl border-2 border-green-400 bg-green-500/10 p-6 shadow-xl">
        <div class="text-4xl mb-3 animate-bounce">🏆</div>
        <div id="winnerPrizeName" class="text-yellow-400 font-bold text-lg mb-1 hidden"></div>
        <h2 class="text-3xl font-extrabold text-green-400 mb-2">GANADOR</h2>
        <div class="text-5xl font-black text-white mb-3">
            Nº <span id="winnerNumber"></span>
        </div>
        <div class="text-2xl text-yellow-300 font-bold">
            <span id="winnerName"></span>
        </div>
    </div>

    @if($isMultiPrize)
    <!-- RESUMEN FINAL (se muestra cuando todos los premios están sorteados) -->
    <div id="finalSummary" class="hidden mt-8 max-w-2xl mx-auto space-y-3">
        <h3 class="text-yellow-400 font-extrabold text-xl mb-4">🎉 Todos los premios sorteados</h3>
        <div id="finalPrizesList" class="space-y-2"></div>
    </div>
    @endif

    @if($promoEnabled)
    <!-- SECCIÓN PROMO — oculta hasta que termina el sorteo principal -->
    <div id="promoSection" class="{{ $allPrizesDrawn ? '' : 'hidden' }} mt-6 max-w-2xl mx-auto">

        @if($promoResults->isEmpty())
        {{-- Promo pendiente --}}
        <div id="promoPending" class="bg-yellow-900/20 border border-yellow-500/40 rounded-2xl p-5 text-center">
            <div class="text-yellow-400 font-bold text-lg mb-1">🎁 {{ $raffle->promo_prize_text }}</div>
            <div class="text-gray-400 text-sm mb-4">
                Primeros {{ $raffle->promo_limit }} reservados &middot; {{ $raffle->promo_winner_count }} ganador(es)
            </div>
            <button id="promoBtn"
                onclick="runPromo()"
                class="bg-yellow-400 hover:bg-yellow-300 text-black px-6 py-3 rounded-xl font-bold transition">
                🎁 Sortear Promo
            </button>
        </div>

        @else
        {{-- Resultados ya guardados (recarga de página) --}}
        <div class="bg-yellow-900/20 border border-yellow-500/40 rounded-2xl p-5">
            <h3 class="text-yellow-400 font-bold text-center mb-3">🎁 Ganadores de la Promo</h3>
            <div class="space-y-2">
                @foreach($promoResults as $r)
                <div class="flex justify-between items-center bg-yellow-500/10 border border-yellow-500/20 rounded-xl px-4 py-3">
                    <div class="text-yellow-300 font-bold text-sm">{{ $r->prize_text }}</div>
                    <div class="text-right">
                        <div class="text-white font-black">Nº {{ str_pad($r->raffleNumber->number ?? '?', 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-gray-300 text-xs">{{ $r->customer_name }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    @endif

    <!-- BOTÓN PRINCIPAL -->
    <button id="spinBtn"
        class="mt-8 bg-yellow-400 hover:bg-yellow-300 text-black px-8 py-4 rounded-2xl font-extrabold text-lg shadow-lg transition transform hover:scale-105">
        🎯 INICIAR SORTEO
    </button>

</div>

<style>
    #track { transition: transform 6s cubic-bezier(0.08, 0.75, 0.12, 1); }

    .draw-card {
        width: 150px; min-width: 150px; height: 150px;
        border-radius: 1rem;
        border: 2px solid rgba(255,255,255,.15);
        background: linear-gradient(180deg, #374151, #1f2937);
        color: white;
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        box-shadow: 0 10px 25px rgba(0,0,0,.35);
    }
    .draw-card-number { font-size: 2rem; font-weight: 900; line-height: 1; }
    .draw-card-name {
        margin-top: .75rem; font-size: .95rem; font-weight: 700;
        color: #fde047; max-width: 120px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .winner-card {
        animation: winnerPulse 1s ease-in-out infinite alternate;
        border-color: #facc15 !important;
        box-shadow: 0 0 20px rgba(250,204,21,0.5), 0 0 40px rgba(250,204,21,0.25);
    }
    @keyframes winnerPulse {
        from { transform: scale(1); }
        to   { transform: scale(1.06); }
    }
    .confetti-piece {
        position: absolute; width: 10px; height: 18px;
        opacity: .9; animation: confettiFall linear forwards;
    }
    @keyframes confettiFall {
        0%   { transform: translateY(-20px) rotate(0deg); opacity: 1; }
        100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
    }
</style>

<script>
// ── Datos desde PHP ──────────────────────────────────────────────────────────
const baseItems            = @json($items);
const raffleId             = @json($raffle->id);
const csrfToken            = @json(csrf_token());
const existingWinnerNumber = @json($raffle->winner_number);
const isMultiPrize         = @json($isMultiPrize);
const promoEnabled         = @json($promoEnabled);

// Premios en orden de sorteo: index 0 = último premio (order=1), último index = 1er premio
let prizes = @json($prizes->values());

// ── Estado ───────────────────────────────────────────────────────────────────
let isRunning         = false;
let countdownInterval = null;
let spinInterval      = null;

// Índice del premio actual (primer sin ganador)
let currentPrizeIdx = isMultiPrize
    ? prizes.findIndex(p => !p.winner_number)
    : -1;   // -1 = modo legacy

// ── Elementos DOM ────────────────────────────────────────────────────────────
const track              = document.getElementById('track');
const spinBtn            = document.getElementById('spinBtn');
const winnerBox          = document.getElementById('winnerBox');
const winnerPrizeName    = document.getElementById('winnerPrizeName');
const winnerNumberEl     = document.getElementById('winnerNumber');
const winnerNameEl       = document.getElementById('winnerName');
const spinSound          = document.getElementById('spinSound');
const winSound           = document.getElementById('winSound');
const confettiEl         = document.getElementById('confetti');
const countdownOverlay   = document.getElementById('countdownOverlay');
const countdownValue     = document.getElementById('countdownValue');
const spinStatusBox      = document.getElementById('spinStatusBox');
const spinTimer          = document.getElementById('spinTimer');
const currentPrizeLabel  = document.getElementById('currentPrizeLabel');
const currentPrizeDesc   = document.getElementById('currentPrizeDesc');
const finalSummary       = document.getElementById('finalSummary');
const finalPrizesList    = document.getElementById('finalPrizesList');

// ── Track ─────────────────────────────────────────────────────────────────────
function buildTrack() {
    track.style.transition = 'none';
    track.style.transform  = 'translateX(0)';
    track.innerHTML        = '';

    const expanded = [];
    for (let r = 0; r < 14; r++) {
        baseItems.forEach(item => expanded.push({ ...item }));
    }

    expanded.forEach((item, index) => {
        const card = document.createElement('div');
        card.className      = 'draw-card';
        card.dataset.number = item.number;
        card.dataset.name   = item.name;
        card.dataset.index  = index;
        card.innerHTML = `
            <div class="draw-card-number">${item.number}</div>
            <div class="draw-card-name">${item.name}</div>
        `;
        track.appendChild(card);
    });
}

function centerCard(card, animated = true) {
    const container    = track.parentElement;
    const containerWidth = container.offsetWidth;
    const translate    = card.offsetLeft - (containerWidth / 2) + (card.offsetWidth / 2);

    track.style.transition = animated
        ? 'transform 6s cubic-bezier(0.08, 0.75, 0.12, 1)'
        : 'none';

    track.style.transform = `translateX(-${translate}px)`;
}

// ── Confetti ──────────────────────────────────────────────────────────────────
function launchConfetti() {
    confettiEl.innerHTML = '';
    const colors = ['#facc15','#22c55e','#ef4444','#3b82f6','#a855f7','#ffffff'];

    for (let i = 0; i < 120; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left            = Math.random() * 100 + 'vw';
        piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        piece.style.animationDuration = (2 + Math.random() * 2) + 's';
        piece.style.animationDelay  = (Math.random() * 0.4) + 's';
        confettiEl.appendChild(piece);
    }
    setTimeout(() => { confettiEl.innerHTML = ''; }, 5000);
}

// ── UI helpers ────────────────────────────────────────────────────────────────
function showWinner(number, name, prizeName) {
    winnerNumberEl.textContent = number;
    winnerNameEl.textContent   = name;

    if (prizeName && winnerPrizeName) {
        winnerPrizeName.textContent = prizeName;
        winnerPrizeName.classList.remove('hidden');
    } else if (winnerPrizeName) {
        winnerPrizeName.classList.add('hidden');
    }

    winnerBox.classList.remove('hidden');
}

function updatePrizeHeader() {
    if (!isMultiPrize || currentPrizeIdx < 0) return;

    const prize = prizes[currentPrizeIdx];
    if (!prize) return;

    if (currentPrizeLabel) {
        currentPrizeLabel.textContent = `Sorteando: ${prize.name}`;
    }
    if (currentPrizeDesc) {
        currentPrizeDesc.textContent = prize.description ?? '';
    }
}

function markPillDrawn(order) {
    const pill = document.getElementById(`prize-pill-${order}`);
    if (!pill) return;
    pill.classList.remove('bg-gray-800','border-gray-600','text-gray-500','bg-yellow-400/10','border-yellow-400','text-yellow-400');
    pill.classList.add('bg-green-500/20','border-green-500','text-green-400');
    pill.innerHTML += ' <span>✓</span>';
}

function markPillCurrent(order) {
    const pill = document.getElementById(`prize-pill-${order}`);
    if (!pill) return;
    pill.classList.remove('bg-gray-800','border-gray-600','text-gray-500');
    pill.classList.add('bg-yellow-400/10','border-yellow-400','text-yellow-400','animate-pulse');
}

function showFinalSummary() {
    if (!finalSummary) return;

    finalPrizesList.innerHTML = '';
    // Mostrar de mayor a menor (1er premio primero en resumen)
    const sorted = [...prizes].sort((a, b) => b.order - a.order);

    sorted.forEach(p => {
        finalPrizesList.innerHTML += `
            <div class="flex justify-between items-center bg-green-900/30 border border-green-500/30 rounded-xl px-4 py-3">
                <div class="text-left">
                    <div class="text-green-400 font-bold text-sm">${p.name}</div>
                    ${p.description ? `<div class="text-gray-400 text-xs">${p.description}</div>` : ''}
                </div>
                <div class="text-right">
                    <div class="text-white font-black">Nº ${String(p.winner_number).padStart(2,'0')}</div>
                    <div class="text-yellow-300 text-xs">${p.winner_name ?? ''}</div>
                </div>
            </div>
        `;
    });

    finalSummary.classList.remove('hidden');
}

// ── Promo ─────────────────────────────────────────────────────────────────────
function showPromoSection() {
    const section = document.getElementById('promoSection');
    if (section) section.classList.remove('hidden');
}

async function runPromo() {
    const promoBtn = document.getElementById('promoBtn');
    promoBtn.disabled    = true;
    promoBtn.textContent = '⏳ Sorteando...';

    let response;
    try {
        response = await fetch(`/admin/sortear-promo/${raffleId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ _token: csrfToken }).toString(),
        });
    } catch (e) {
        alert('No se pudo conectar con el servidor.');
        promoBtn.disabled    = false;
        promoBtn.textContent = '🎁 Sortear Promo';
        return;
    }

    const data = await response.json();

    if (!response.ok || !data.success) {
        alert(data.message ?? 'Error al ejecutar la promo.');
        promoBtn.disabled    = false;
        promoBtn.textContent = '🎁 Sortear Promo';
        return;
    }

    const winnersHtml = data.winners.map(w => `
        <div class="flex justify-between items-center bg-yellow-500/10 border border-yellow-500/20 rounded-xl px-4 py-3">
            <div class="text-yellow-300 font-bold text-sm">${w.prize_text}</div>
            <div class="text-right">
                <div class="text-white font-black">Nº ${String(w.number).padStart(2, '0')}</div>
                <div class="text-gray-300 text-xs">${w.customer_name}</div>
            </div>
        </div>
    `).join('');

    document.getElementById('promoSection').innerHTML = `
        <div class="bg-yellow-900/20 border border-yellow-500/40 rounded-2xl p-5">
            <h3 class="text-yellow-400 font-bold text-center mb-3">🎁 Ganadores de la Promo</h3>
            <div class="space-y-2">${winnersHtml}</div>
        </div>
    `;
}

function resetUI() {
    isRunning = false;
    clearInterval(countdownInterval);
    clearInterval(spinInterval);
    spinBtn.disabled = false;
    spinBtn.classList.remove('opacity-60','cursor-not-allowed');

    try { spinSound.pause(); spinSound.currentTime = 0; } catch(e) {}
}

// ── Inicio de secuencia ───────────────────────────────────────────────────────
function startSequence() {
    if (isRunning || baseItems.length === 0) return;

    isRunning = true;
    winnerBox.classList.add('hidden');
    document.querySelectorAll('.draw-card').forEach(el => el.classList.remove('winner-card'));

    spinBtn.disabled = true;
    spinBtn.classList.add('opacity-60','cursor-not-allowed');
    spinBtn.textContent = '⏳ PREPARANDO...';

    let count = 3;
    countdownOverlay.classList.remove('hidden');
    countdownValue.textContent = count;

    countdownInterval = setInterval(() => {
        count--;
        if (count > 0) {
            countdownValue.textContent = count;
            return;
        }

        clearInterval(countdownInterval);
        countdownValue.textContent = '🎉';

        setTimeout(() => {
            countdownOverlay.classList.add('hidden');
            runDraw();
        }, 500);
    }, 1000);
}

// ── Sorteo principal ──────────────────────────────────────────────────────────
async function runDraw() {
    buildTrack();

    // Forzar reflow para que la transición funcione tras rebuild
    track.getBoundingClientRect();

    spinStatusBox.classList.remove('hidden');
    spinTimer.textContent = '6';

    try {
        spinSound.currentTime = 0;
        spinSound.loop = true;
        await spinSound.play().catch(() => {});
    } catch(e) {}

    // Body del request
    const body = new URLSearchParams({ _token: csrfToken });
    if (isMultiPrize && currentPrizeIdx >= 0) {
        body.append('prize_order', prizes[currentPrizeIdx].order);
    }

    let response;
    try {
        response = await fetch(`/admin/sortear/${raffleId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        });
    } catch(e) {
        alert('No se pudo conectar con el servidor.');
        resetUI();
        return;
    }

    if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        alert(err.message ?? 'Ocurrió un error al elegir el ganador.');
        resetUI();
        return;
    }

    const data = await response.json();

    const winNum   = String(data.winner_number).padStart(2, '0');
    const winName  = data.winner_name ?? 'Participante';
    const prizeName = data.prize_name ?? null;

    // Buscar tarjeta ganadora en el track
    const allCards = [...document.querySelectorAll('.draw-card')];
    let target = allCards.find((card, idx) =>
        idx > baseItems.length * 7 &&
        card.dataset.number === winNum &&
        card.dataset.name   === winName
    );
    if (!target) {
        target = allCards.find((card, idx) =>
            idx > baseItems.length * 7 &&
            card.dataset.number === winNum
        );
    }
    if (!target) {
        alert('No se encontró la tarjeta ganadora.');
        resetUI();
        return;
    }

    let secondsLeft = 6;
    spinInterval = setInterval(() => {
        secondsLeft--;
        if (secondsLeft >= 0) spinTimer.textContent = secondsLeft;
    }, 1000);

    centerCard(target, true);

    setTimeout(async () => {
        clearInterval(spinInterval);

        try {
            spinSound.pause(); spinSound.currentTime = 0;
            winSound.currentTime = 0;
            await winSound.play().catch(() => {});
        } catch(e) {}

        spinStatusBox.classList.add('hidden');
        target.classList.add('winner-card');
        showWinner(winNum, winName, prizeName);
        launchConfetti();

        isRunning = false;

        // ── Post-sorteo ──────────────────────────────────────────────
        if (isMultiPrize) {
            // Actualizar estado local del premio recién sorteado
            prizes[currentPrizeIdx].winner_number = winNum;
            prizes[currentPrizeIdx].winner_name   = winName;
            markPillDrawn(prizes[currentPrizeIdx].order);

            if (data.all_drawn) {
                // Todos los premios sorteados
                if (currentPrizeLabel) currentPrizeLabel.textContent = '🎉 ¡Sorteo completado!';
                if (currentPrizeDesc)  currentPrizeDesc.textContent  = '';

                showFinalSummary();
                if (promoEnabled) showPromoSection();

                spinBtn.textContent = '⬅️ VOLVER AL PANEL';
                spinBtn.disabled    = false;
                spinBtn.classList.remove('opacity-60','cursor-not-allowed');
                spinBtn.onclick = () => { window.location.href = '/admin'; };
            } else {
                // Hay más premios
                currentPrizeIdx = prizes.findIndex(p => !p.winner_number);

                if (currentPrizeIdx >= 0) {
                    markPillCurrent(prizes[currentPrizeIdx].order);
                    updatePrizeHeader();
                }

                spinBtn.textContent = `▶️ SIGUIENTE PREMIO`;
                spinBtn.disabled    = false;
                spinBtn.classList.remove('opacity-60','cursor-not-allowed');
                spinBtn.onclick = () => startSequence();
            }

        } else {
            // Legacy: un solo ganador
            spinBtn.textContent = '⬅️ VOLVER AL PANEL';
            spinBtn.disabled    = false;
            spinBtn.classList.remove('opacity-60','cursor-not-allowed');
            spinBtn.onclick = () => { window.location.href = '/admin'; };
            if (promoEnabled) showPromoSection();
        }

    }, 6000);
}

// ── Inicialización ────────────────────────────────────────────────────────────
buildTrack();

if (isMultiPrize) {

    if (currentPrizeIdx < 0) {
        // Todos los premios ya sorteados (recarga de página)
        if (currentPrizeLabel) currentPrizeLabel.textContent = '🎉 ¡Sorteo completado!';
        if (currentPrizeDesc)  currentPrizeDesc.textContent  = '';

        showFinalSummary();
        spinBtn.textContent = '⬅️ VOLVER AL PANEL';
        spinBtn.onclick = () => { window.location.href = '/admin'; };

    } else {
        // Marcar premios ya sorteados
        prizes.forEach(p => {
            if (p.winner_number) markPillDrawn(p.order);
        });
        // Marcar el actual
        markPillCurrent(prizes[currentPrizeIdx].order);
        updatePrizeHeader();

        spinBtn.onclick = () => startSequence();
    }

} else {
    // ── Modo legacy ──────────────────────────────────────────────────────────
    spinBtn.onclick = () => startSequence();

    if (existingWinnerNumber) {
        const existing = [...document.querySelectorAll('.draw-card')].find((card, idx) =>
            idx > baseItems.length * 2 &&
            card.dataset.number === String(existingWinnerNumber).padStart(2, '0')
        );

        if (existing) {
            centerCard(existing, false);
            existing.classList.add('winner-card');
            showWinner(String(existingWinnerNumber).padStart(2, '0'), existing.dataset.name || 'Participante', null);

            spinBtn.textContent = '⬅️ VOLVER AL PANEL';
            spinBtn.onclick = () => { window.location.href = '/admin'; };
        }
    }
}
</script>

@endsection
