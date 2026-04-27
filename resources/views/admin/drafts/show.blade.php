@extends('layouts.app')

@section('title', 'Detalle borrador')

@section('content')
@php
    $riskClasses = [
        'bajo' => 'bg-green-900/40 text-green-300 border-green-500/30',
        'medio' => 'bg-yellow-900/40 text-yellow-300 border-yellow-500/30',
        'alto' => 'bg-red-900/40 text-red-300 border-red-500/30',
    ];

    $isPending = $candidate->filter_status === 'selected' && is_null($candidate->approved_at);
    $typeLabel = $candidate->raffle_type === 'relampago' ? 'flash' : 'grande';
    $prizes = $candidate->metrics['prizes'] ?? [];
@endphp

<div class="flex justify-between items-center gap-2 mb-4">
    <a href="{{ route('admin.drafts.index') }}" class="bg-[#1A1A1A] border border-white/10 text-yellow-300 px-3 py-2 rounded-lg text-xs font-bold">
        ← Volver
    </a>
    <span class="border rounded-full px-3 py-1 text-xs font-bold {{ $riskClasses[$candidate->risk_level] ?? 'bg-gray-800 text-gray-300 border-gray-600' }}">
        Riesgo {{ strtoupper($candidate->risk_level) }}
    </span>
</div>

@if(session('success'))
    <div class="bg-green-900/30 border border-green-500/30 text-green-300 rounded-xl p-3 mb-4 text-sm font-bold">
        {{ session('success') }}
    </div>
@endif

<div class="bg-[#1A1A1A] border border-yellow-500/20 rounded-xl p-4 mb-4 shadow-lg">
    <p class="text-gray-500 text-xs font-bold uppercase">{{ $candidate->selection_group }}</p>
    <h1 class="text-yellow-400 font-black text-xl mt-1">{{ $candidate->product_name }}</h1>
    @if(!empty($candidate->metrics['product_code']))
        <p class="text-gray-500 text-xs mt-1">Cod. {{ $candidate->metrics['product_code'] }}</p>
    @endif
    <p class="text-gray-400 text-sm mt-1">{{ $candidate->category }} · {{ $typeLabel }}</p>

    @if(!$isPending)
        <div class="mt-3 bg-gray-900 border border-white/10 rounded-lg p-3 text-xs text-gray-300">
            Estado: {{ $candidate->approved_at ? 'aprobado' : $candidate->filter_status }}
            @if($candidate->created_raffle_id)
                · Sorteo #{{ $candidate->created_raffle_id }}
            @endif
        </div>
    @endif
</div>

<div class="grid grid-cols-2 gap-2 mb-4 text-xs">
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-3">
        <p class="text-gray-500">{{ $candidate->raffle_type === 'grande' ? 'Costo total' : 'Costo premio' }}</p>
        <p class="text-white font-black text-base">Gs. {{ number_format($candidate->cost_gs, 0, ',', '.') }}</p>
    </div>
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-3">
        <p class="text-gray-500">Cantidad de numeros</p>
        <p class="text-white font-black text-base">{{ $candidate->numbers_count }}</p>
    </div>
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-3">
        <p class="text-gray-500">Precio por numero</p>
        <p class="text-white font-black text-base">Gs. {{ number_format($candidate->price_per_number_gs, 0, ',', '.') }}</p>
    </div>
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-3">
        <p class="text-gray-500">Recaudacion total</p>
        <p class="text-white font-black text-base">Gs. {{ number_format($candidate->revenue_gs, 0, ',', '.') }}</p>
    </div>
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-3">
        <p class="text-gray-500">Ganancia estimada</p>
        <p class="text-green-400 font-black text-base">Gs. {{ number_format($candidate->estimated_profit_gs, 0, ',', '.') }}</p>
    </div>
    <div class="bg-[#1A1A1A] border border-white/10 rounded-xl p-3">
        <p class="text-gray-500">Score</p>
        <p class="text-yellow-300 font-black text-base">{{ number_format((float) $candidate->score, 2, ',', '.') }}</p>
    </div>
</div>

