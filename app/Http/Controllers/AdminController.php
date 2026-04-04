<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    // 📊 DASHBOARD
    public function dashboard()
    {
        $raffle = Raffle::with('numbers')->latest()->first();

        if (!$raffle) {
            return view('admin.dashboard', [
                'raffle' => null,
                'total' => 0,
                'free' => 0,
                'reserved' => 0,
                'sold' => 0,
                'revenue' => 0,
                'progress' => 0,
            ]);
        }

        $total = $raffle->numbers->count();
        $free = $raffle->numbers->where('status', 'free')->count();
        $reserved = $raffle->numbers->where('status', 'reserved')->count();
        $sold = $raffle->numbers->where('status', 'sold')->count();
        $revenue = $sold * $raffle->price;
        $progress = $total > 0 ? round((($sold + $reserved) / $total) * 100) : 0;

        return view('admin.dashboard', compact(
            'raffle',
            'total',
            'free',
            'reserved',
            'sold',
            'revenue',
            'progress'
        ));
    }

    // ➕ FORM CREAR
    public function create()
    {
        return view('admin.create');
    }

    // 💾 GUARDAR SORTEO
    public function store(Request $request)
    {
        Log::info("🚀 INICIO STORE");

        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'total_numbers' => 'required|integer|min:1',
            'image' => 'required|image|max:5120'
        ]);

        $price = str_replace('.', '', $request->price);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('raffles', $filename, 'public');
            Log::info("📸 IMAGEN GUARDADA: " . $path);
            $imagePath = $path;
        }

        $raffle = Raffle::create([
            'name' => $request->name,
            'price' => $price,
            'total_numbers' => $request->total_numbers,
            'image' => $imagePath,
            'status' => 'active'
        ]);

        Log::info("🎯 SORTEO CREADO ID: " . $raffle->id);

        for ($i = 1; $i <= $raffle->total_numbers; $i++) {
            $raffle->numbers()->create([
                'number' => str_pad($i, 2, '0', STR_PAD_LEFT),
                'status' => 'free'
            ]);
        }

        return redirect('/admin')->with('success', 'Sorteo creado correctamente');
    }

    // 💰 CONFIRMAR PAGO
    public function confirmarPago($id)
    {
        $num = RaffleNumber::findOrFail($id);

        if ($num->status !== 'reserved') {
            return back()->with('error', 'Solo se pueden confirmar números reservados');
        }

        $num->update([
            'status' => 'sold',
            'paid' => true,
        ]);

        $raffle = $num->raffle;

        $total = $raffle->numbers()->count();
        $sold = $raffle->numbers()->where('status', 'sold')->count();

        Log::info("📊 PROGRESO: $sold / $total");

        // 🎯 SI SE VENDIÓ TODO → IR A RULETA
        if ($total === $sold) {
            Log::info("🎰 TODO VENDIDO → REDIRECT RULETA");

            return redirect()->route('admin.roulette', $raffle->id)
                ->with('success', '¡Todo vendido! Iniciando sorteo...');
        }

        return back()->with('success', 'Pago confirmado');
    }

    // 🎰 VISTA RULETA
    public function vistaSorteo($id)
    {
        $raffle = Raffle::with('numbers')->findOrFail($id);

        return view('admin.roulette', compact('raffle'));
    }

    // 🎯 SORTEAR GANADOR
   public function sortear($id)
{
    $raffle = Raffle::with('numbers')->findOrFail($id);

    $total = $raffle->numbers->count();
    $soldNumbers = $raffle->numbers->where('status', 'sold');
    $sold = $soldNumbers->count();

    if ($total !== $sold) {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Aún no se vendieron todos'
            ], 422);
        }

        return back()->with('error', 'Aún no se vendieron todos');
    }

    // Si ya hay ganador, reutilizarlo
    if ($raffle->winner_number) {
        $winner = $soldNumbers->firstWhere('number', $raffle->winner_number);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'winner_number' => $raffle->winner_number,
                'winner_name' => $winner->buyer_name ?? $winner->name ?? $winner->customer_name ?? 'Participante',
            ]);
        }

        return redirect()->route('admin.roulette', $raffle->id)
            ->with('success', 'Ganador: ' . $raffle->winner_number);
    }

    // Elegir ganador
    $winner = $soldNumbers->random();

    $raffle->update([
        'winner_number' => $winner->number,
        'status' => 'finished'
    ]);

    Log::info("🏆 GANADOR: " . $winner->number);

    if (request()->expectsJson()) {
        return response()->json([
            'success' => true,
            'winner_number' => $winner->number,
            'winner_name' => $winner->buyer_name ?? $winner->name ?? $winner->customer_name ?? 'Participante',
        ]);
    }

    return redirect()->route('admin.roulette', $raffle->id)
        ->with('success', 'Ganador: ' . $winner->number);
}
}