<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public const MAX_NUMEROS_POR_PEDIDO = 10;

    public function reservar(Request $request)
    {
        $request->validate([
            'raffle_id' => 'required|exists:raffles,id',
            'numbers'   => 'required|array|min:1|max:' . self::MAX_NUMEROS_POR_PEDIDO,
            'numbers.*' => 'integer|min:1',
            'name'      => 'required|string|max:255'
        ], [
            'numbers.max' => 'Podés reservar hasta ' . self::MAX_NUMEROS_POR_PEDIDO . ' números por vez.',
        ]);

        try {

            DB::beginTransaction();

            $raffle = Raffle::findOrFail($request->raffle_id);

            if ($raffle->status !== 'active') {
                DB::rollBack();
                return response()->json([
                    'error' => 'Este sorteo no está disponible para reservas'
                ], 400);
            }

            $isFreeRaffle = (int) $raffle->price === 0;
            $numbers = array_values(array_unique(array_map('intval', $request->numbers)));

            // 🔒 Bloquear todos los números en UNA consulta, siempre en orden de id,
            // para que dos reservas simultáneas no se bloqueen entre sí (deadlock).
            // La columna "number" es varchar: se compara como texto.
            $rows = RaffleNumber::where('raffle_id', $raffle->id)
                ->whereIn('number', array_map('strval', $numbers))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($n) => (int) $n->number);

            foreach ($numbers as $num) {

                if (!isset($rows[$num])) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "El número {$num} no existe"
                    ], 400);
                }

                if ($rows[$num]->status !== 'free') {
                    DB::rollBack();
                    return response()->json([
                        'error' => "El número {$num} ya fue reservado o vendido"
                    ], 400);
                }
            }

            RaffleNumber::whereIn('id', $rows->pluck('id'))->update([
                'status'        => $isFreeRaffle ? 'sold' : 'reserved',
                'customer_name' => $request->name,
                'reserved_at'   => now(),
                'expires_at'    => $isFreeRaffle ? null : now()->addMinutes(config('sorteos.reserva_minutos')),
                'paid'          => $isFreeRaffle
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'numbers' => $numbers,
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