<div class="space-y-3 mb-4">
    @if(!empty($prizes))
        <section class="bg-[#1A1A1A] border border-white/10 rounded-xl p-4">
            <h2 class="text-white font-bold text-sm mb-3">{{ $candidate->raffle_type === 'grande' ? 'Lista de premios' : 'Premio flash' }}</h2>
            <div class="space-y-2">
                @foreach($prizes as $index => $prize)
                    <div class="bg-black/30 border border-white/10 rounded-lg p-3">
                        <div class="flex justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-yellow-300 text-xs font-bold">{{ $index + 1 }}. {{ $prize['name'] ?? 'Premio' }}</p>
                                <p class="text-gray-500 text-[11px] mt-1">{{ $prize['category'] ?? 'Sin rubro' }} · {{ $prize['role'] ?? 'premio' }}</p>
                            </div>
                            <p class="text-white text-xs font-black shrink-0">Gs. {{ number_format((int) ($prize['cost_gs'] ?? 0), 0, ',', '.') }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2 text-[11px]">
                            <div>
                                <span class="text-gray-500">Codigo</span>
                                <span class="text-gray-300 ml-1">{{ $prize['product_code'] ?? 'No registrado' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Stock</span>
                                <span class="text-gray-300 ml-1">{{ array_key_exists('stock', $prize) && !is_null($prize['stock']) ? $prize['stock'] : 'No registrado' }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="bg-[#1A1A1A] border border-white/10 rounded-xl p-4">
        <h2 class="text-white font-bold text-sm mb-2">Motivo de eleccion</h2>
        <p class="text-gray-300 text-sm leading-relaxed">{{ $candidate->reason }}</p>
    </section>

    <section class="bg-[#1A1A1A] border border-white/10 rounded-xl p-4">
        <h2 class="text-white font-bold text-sm mb-2">Comparacion con sorteos anteriores</h2>
        <p class="text-gray-300 text-sm leading-relaxed">{{ $candidate->historical_comparison ?: 'Sin comparacion historica registrada.' }}</p>
    </section>

    <section class="bg-[#1A1A1A] border border-white/10 rounded-xl p-4">
        <h2 class="text-white font-bold text-sm mb-2">Datos de aprobacion</h2>
        <div class="space-y-1.5 text-xs">
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">ID</span>
                <span class="text-gray-300">{{ $candidate->id }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Codigo producto</span>
                <span class="text-gray-300">{{ $candidate->metrics['product_code'] ?? 'No registrado' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Batch</span>
                <span class="text-gray-300 text-right">{{ $candidate->batch_uuid }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Archivo fuente</span>
                <span class="text-gray-300 text-right">{{ $candidate->source_file ?: 'No registrado' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Stock</span>
                <span class="text-gray-300">{{ is_null($candidate->stock) ? 'No registrado' : $candidate->stock }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Estado filtro</span>
                <span class="text-gray-300">{{ $candidate->filter_status }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Aprobado</span>
                <span class="text-gray-300">{{ $candidate->approved_at ? $candidate->approved_at->format('d/m/Y H:i') : 'No' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Sorteo creado</span>
                <span class="text-gray-300">{{ $candidate->created_raffle_id ?: 'No' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Creado</span>
                <span class="text-gray-300">{{ $candidate->created_at ? $candidate->created_at->format('d/m/Y H:i') : 'No registrado' }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Actualizado</span>
                <span class="text-gray-300">{{ $candidate->updated_at ? $candidate->updated_at->format('d/m/Y H:i') : 'No registrado' }}</span>
            </div>
        </div>
    </section>

    @if(!empty($candidate->filter_reasons) || !empty($candidate->metrics))
        <section class="bg-[#1A1A1A] border border-white/10 rounded-xl p-4">
            <h2 class="text-white font-bold text-sm mb-2">Metricas internas</h2>
            @if(!empty($candidate->filter_reasons))
                <p class="text-gray-500 text-xs font-bold mb-1">Razones de filtro</p>
                <pre class="bg-black/40 border border-white/10 rounded-lg p-3 text-[11px] text-gray-300 overflow-x-auto mb-3">{{ json_encode($candidate->filter_reasons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
            @if(!empty($candidate->metrics))
                <p class="text-gray-500 text-xs font-bold mb-1">Score y senales</p>
                <pre class="bg-black/40 border border-white/10 rounded-lg p-3 text-[11px] text-gray-300 overflow-x-auto">{{ json_encode($candidate->metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </section>
    @endif
</div>

@if($isPending)
    <div class="grid grid-cols-1 gap-3">
        <form method="POST" action="{{ route('admin.drafts.approve', $candidate->id) }}"
            onsubmit="return confirm('¿Aprobar este borrador y crear el sorteo real? Esta accion generara el premio y todos los numeros.')">
            @csrf
            <button type="submit" class="w-full bg-green-500 hover:bg-green-400 text-white text-base font-black py-4 rounded-xl transition">
                ✅ APROBAR
            </button>
        </form>

        <form method="POST" action="{{ route('admin.drafts.reject', $candidate->id) }}" onsubmit="return confirm('¿Rechazar este borrador?')">
            @csrf
            <button type="submit" class="w-full bg-red-600 hover:bg-red-500 text-white text-base font-black py-4 rounded-xl transition">
                ❌ RECHAZAR
            </button>
        </form>
    </div>
@else
    <a href="{{ route('admin.drafts.index') }}" class="block w-full bg-yellow-400 text-black text-center text-sm font-black py-3 rounded-xl">
        Ver borradores pendientes
    </a>
@endif
@endsection
