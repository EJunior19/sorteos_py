<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePrize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    // 📊 DASHBOARD
    public function dashboard()
    {
        $raffle = Raffle::with(['numbers', 'prizes'])->latest()->first();

        if (!$raffle) {
            return view('admin.dashboard', [
                'raffle'   => null,
                'total'    => 0,
                'free'     => 0,
                'reserved' => 0,
                'sold'     => 0,
                'revenue'  => 0,
                'progress' => 0,
            ]);
        }

        $total    = $raffle->numbers->count();
        $free     = $raffle->numbers->where('status', 'free')->count();
        $reserved = $raffle->numbers->where('status', 'reserved')->count();
        $sold     = $raffle->numbers->where('status', 'sold')->count();
        $revenue  = $sold * $raffle->price;
        $progress = $total > 0 ? round((($sold + $reserved) / $total) * 100) : 0;

        return view('admin.dashboard', compact(
            'raffle', 'total', 'free', 'reserved', 'sold', 'revenue', 'progress'
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
            'name'              => 'required|string|max:255',
            'price'             => 'required',
            'total_numbers'     => 'required|integer|min:1',
            'image'             => 'required|image|max:5120',
            'prizes_count'      => 'required|integer|min:1|max:20',
            'prizes'            => 'required|array|min:1',
            'prizes.*.name'     => 'required|string|max:255',
            'prizes.*.description' => 'nullable|string|max:255',
        ]);

        $price = str_replace('.', '', $request->price);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file      = $request->file('image');
            $filename  = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $imagePath = $file->storeAs('raffles', $filename, 'public');
            Log::info("📸 IMAGEN GUARDADA: " . $imagePath);
        }

        DB::transaction(function () use ($request, $price, $imagePath) {
            $raffle = Raffle::create([
                'name'          => $request->name,
                'price'         => $price,
                'total_numbers' => $request->total_numbers,
                'image'         => $imagePath,
                'status'        => 'active',
                'prizes_count'  => $request->prizes_count,
            ]);

            Log::info("🎯 SORTEO CREADO ID: " . $raffle->id);

            // Generar números
            for ($i = 1; $i <= $raffle->total_numbers; $i++) {
                $raffle->numbers()->create([
                    'number' => str_pad($i, 2, '0', STR_PAD_LEFT),
                    'status' => 'free',
                ]);
            }

            // Crear premios
            // El array viene del formulario en orden 1er→último pero
            // los guardamos con order = posición real (1=último, N=1er premio)
            $prizes      = $request->prizes;
            $totalPrizes = count($prizes);

            foreach ($prizes as $index => $prizeData) {
                // index 0 = 1er premio (mayor) → order = totalPrizes
                // index N-1 = último premio (menor) → order = 1
                $order = $totalPrizes - $index;

                RafflePrize::create([
                    'raffle_id'   => $raffle->id,
                    'order'       => $order,
                    'name'        => $prizeData['name'],
                    'description' => $prizeData['description'] ?? null,
                ]);
            }

            Log::info("🏆 " . $totalPrizes . " premios creados para sorteo ID: " . $raffle->id);
        });

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
            'status'     => 'sold',
            'paid'       => true,
            'expires_at' => null,
        ]);

        $raffle = $num->raffle;
        $total  = $raffle->numbers()->count();
        $sold   = $raffle->numbers()->where('status', 'sold')->count();

        Log::info("📊 PROGRESO: $sold / $total");

        if ($total === $sold) {
            Log::info("🎰 TODO VENDIDO → REDIRECT RULETA");
            return redirect()->route('admin.roulette', $raffle->id)
                ->with('success', '¡Todo vendido! Iniciando sorteo...');
        }

        return back()->with('success', 'Pago confirmado');
    }

    // 🎰 VISTA SORTEO
    public function vistaSorteo($id)
    {
        $raffle = Raffle::with(['numbers', 'prizes'])->findOrFail($id);

        return view('admin.roulette', compact('raffle'));
    }

    // 🎯 SORTEAR — soporta legacy (1 premio) y múltiples premios
    public function sortear(Request $request, $id)
    {
        $raffle      = Raffle::with(['numbers', 'prizes'])->findOrFail($id);
        $soldNumbers = $raffle->numbers->where('status', 'sold');
        $total       = $raffle->numbers->count();
        $sold        = $soldNumbers->count();

        if ($total !== $sold) {
            return response()->json([
                'success' => false,
                'message' => 'Aún no se vendieron todos los números',
            ], 422);
        }

        // ── SISTEMA MÚLTIPLES PREMIOS ──────────────────────────────────────
        if ($raffle->prizes->isNotEmpty()) {
            return $this->sortearPremio($request, $raffle, $soldNumbers);
        }

        // ── SISTEMA LEGACY (1 solo ganador) ───────────────────────────────
        if ($raffle->winner_number) {
            $winner = $soldNumbers->firstWhere('number', $raffle->winner_number);
            return response()->json([
                'success'      => true,
                'winner_number' => $raffle->winner_number,
                'winner_name'  => $raffle->winner_name ?? ($winner->customer_name ?? 'Participante'),
            ]);
        }

        $winner = $soldNumbers->random();

        $raffle->update([
            'winner_number' => $winner->number,
            'winner_name'   => $winner->customer_name ?? 'Participante',
            'status'        => 'finished',
        ]);

        Log::info("🏆 GANADOR LEGACY: " . $winner->number . ' - ' . ($winner->customer_name ?? 'Participante'));

        return response()->json([
            'success'      => true,
            'winner_number' => $winner->number,
            'winner_name'  => $winner->customer_name ?? 'Participante',
        ]);
    }

    // 📲 GENERAR MENSAJE WHATSAPP
    public function generarMensajeWhatsapp($id)
    {
        $raffle = Raffle::with(['numbers', 'prizes' => function ($q) {
            $q->orderBy('order', 'desc');
        }])->findOrFail($id);

        $mensaje  = "🎰✨ *¡SORTEO EN CURSO!* ✨🎰\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "🎟️ *{$raffle->name}*\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

        $mensaje .= "🏆 *PREMIOS INCREÍBLES:*\n";

        $emojis = ['🥇','🥈','🥉','🎁','🎀','🌟','💫','✨','🎯','🎪',
                   '🎨','🎭','🎬','🎤','🎧','🎸','🎺','🎻','🥁','🎹'];

        $prizes = $raffle->prizes->sortByDesc('order')->values();

        foreach ($prizes as $index => $prize) {
            $emoji = $emojis[$index] ?? '🎁';
            $orden = ($index + 1) . '°';
            $mensaje .= "{$emoji} *{$orden} Premio:* {$prize->description}\n";
        }

        $precio = number_format($raffle->price, 0, ',', '.');

        $mensaje .= "\n━━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "💰 *Precio por número:* Gs. {$precio}\n";
        $mensaje .= "💳 *Titular:* Junior Enciso\n";
        $mensaje .= "🔑 *Alias:* 7130138\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "🎫 *LISTA DE NÚMEROS:*\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━\n";

        $numbers = $raffle->numbers->sortBy(fn($n) => (int) $n->number);

        foreach ($numbers as $number) {
            if ($number->paid) {
                $mensaje .= "{$number->number} - {$number->customer_name} 💵\n";
            } elseif ($number->status === 'reserved' && $number->customer_name) {
                $mensaje .= "{$number->number} - {$number->customer_name}\n";
            } else {
                $mensaje .= "{$number->number}\n";
            }
        }

        $mensaje .= "\n━━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "💵 Pagado confirmado\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
        $mensaje .= "🚀 *¿QUERÉS PARTICIPAR?*\n";
        $mensaje .= "👉 Elegí tu número favorito\n";
        $mensaje .= "💸 Realizá tu transferencia\n";
        $mensaje .= "📩 Envianos tu comprobante\n";
        $mensaje .= "✅ ¡Y listo, ya estás participando!\n\n";
        $mensaje .= "🍀 *¡Buena suerte a todos!* 🍀\n";

        return response()->json(['mensaje' => $mensaje]);
    }

    // 🎯 LÓGICA INTERNA — sortear un premio específico
    private function sortearPremio(Request $request, Raffle $raffle, $soldNumbers)
    {
        $prizeOrder = (int) $request->input('prize_order', 1);

        $prize = $raffle->prizes->firstWhere('order', $prizeOrder);

        if (!$prize) {
            return response()->json([
                'success' => false,
                'message' => 'Premio no encontrado',
            ], 404);
        }

        // Si este premio ya fue sorteado, devolver el resultado guardado
        if ($prize->winner_number) {
            return response()->json([
                'success'      => true,
                'already_drawn' => true,
                'prize_order'  => $prize->order,
                'prize_name'   => $prize->name,
                'prize_description' => $prize->description,
                'winner_number' => $prize->winner_number,
                'winner_name'  => $prize->winner_name,
            ]);
        }

        // Excluir participantes que ya ganaron otro premio en este sorteo
        $alreadyWonNames = $raffle->prizes
            ->whereNotNull('winner_name')
            ->pluck('winner_name')
            ->map(fn($n) => strtolower(trim($n)))
            ->toArray();

        $eligible = $soldNumbers->filter(function ($num) use ($alreadyWonNames) {
            $name = strtolower(trim($num->customer_name ?? ''));
            return !in_array($name, $alreadyWonNames);
        });

        if ($eligible->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay participantes elegibles para este premio',
            ], 422);
        }

        $winner = $eligible->random();

        $prize->update([
            'winner_number' => $winner->number,
            'winner_name'   => $winner->customer_name ?? 'Participante',
        ]);

        Log::info("🏆 PREMIO #{$prize->order} ({$prize->name}): {$winner->number} - " . ($winner->customer_name ?? 'Participante'));

        // Si todos los premios ya tienen ganador → marcar sorteo como terminado
        $raffle->load('prizes');
        $allDrawn = $raffle->prizes->every(fn($p) => !is_null($p->winner_number));

        if ($allDrawn) {
            // Guardamos en winner_number/winner_name el 1er premio (order máximo) para compatibilidad
            $mainPrize = $raffle->prizes->sortByDesc('order')->first();
            $raffle->update([
                'winner_number' => $mainPrize->winner_number,
                'winner_name'   => $mainPrize->winner_name,
                'status'        => 'finished',
            ]);
            Log::info("✅ TODOS LOS PREMIOS SORTEADOS — Sorteo finalizado");
        }

        return response()->json([
            'success'          => true,
            'prize_order'      => $prize->order,
            'prize_name'       => $prize->name,
            'prize_description' => $prize->description,
            'winner_number'    => $winner->number,
            'winner_name'      => $winner->customer_name ?? 'Participante',
            'all_drawn'        => $allDrawn,
            'prizes_total'     => $raffle->prizes->count(),
        ]);
    }
}
