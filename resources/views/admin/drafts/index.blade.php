@extends('layouts.app')

@section('title', 'Borradores')

@section('content')
@php
    $labels = [
        'top_grande' => 'Sorteo grande recomendado',
        'top_relampago' => 'Sorteo flash recomendado',
        'alternativa' => 'Alternativas recomendadas',
    ];

    $riskClasses = [
        'bajo' => 'bg-green-900/40 text-green-300 border-green-500/30',
        'medio' => 'bg-yellow-900/40 text-yellow-300 border-yellow-500/30',
        'alto' => 'bg-red-900/40 text-red-300 border-red-500/30',
    ];
@endphp

<div class="flex justify-between items-center gap-2 mb-4">
    <div>
        <h1 class="text-yellow-400 font-black text-xl">Borradores</h1>
        <p class="text-gray-500 text-xs mt-0.5">Candidatos generados por el analisis automatico</p>
    </div>
    <a href="/admin" class="bg-[#1A1A1A] border border-white/10 text-yellow-300 px-3 py-2 rounded-lg text-xs font-bold">
        Admin
    </a>
</div>

@if(session('success'))
    <div class="bg-green-900/30 border border-green-500/30 text-green-300 rounded-xl p-3 mb-4 text-sm font-bold">
        {{ session('success') }}
    </div>
@endif

@if($groups->isEmpty())
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-5 text-center">
        <p class="text-yellow-400 font-bold">No hay borradores pendientes</p>
        <p class="text-gray-500 text-xs mt-1">Ejecuta el comando de analisis para generar nuevos candidatos.</p>
    </div>
@else
    <div class="space-y-5">
        @foreach($labels as $groupKey => $groupLabel)
            @php
                $items = $groups->get($groupKey, collect());
            @endphp

            <section>
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-white font-bold text-sm">{{ $groupLabel }}</h2>
                    <span class="text-gray-500 text-xs">{{ $items->count() }} pendientes</span>
                </div>

                @if($items->isEmpty())
                    <div class="bg-[#141414] border border-white/10 rounded-xl p-3 text-gray-500 text-xs">
                        Sin candidatos en este grupo.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($items as $candidate)
                            @php
                                $typeLabel = $candidate->raffle_type === 'relampago' ? 'flash' : 'grande';
                                $prizes = $candidate->metrics['prizes'] ?? [];
                            @endphp
                            <article class="bg-[#1A1A1A] border border-white/10 rounded-xl p-4 shadow-lg">
                                <div class="flex justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-yellow-400 font-bold text-base leading-tight">{{ $candidate->product_name }}</h3>
                                        @if(!empty($candidate->metrics['product_code']))
                                            <p class="text-gray-500 text-[11px] mt-0.5">Cod. {{ $candidate->metrics['product_code'] }}</p>
                                        @endif
                                        <p class="text-gray-400 text-xs mt-1">{{ $candidate->category }} · {{ $typeLabel }}</p>
                                        @if($candidate->raffle_type === 'grande' && count($prizes) > 0)
                                            <p class="text-gray-500 text-[11px] mt-1">{{ count($prizes) }} premios incluidos</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 border rounded-full px-2 py-1 text-[10px] font-bold {{ $riskClasses[$candidate->risk_level] ?? 'bg-gray-800 text-gray-300 border-gray-600' }}">
                                        {{ strtoupper($candidate->risk_level) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
                                    <div class="bg-black/30 rounded-lg p-2">
                                        <p class="text-gray-500">Costo total</p>
                                        <p class="text-white font-bold">Gs. {{ number_format($candidate->cost_gs, 0, ',', '.') }}</p>
                                    </div>
                                    <div class="bg-black/30 rounded-lg p-2">
                                        <p class="text-gray-500">Numeros</p>
                                        <p class="text-white font-bold">{{ $candidate->numbers_count }}</p>
                                    </div>
                                    <div class="bg-black/30 rounded-lg p-2">
                                        <p class="text-gray-500">Precio/numero</p>
                                        <p class="text-white font-bold">Gs. {{ number_format($candidate->price_per_number_gs, 0, ',', '.') }}</p>
                                    </div>
                                    <div class="bg-black/30 rounded-lg p-2">
                                        <p class="text-gray-500">Ganancia</p>
                                        <p class="text-green-400 font-bold">Gs. {{ number_format($candidate->estimated_profit_gs, 0, ',', '.') }}</p>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center mt-3">
                                    <span class="text-yellow-300 text-xs font-bold">Score {{ number_format((float) $candidate->score, 2, ',', '.') }}</span>
                                    <span class="text-gray-500 text-[10px]">Batch {{ Str::limit($candidate->batch_uuid, 8, '') }}</span>
                                </div>

                                <p class="text-gray-300 text-xs mt-3 leading-relaxed">{{ $candidate->reason }}</p>

                                <div class="flex gap-2 mt-4">
                                    <a href="{{ route('admin.drafts.show', $candidate->id) }}"
                                        class="flex-1 bg-yellow-400 hover:bg-yellow-300 text-black text-center text-xs font-black py-2 rounded-lg transition">
                                        Ver detalle
                                    </a>
                                    <form method="POST" action="{{ route('admin.drafts.reject', $candidate->id) }}" class="flex-1" onsubmit="return confirm('¿Rechazar este borrador?')">
                                        @csrf
                                        <button type="submit" class="w-full bg-red-600 hover:bg-red-500 text-white text-xs font-black py-2 rounded-lg transition">
                                            Rechazar
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endif
@endsection
