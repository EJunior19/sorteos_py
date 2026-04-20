<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePrize;
use App\Models\RafflePromoResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    // 📊 DASHBOARD
    public function dashboard()
    {
        $raffle = Raffle::with(['numbers', 'prizes', 'promoResults.raffleNumber'])->latest()->first();

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
        $assigned = $raffle->numbers->whereNotNull('customer_name')->where('customer_name', '!=', '')->count();
        $revenue  = $sold * $raffle->price;
        $progress = $total > 0 ? round(($assigned / $total) * 100) : 0;

        return view('admin.dashboard', compact(
            'raffle', 'total', 'free', 'reserved', 'sold', 'assigned', 'revenue', 'progress'
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
            'name'               => 'required|string|max:255',
            'price'              => 'required',
            'total_numbers'      => 'required|integer|min:1',
            'image'              => 'required|image|max:5120',
            'prizes_count'       => 'required|integer|min:1|max:20',
            'prizes'             => 'required|array|min:1',
            'prizes.*.name'      => 'required|string|max:255',
            'prizes.*.description' => 'nullable|string|max:255',
            'titular_name'       => 'nullable|string|max:255',
            'alias'              => 'nullable|string|max:255',
            'promo_enabled'      => 'nullable|boolean',
            'promo_type'         => 'nullable|string|in:early_numbers',
            'promo_limit'        => 'nullable|integer|min:1',
            'promo_winner_count' => 'nullable|integer|min:1',
            'promo_prize_text'   => 'nullable|string|max:255',
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
            $promoEnabled = $request->boolean('promo_enabled');

            $raffle = Raffle::create([
                'name'               => $request->name,
                'price'              => $price,
                'total_numbers'      => $request->total_numbers,
                'image'              => $imagePath,
                'status'             => 'active',
                'prizes_count'       => $request->prizes_count,
                'titular_name'       => $request->titular_name ?? 'Junior Enciso',
                'alias'              => $request->alias ?? '7130138',
                'promo_enabled'      => $promoEnabled,
                'promo_type'         => $promoEnabled ? ($request->promo_type ?? 'early_numbers') : null,
                'promo_limit'        => $promoEnabled ? $request->promo_limit : null,
                'promo_winner_count' => $promoEnabled ? ($request->promo_winner_count ?? 1) : 0,
                'promo_prize_text'   => $promoEnabled ? $request->promo_prize_text : null,
            ]);

            Log::info("🎯 SORTEO CREADO ID: " . $raffle->id);

            // Generar números
            for ($i = 1; $i <= $raffle->total_numbers; $i++) {
                $raffle->numbers()->create([
                    'number' => $i,
                    'status' => 'free',
                ]);
            }

            // Crear premios
            $prizes      = $request->prizes;
            $totalPrizes = count($prizes);

            foreach ($prizes as $index => $prizeData) {
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

        $raffle   = $num->raffle;
        $total    = $raffle->numbers()->count();
        $sold     = $raffle->numbers()->where('status', 'sold')->count();
        $assigned = $raffle->numbers()->whereNotNull('customer_name')->where('customer_name', '!=', '')->count();

        Log::info("📊 PROGRESO: $assigned asignados / $sold pagados / $total total");

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
        $raffle = Raffle::with(['numbers', 'prizes', 'promoResults.raffleNumber'])->findOrFail($id);

        $allPrizesDrawn = $raffle->prizes->isNotEmpty()
            ? $raffle->prizes->every(fn($p) => !is_null($p->winner_number))
            : !is_null($raffle->winner_number);

        return view('admin.roulette', compact('raffle', 'allPrizesDrawn'));
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

    // 📲 GENERAR MENSAJES WHATSAPP (frontend genera localmente, backend devuelve datos)
    public function generarMensajeWhatsapp($id)
    {
        $raffle = Raffle::with([
            'numbers' => function ($query) {
                $query->orderBy('number', 'asc');
            },
            'prizes' => function ($query) {
                $query->reorder('order', 'desc');
            }
        ])->findOrFail($id);

        // Preparar datos para el frontend
        $numbers = $raffle->numbers->map(function ($n) {
            return [
                'number'       => (int)$n->number,
                'customer_name' => $n->customer_name ?? '',
                'status'       => $n->status, // 'free', 'reserved', 'sold'
            ];
        })->toArray();

        $prizes = $raffle->prizes->map(function ($p) {
            return [
                'name'        => $p->name,
                'description' => $p->description ?? '',
            ];
        })->toArray();

        return response()->json([
            'raffle_name'   => $raffle->name,
            'price'         => (int)$raffle->price,
            'titular_name'  => $raffle->titular_name ?? 'Junior Enciso',
            'alias'         => $raffle->alias ?? '7130138',
            'prizes'        => $prizes,
            'numbers'       => $numbers,
        ]);
    }

    // 🎁 PROMO: obtener participantes (primeros promo_limit por reserved_at ASC)
    private function getPromoParticipants(Raffle $raffle)
    {
        return $raffle->numbers()
            ->whereNotNull('reserved_at')
            ->orderBy('reserved_at', 'asc')
            ->take($raffle->promo_limit)
            ->get();
    }

    // 🎁 PROMO: de los participantes, filtrar sold y elegir aleatoriamente
    private function drawPromo(Raffle $raffle)
    {
        $participants = $this->getPromoParticipants($raffle);
        $eligible     = $participants->where('status', 'sold');

        if ($eligible->isEmpty()) {
            return collect();
        }

        $count = min($raffle->promo_winner_count, $eligible->count());

        return $eligible->shuffle()->take($count);
    }

    // 🎁 PROMO: endpoint para ejecutar el sorteo de promo
    public function ejecutarPromo(Request $request, $id)
    {
        $raffle = Raffle::with(['numbers', 'prizes', 'promoResults'])->findOrFail($id);

        if (!$raffle->promo_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'La promo no está habilitada para este sorteo.',
            ], 422);
        }

        // El sorteo principal debe estar completo antes de ejecutar la promo
        if ($raffle->prizes->isNotEmpty()) {
            $allDrawn = $raffle->prizes->every(fn($p) => !is_null($p->winner_number));
            if (!$allDrawn) {
                return response()->json([
                    'success' => false,
                    'message' => 'El sorteo principal no terminó. Completá todos los premios primero.',
                ], 422);
            }
        } else {
            if (!$raffle->winner_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'El sorteo principal no terminó. Ejecutá el sorteo primero.',
                ], 422);
            }
        }

        // Si la promo ya fue ejecutada, devolver los resultados guardados
        if ($raffle->promoResults->isNotEmpty()) {
            $raffle->load('promoResults.raffleNumber');
            $winners = $raffle->promoResults->map(fn($r) => [
                'number'        => $r->raffleNumber->number ?? '?',
                'customer_name' => $r->customer_name,
                'prize_text'    => $r->prize_text,
            ]);

            return response()->json([
                'success'       => true,
                'already_drawn' => true,
                'winners'       => $winners,
            ]);
        }

        $winners = $this->drawPromo($raffle);

        if ($winners->isEmpty()) {
            $limit = $raffle->promo_limit;
            return response()->json([
                'success' => false,
                'message' => "Ninguno de los primeros {$limit} números reservados está confirmado como pagado. No hay participantes elegibles para la promo.",
            ], 422);
        }

        $savedWinners = [];
        foreach ($winners as $winner) {
            RafflePromoResult::create([
                'raffle_id'        => $raffle->id,
                'raffle_number_id' => $winner->id,
                'customer_name'    => $winner->customer_name ?? 'Participante',
                'prize_text'       => $raffle->promo_prize_text ?? 'Premio promo',
            ]);

            $savedWinners[] = [
                'number'        => $winner->number,
                'customer_name' => $winner->customer_name ?? 'Participante',
                'prize_text'    => $raffle->promo_prize_text ?? 'Premio promo',
            ];

            Log::info("🎁 PROMO GANADOR: #{$winner->number} - " . ($winner->customer_name ?? 'Participante'));
        }

        return response()->json([
            'success' => true,
            'winners' => $savedWinners,
        ]);
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