<?php

namespace App\Http\Controllers;

use App\Models\RaffleNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function reservar(Request $request)
    {
        $request->validate([
            'raffle_id' => 'required|exists:raffles,id',
            'numbers'   => 'required|array|min:1',
            'numbers.*' => 'integer|min:1',
            'name'      => 'required|string|max:255'
        ]);

        try {

            DB::beginTransaction();

            $numbers = array_unique($request->numbers);

            $reservados = [];

            foreach ($numbers as $num) {

                $n = RaffleNumber::where('raffle_id', $request->raffle_id)
                    ->where('number', (int)$num)
                    ->lockForUpdate()
                    ->first();

                if (!$n) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "El número {$num} no existe"
                    ], 400);
                }

                if ($n->status !== 'free') {
                    DB::rollBack();
                    return response()->json([
                        'error' => "El número {$num} ya fue reservado o vendido"
                    ], 400);
                }

                $n->update([
                    'status'        => 'reserved',
                    'customer_name' => $request->name,
                    'reserved_at'   => now(),
                    'expires_at'    => null,
                    'paid'          => false
                ]);

                $reservados[] = $num;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'numbers' => $reservados,
                'name'    => $request->name
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Error al reservar. Intente nuevamente.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
