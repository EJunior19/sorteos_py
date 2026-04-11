<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use Illuminate\Http\Request;

class RaffleController extends Controller
{
    // 🎯 LISTA DE SORTEOS
    public function index()
    {
        $raffles = Raffle::whereIn('status', ['active', 'finished'])
            ->with('numbers')
            ->latest()
            ->get();

        // Ganadores: sorteos con sistema legacy (winner_number directo)
        // y sorteos multi-premio (tienen prizes con winner)
        $winners = Raffle::where('status', 'finished')
            ->with(['prizes' => fn($q) => $q->orderByDesc('order')])
            ->latest()
            ->take(5)
            ->get();

        return view('raffle.list', compact('raffles', 'winners'));
    }

    // 🎰 VER SORTEO
    public function show($id)
    {
        $raffle = Raffle::whereIn('status', ['active', 'finished'])
            ->with(['numbers', 'prizes'])
            ->findOrFail($id);

        $total    = $raffle->numbers->count();
        $sold     = $raffle->numbers->where('status', 'sold')->count();
        $reserved = $raffle->numbers->where('status', 'reserved')->count();
        $free     = $raffle->numbers->where('status', 'free')->count();
        $progress = $total > 0 ? round((($sold + $reserved) / $total) * 100) : 0;

        return view('raffle.show', compact(
            'raffle', 'total', 'sold', 'reserved', 'free', 'progress'
        ));
    }

    // 🏆 ÚLTIMOS GANADORES
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
