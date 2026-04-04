@extends('layouts.app')

@section('title', 'Sorteo en vivo')

@section('content')
@php
    $soldNumbers = $raffle->numbers->where('status', 'sold')->values();

    $items = $soldNumbers->map(function ($n) {
        return [
            'id' => $n->id,
            'number' => str_pad($n->number, 2, '0', STR_PAD_LEFT),
            'name' => $n->buyer_name ?? $n->name ?? $n->customer_name ?? 'Participante',
        ];
    })->values();
@endphp

<div class="max-w-6xl mx-auto px-4 py-8 text-center">
    <h1 class="text-4xl font-extrabold text-yellow-400 mb-2">
        🎰 SORTEO EN VIVO
    </h1>

    <p class="text-gray-300 mb-6">
        Preparando sorteo automático
    </p>

    <audio id="spinSound" src="/sounds/spin.mp3"></audio>
    <audio id="winSound" src="/sounds/win.mp3"></audio>

    <div id="confetti" class="pointer-events-none fixed inset-0 overflow-hidden z-40"></div>

    <!-- Cuenta regresiva 3 2 1 -->
    <div id="countdownOverlay"
         class="hidden fixed inset-0 z-50 bg-black/75 flex items-center justify-center">
        <div id="countdownValue"
             class="text-8xl md:text-9xl font-black text-yellow-400 drop-shadow-[0_0_30px_rgba(250,204,21,0.8)] animate-pulse">
            3
        </div>
    </div>

    <!-- Indicador de giro -->
    <div id="spinStatusBox"
         class="hidden max-w-md mx-auto mb-8 rounded-2xl border-2 border-yellow-400 bg-yellow-400/10 px-6 py-5 shadow-xl">
        <div class="text-yellow-300 text-lg font-bold mb-2">
            🎯 Sorteando ganador...
        </div>
        <div id="spinTimer" class="text-5xl font-black text-white">
            6
        </div>
        <div class="text-gray-300 mt-2 font-semibold">
            segundos restantes
        </div>
    </div>

    <div class="relative max-w-4xl mx-auto">
        <div class="absolute left-1/2 -translate-x-1/2 -top-4 z-20">
            <div class="w-0 h-0 border-l-[18px] border-r-[18px] border-b-[28px] border-l-transparent border-r-transparent border-b-red-500 drop-shadow-lg"></div>
        </div>

        <div class="relative rounded-3xl border-4 border-yellow-400 bg-gradient-to-b from-gray-900 to-black shadow-2xl px-6 py-10 overflow-hidden">
            <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-1 bg-yellow-300/40 z-10"></div>

            <div class="overflow-hidden">
                <div id="track" class="flex gap-4 items-center will-change-transform">
                </div>
            </div>
        </div>
    </div>

    <div id="winnerBox"
         class="hidden mt-10 max-w-2xl mx-auto rounded-2xl border-2 border-green-400 bg-green-500/10 p-6 shadow-xl">
        <div class="text-4xl mb-3 animate-bounce">🏆</div>
        <h2 class="text-3xl font-extrabold text-green-400 mb-2">GANADOR</h2>
        <div class="text-5xl font-black text-white mb-3">
            Nº <span id="winnerNumber"></span>
        </div>
        <div class="text-2xl text-yellow-300 font-bold">
            <span id="winnerName"></span>
        </div>
    </div>

    <button id="spinBtn"
        onclick="startSequence()"
        class="mt-8 bg-yellow-400 hover:bg-yellow-300 text-black px-8 py-4 rounded-2xl font-extrabold text-lg shadow-lg transition transform hover:scale-105">
        🎯 INICIAR SORTEO
    </button>
</div>

