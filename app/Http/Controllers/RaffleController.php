<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use Illuminate\Http\Request;

class RaffleController extends Controller
{
    // 🎯 LISTA
    public function index()
    {
        $raffles = Raffle::whereIn('status', ['active', 'finished'])
            ->with(['numbers' => function ($q) {
                $q->select('id', 'raffle_id', 'number', 'status');
            }])
            ->latest()
            ->get();

        $winners = Raffle::where('status', 'finished')
            ->with(['prizes' => fn($q) => $q->orderByDesc('order')])
            ->latest()
            ->take(5)
            ->get();

        return view('raffle.list', compact('raffles', 'winners'));
    }

    // 🟢 PARTICIPAR (PLAY)
    public function play($id)
    {
        $raffle = Raffle::where('status', 'active')
            ->with('numbers')
            ->findOrFail($id);

        $total    = $raffle->numbers->count();
        $sold     = $raffle->numbers->where('status', 'sold')->count();
        $reserved = $raffle->numbers->where('status', 'reserved')->count();
        $free     = $raffle->numbers->where('status', 'free')->count();
        $progress = $total > 0 ? round((($sold + $reserved) / $total) * 100) : 0;

        return view('raffle.play', compact(
            'raffle', 'total', 'sold', 'reserved', 'free', 'progress'
        ));
    }

    // 🔴 RESULTADOS
    public function show($id)
    {
        $raffle = Raffle::where('status', 'finished')
            ->with(['numbers', 'prizes'])
            ->findOrFail($id);

        return view('raffle.show', compact('raffle'));
    }

    // 🔢 ELEGIR NÚMEROS
    public function numbers($id)
    {
        $raffle = Raffle::where('status', 'active')
            ->with('numbers')
            ->findOrFail($id);

        return view('raffle.numbers', compact('raffle'));
    }

    // 🏆 GANADORES
    public function winners()
    {
        $winners = Raffle::where('status', 'finished')
            ->with(['prizes' => fn($q) => $q->orderByDesc('order')])
            ->latest()
            ->take(10)
            ->get();

        return view('winners.index', compact('winners'));
    }
}