<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Http\Request;

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

    // 💾 GUARDAR SORTEO (ÚNICO)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'total_numbers' => 'required|integer|min:1',
            'image' => 'nullable|image|max:5120'
        ]);

        // 🔥 LIMPIAR PRECIO
        $price = str_replace('.', '', $request->price);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('raffles', 'public');
        }

        $raffle = Raffle::create([
            'name' => $request->name,
            'price' => $price,
            'total_numbers' => $request->total_numbers,
            'image' => $imagePath,
            'status' => 'active'
        ]);

        // 🔢 GENERAR NUMEROS
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

        return back()->with('success', 'Pago confirmado correctamente');
    }
}