<style>
    #track {
        transition: transform 6s cubic-bezier(0.08, 0.75, 0.12, 1);
    }

    .draw-card {
        width: 150px;
        min-width: 150px;
        height: 150px;
        border-radius: 1rem;
        border: 2px solid rgba(255,255,255,.15);
        background: linear-gradient(180deg, #374151, #1f2937);
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        box-shadow: 0 10px 25px rgba(0,0,0,.35);
    }

    .draw-card-number {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1;
    }

    .draw-card-name {
        margin-top: .75rem;
        font-size: .95rem;
        font-weight: 700;
        color: #fde047;
        max-width: 120px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .winner-card {
        animation: winnerPulse 1s ease-in-out infinite alternate;
        border-color: #facc15 !important;
        box-shadow: 0 0 20px rgba(250, 204, 21, 0.5), 0 0 40px rgba(250, 204, 21, 0.25);
    }

    @keyframes winnerPulse {
        from { transform: scale(1); }
        to { transform: scale(1.06); }
    }

    .confetti-piece {
        position: absolute;
        width: 10px;
        height: 18px;
        opacity: 0.9;
        animation: confettiFall linear forwards;
    }

    @keyframes confettiFall {
        0% {
            transform: translateY(-20px) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(110vh) rotate(720deg);
            opacity: 0;
        }
    }
</style>

<script>
    const baseItems = @json($items);
    const raffleId = @json($raffle->id);
    const csrfToken = @json(csrf_token());
    const existingWinnerNumber = @json($raffle->winner_number);

    const track = document.getElementById('track');
    const spinBtn = document.getElementById('spinBtn');
    const winnerBox = document.getElementById('winnerBox');
    const winnerNumber = document.getElementById('winnerNumber');
    const winnerName = document.getElementById('winnerName');
    const spinSound = document.getElementById('spinSound');
    const winSound = document.getElementById('winSound');
    const confetti = document.getElementById('confetti');

    const countdownOverlay = document.getElementById('countdownOverlay');
    const countdownValue = document.getElementById('countdownValue');
    const spinStatusBox = document.getElementById('spinStatusBox');
    const spinTimer = document.getElementById('spinTimer');

    let isRunning = false;
    let expandedItems = [];
    let countdownInterval = null;
    let spinInterval = null;

    function buildTrack() {
        track.innerHTML = '';
        expandedItems = [];

        for (let r = 0; r < 14; r++) {
            baseItems.forEach(item => {
                expandedItems.push({ ...item });
            });
        }

        expandedItems.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = 'draw-card';
            card.dataset.number = item.number;
            card.dataset.name = item.name;
            card.dataset.index = index;
            card.innerHTML = `
                <div class="draw-card-number">${item.number}</div>
                <div class="draw-card-name">${item.name}</div>
            `;
            track.appendChild(card);
        });
    }

    function launchConfetti() {
        confetti.innerHTML = '';
        const colors = ['#facc15', '#22c55e', '#ef4444', '#3b82f6', '#a855f7', '#ffffff'];

        for (let i = 0; i < 120; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDuration = (2 + Math.random() * 2) + 's';
            piece.style.animationDelay = (Math.random() * 0.4) + 's';
            confetti.appendChild(piece);
        }

        setTimeout(() => {
            confetti.innerHTML = '';
        }, 5000);
    }

    function centerCard(card, animated = true) {
        const container = track.parentElement;
        const containerWidth = container.offsetWidth;
        const left = card.offsetLeft;
        const width = card.offsetWidth;

        const translate = left - (containerWidth / 2) + (width / 2);

        track.style.transition = animated
            ? 'transform 6s cubic-bezier(0.08, 0.75, 0.12, 1)'
            : 'none';

        track.style.transform = `translateX(-${translate}px)`;
    }

    function showWinner(number, name) {
        winnerNumber.textContent = number;
        winnerName.textContent = name;
        winnerBox.classList.remove('hidden');
    }

    function resetUI() {
        isRunning = false;
        clearInterval(countdownInterval);
        clearInterval(spinInterval);

        spinBtn.disabled = false;
        spinBtn.classList.remove('opacity-60', 'cursor-not-allowed');

        try {
            spinSound.pause();
            spinSound.currentTime = 0;
        } catch (e) {}
    }

    function startSequence() {
        if (isRunning || baseItems.length === 0) return;

        isRunning = true;
        winnerBox.classList.add('hidden');

        document.querySelectorAll('.draw-card').forEach(el => {
            el.classList.remove('winner-card');
        });

        spinBtn.disabled = true;
        spinBtn.classList.add('opacity-60', 'cursor-not-allowed');
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

    async function runDraw() {
        spinStatusBox.classList.remove('hidden');
        spinTimer.textContent = '6';

        try {
            spinSound.currentTime = 0;
            spinSound.loop = true;
            await spinSound.play().catch(() => {});
        } catch (e) {}

        let response;

        try {
            response = await fetch(`/admin/sortear/${raffleId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        } catch (e) {
            alert('No se pudo realizar el sorteo.');
            resetUI();
            return;
        }

        if (!response.ok) {
            alert('Ocurrió un error al elegir el ganador.');
            resetUI();
            return;
        }

        const data = await response.json();

        const winningNumberValue = String(data.winner_number).padStart(2, '0');
        const winningNameValue = data.winner_name ?? 'Participante';

        const allCards = [...document.querySelectorAll('.draw-card')];

        let target = allCards.find((card, idx) => {
            return idx > baseItems.length * 7 &&
                card.dataset.number === winningNumberValue &&
                card.dataset.name === winningNameValue;
        });

        if (!target) {
            target = allCards.find((card, idx) => {
                return idx > baseItems.length * 7 &&
                    card.dataset.number === winningNumberValue;
            });
        }

        if (!target) {
            alert('No se encontró el ganador.');
            resetUI();
            return;
        }

        let secondsLeft = 6;
        spinInterval = setInterval(() => {
            secondsLeft--;
            if (secondsLeft >= 0) {
                spinTimer.textContent = secondsLeft;
            }
        }, 1000);

        centerCard(target, true);

        setTimeout(async () => {
            clearInterval(spinInterval);

            try {
                spinSound.pause();
                spinSound.currentTime = 0;
                winSound.currentTime = 0;
                await winSound.play().catch(() => {});
            } catch (e) {}

            spinStatusBox.classList.add('hidden');
            target.classList.add('winner-card');
            showWinner(winningNumberValue, winningNameValue);
            launchConfetti();

            spinBtn.textContent = '⬅️ VOLVER AL PANEL';
            spinBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            spinBtn.disabled = false;
            spinBtn.onclick = () => {
                window.location.href = '/admin';
            };

            isRunning = false;
        }, 6000);
    }

    buildTrack();

    if (existingWinnerNumber) {
        spinStatusBox.classList.add('hidden');
        countdownOverlay.classList.add('hidden');

        const existing = [...document.querySelectorAll('.draw-card')].find((card, idx) => {
            return idx > baseItems.length * 2 &&
                card.dataset.number === String(existingWinnerNumber).padStart(2, '0');
        });

        if (existing) {
            centerCard(existing, false);
            existing.classList.add('winner-card');

            const existingName = existing.dataset.name || 'Participante';
            showWinner(String(existingWinnerNumber).padStart(2, '0'), existingName);

            spinBtn.textContent = '⬅️ VOLVER AL PANEL';
            spinBtn.disabled = false;
            spinBtn.onclick = () => {
                window.location.href = '/admin';
            };
        }
    }
</script>
@endsection