<?php

namespace App\Http\Controllers;

use App\Models\RaffleNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    // 🎯 RESERVAR NÚMEROS
    public function reservar(Request $request)
    {
        $request->validate([
            'raffle_id' => 'required|exists:raffles,id',
            'numbers' => 'required|array|min:1',
            'name' => 'required|string|max:255'
        ]);

        try {

            DB::beginTransaction();

            foreach ($request->numbers as $num) {

                // 🔒 BLOQUEO PARA EVITAR DOBLE RESERVA
                $n = RaffleNumber::where('raffle_id', $request->raffle_id)
                    ->where('number', $num)
                    ->lockForUpdate()
                    ->first();

                if (!$n) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "Número $num no existe"
                    ], 400);
                }

                // 🚫 SI YA NO ESTÁ LIBRE
                if ($n->status !== 'free') {
                    DB::rollBack();
                    return response()->json([
                        'error' => "El número $num ya no está disponible"
                    ], 400);
                }

                // ⏱ RESERVAR
                $n->update([
                    'status' => 'reserved',
                    'customer_name' => $request->name,
                    'reserved_at' => now(),
                    'expires_at' => now()->addMinutes(15),
                    'paid' => false
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Error al reservar, intentá nuevamente'
            ], 500);
        }
    }
}