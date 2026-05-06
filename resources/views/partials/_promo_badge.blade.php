@if($raffle->promo_enabled && $raffle->promo_prize_text)
<div class="bg-gradient-to-br from-yellow-900/30 to-yellow-800/10 border border-yellow-500/40 rounded-2xl p-5 mb-5">

    <p class="text-yellow-400 font-bold text-sm uppercase tracking-wide mb-1">🎁 Promo especial del sorteo</p>
    <p class="text-gray-300 text-sm mb-2">Además del premio principal, participás por un premio extra:</p>
    <p class="text-white font-bold text-base mb-4">{{ $raffle->promo_prize_text }}</p>

    @if($raffle->promo_type === 'most_numbers')
    <div class="border-t border-yellow-500/20 pt-3 space-y-2">
        <p class="text-yellow-300 font-semibold text-sm">🏆 ¿Cómo se gana?</p>
        <p class="text-gray-300 text-sm leading-relaxed">
            La persona que compre la <strong class="text-white">mayor cantidad de números</strong> en este sorteo se lleva esta promo especial.
        </p>
        <p class="text-yellow-200 text-xs font-medium">
            🔥 Cuantos más números comprás, más chances tenés de ganar el sorteo principal <em>y también</em> la promo extra.
        </p>
    </div>
    @else
    <div class="border-t border-yellow-500/20 pt-3">
        <p class="text-gray-400 text-sm">
            🏆 Los primeros <strong class="text-white">{{ $raffle->promo_limit }}</strong> en reservar y confirmar pago participan &middot; {{ $raffle->promo_winner_count }} ganador(es).
        </p>
    </div>
    @endif

</div>
@endif
