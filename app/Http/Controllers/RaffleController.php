<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use Illuminate\Http\Request;

class RaffleController extends Controller
{
    // 🎯 LISTA DE SORTEOS
    public function index()
    {
        $raffles = Raffle::where('status', 'active')->latest()->get();
        return view('raffle.list', compact('raffles'));
    }

    // 🎰 VER SORTEO
    public function show($id)
    {
        $raffle = Raffle::where('status', 'active')
            ->with('numbers')
            ->findOrFail($id);

        $total = $raffle->numbers->count();
        $sold = $raffle->numbers->where('status', 'sold')->count();
        $reserved = $raffle->numbers->where('status', 'reserved')->count();
        $free = $raffle->numbers->where('status', 'free')->count();

        $progress = $total > 0 ? round((($sold + $reserved) / $total) * 100) : 0;

        return view('raffle.play', compact(
            'raffle',
            'total',
            'sold',
            'reserved',
            'free',
            'progress'
        ));
    }
}