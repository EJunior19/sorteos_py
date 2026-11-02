<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePrize;
use App\Models\RafflePromoResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $stats = Cache::remember("dashboard_stats_{$raffle->id}", 30, function () use ($raffle) {
            $total    = $raffle->numbers->count();
            $free     = $raffle->numbers->where('status', 'free')->count();
            $reserved = $raffle->numbers->where('status', 'reserved')->count();
            $sold     = $raffle->numbers->where('status', 'sold')->count();
            $assigned = $raffle->numbers->whereNotNull('customer_name')->where('customer_name', '!=', '')->count();
            return compact('total', 'free', 'reserved', 'sold', 'assigned');
        });

        $total    = $stats['total'];
        $free     = $stats['free'];
        $reserved = $stats['reserved'];
        $sold     = $stats['sold'];
        $assigned = $stats['assigned'];
        $revenue  = $sold * $raffle->price;
        $progress = $total > 0 ? round(($assigned / $total) * 100) : 0;

        $paid        = $raffle->numbers->where('paid', 1)->count();
        $pendientes  = $raffle->numbers->whereIn('status', ['sold', 'reserved'])->where('paid', 0)->count();
        $totalCost   = $raffle->prizes->sum('cost');

        return view('admin.dashboard', compact(
            'raffle', 'total', 'free', 'reserved', 'sold', 'assigned', 'revenue', 'progress',
            'paid', 'pendientes', 'totalCost'
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
            'price'              => ['required', 'string', 'regex:/^[0-9][0-9.]*$/'],
            'total_numbers'      => 'required|integer|min:1',
            'image'              => 'required|image|max:5120',
            'prizes_count'       => 'required|integer|min:1|max:20',
            'prizes'             => 'required|array|min:1',
            'prizes.*.name'      => 'required|string|max:255',
            'prizes.*.description' => 'nullable|string|max:255',
            'titular_name'       => 'nullable|string|max:255',
            'alias'              => 'nullable|string|max:255',
            'promo_enabled'      => 'nullable|boolean',
            'promo_type'         => 'nullable|string|in:early_numbers,most_numbers',
            'promo_limit'        => 'nullable|integer|min:1',
            'promo_winner_count' => 'nullable|integer|min:1',
            'promo_prize_text'   => 'nullable|string|max:255',
            'weighted_draw_enabled' => 'nullable|boolean',
            'win_weights'        => 'nullable|array',
            'win_weights.0'      => 'nullable|numeric|min:0|max:1',
            'win_weights.1'      => 'nullable|numeric|min:0|max:1',
            'win_weights.2'      => 'nullable|numeric|min:0|max:1',
            'win_weights.3'      => 'nullable|numeric|min:0|max:1',
        ], [
            'price.required' => 'Indicá el precio del número. Para sorteos gratis usá 0.',
            'price.regex'    => 'El precio debe ser un monto válido en guaraníes. Para sorteos gratis usá 0.',
        ]);

        $price = (int) str_replace('.', '', $request->price);

        $recentDuplicate = Raffle::where('name', $request->name)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($recentDuplicate) {
            return back()
                ->withInput()
                ->withErrors(['name' => 'Este sorteo ya fue creado hace menos de 30 segundos. Recargá la página para verificar.']);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file      = $request->file('image');
            $filename  = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $imagePath = $file->storeAs('raffles', $filename, 'public');
            Log::info("📸 IMAGEN GUARDADA: " . $imagePath);
        }

        DB::transaction(function () use ($request, $price, $imagePath) {
            $promoEnabled   = $request->boolean('promo_enabled');
            $weightedEnabled = $request->boolean('weighted_draw_enabled');

            $winWeights = null;
            if ($weightedEnabled) {
                $raw = $request->input('win_weights', []);
                $winWeights = [
                    '0' => isset($raw[0]) ? (float)$raw[0] : 1.0,
                    '1' => isset($raw[1]) ? (float)$raw[1] : 0.5,
                    '2' => isset($raw[2]) ? (float)$raw[2] : 0.25,
                    '3' => isset($raw[3]) ? (float)$raw[3] : 0.0,
                ];
            }

            $raffle = Raffle::create([
                'name'                  => $request->name,
                'price'                 => $price,
                'total_numbers'         => $request->total_numbers,
                'image'                 => $imagePath,
                'status'                => 'active',
                'prizes_count'          => $request->prizes_count,
                'titular_name'          => $request->titular_name ?? 'Junior Enciso',
                'alias'                 => $request->alias ?? '7130138',
                'promo_enabled'         => $promoEnabled,
                'promo_type'            => $promoEnabled ? ($request->promo_type ?? 'early_numbers') : null,
                'promo_limit'           => $promoEnabled ? $request->promo_limit : null,
                'promo_winner_count'    => $promoEnabled ? ($request->promo_winner_count ?? 1) : 0,
                'promo_prize_text'      => $promoEnabled ? $request->promo_prize_text : null,
                'weighted_draw_enabled' => $weightedEnabled,
                'win_weights'           => $winWeights,
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

        $raffle = $num->raffle;
        Cache::forget("dashboard_stats_{$raffle->id}");
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

        DB::transaction(function () use ($raffle, $winner) {
            $raffle->update([
                'winner_number' => $winner->number,
                'winner_name'   => $winner->customer_name ?? 'Participante',
                'status'        => 'finished',
            ]);
        });

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
                $query->orderByRaw('CAST(number AS INTEGER) ASC');
            },
            'prizes',
        ])->findOrFail($id);

        // Preparar datos para el frontend
        $numbers = $raffle->numbers->map(function ($n) {
            return [
                'number'        => (int)$n->number,
                'customer_name' => $n->customer_name ?? '',
                'status'        => $n->status,
            ];
        })->toArray();

        // Ordenar premios de mayor a menor (1° Premio = mejor premio)
        $prizes = $raffle->prizes->sortByDesc('order')->values()->map(function ($p) {
            return [
                'name'          => $p->name,
                'description'   => $p->description ?? '',
                'winner_number' => $p->winner_number,
                'winner_name'   => $p->winner_name,
            ];
        })->toArray();

        return response()->json([
            'raffle_name'      => $raffle->name,
            'price'            => (int)$raffle->price,
            'titular_name'     => $raffle->titular_name ?? 'Junior Enciso',
            'alias'            => $raffle->alias ?? '7130138',
            'prizes'           => $prizes,
            'numbers'          => $numbers,
            'discount_active'  => (bool)$raffle->discount_active,
            'discount_pct'     => (int)$raffle->discount_pct,
            'promo_enabled'    => (bool)$raffle->promo_enabled,
            'promo_type'       => $raffle->promo_type,
            'promo_prize_text' => $raffle->promo_prize_text,
        ]);
    }

    public function guardarCostos($id, \Illuminate\Http\Request $request)
    {
        $raffle = Raffle::with('prizes')->findOrFail($id);
        $costs  = $request->input('costs', []);
        foreach ($raffle->prizes as $prize) {
            if (isset($costs[$prize->id])) {
                $prize->update(['cost' => max(0, (int) $costs[$prize->id])]);
            }
        }
        $total = $raffle->prizes->sum(fn($p) => (int)($costs[$p->id] ?? $p->cost));
        return response()->json(['ok' => true, 'total_cost' => $total]);
    }

    public function activarDescuento($id, \Illuminate\Http\Request $request)
    {
        $request->validate(['pct' => 'required|integer|min:1|max:99']);
        $raffle = Raffle::findOrFail($id);
        $raffle->update(['discount_active' => true, 'discount_pct' => $request->pct]);
        return response()->json(['ok' => true, 'pct' => $request->pct]);
    }

    public function desactivarDescuento($id)
    {
        $raffle = Raffle::findOrFail($id);
        $raffle->update(['discount_active' => false, 'discount_pct' => 0]);
        return response()->json(['ok' => true]);
    }

    // 🎁 PROMO: despacha al método correcto según promo_type
    private function drawPromo(Raffle $raffle)
    {
        if ($raffle->promo_type === 'most_numbers') {
            return $this->drawPromoMostNumbers($raffle);
        }

        return $this->drawPromoEarlyNumbers($raffle);
    }

    // 🎁 PROMO early_numbers: primeros promo_limit reservados, elegir aleatoriamente entre pagados
    private function drawPromoEarlyNumbers(Raffle $raffle)
    {
        $participants = $raffle->numbers()
            ->whereNotNull('reserved_at')
            ->orderBy('reserved_at', 'asc')
            ->take($raffle->promo_limit)
            ->get();

        $eligible = $participants->where('status', 'sold');

        if ($eligible->isEmpty()) {
            return collect();
        }

        $count = min($raffle->promo_winner_count, $eligible->count());

        return $eligible->shuffle()->take($count);
    }

    // 🎁 PROMO most_numbers: gana quien más números compró; empate se rompe por fecha de compra más antigua
    private function drawPromoMostNumbers(Raffle $raffle)
    {
        $soldNumbers = $raffle->numbers()
            ->where('status', 'sold')
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->get();

        if ($soldNumbers->isEmpty()) {
            return collect();
        }

        $ranked = $soldNumbers
            ->groupBy('customer_name')
            ->map(fn($nums) => [
                'count'          => $nums->count(),
                'earliest_at'    => $nums->min('reserved_at'),
                'representative' => $nums->sortBy('reserved_at')->first(),
            ])
            ->sort(function ($a, $b) {
                if ($a['count'] !== $b['count']) {
                    return $b['count'] - $a['count'];
                }
                return strcmp($a['earliest_at'] ?? '', $b['earliest_at'] ?? '');
            })
            ->take($raffle->promo_winner_count ?: 1);

        return $ranked->map(fn($item) => $item['representative']);
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

        // Para most_numbers el resultado es determinístico, no requiere que el sorteo principal esté completo.
        // Para early_numbers sí se requiere que el sorteo principal haya terminado.
        if ($raffle->promo_type !== 'most_numbers') {
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
            $message = $raffle->promo_type === 'most_numbers'
                ? 'No hay números vendidos con cliente asignado para determinar el ganador de la promo.'
                : "Ninguno de los primeros {$raffle->promo_limit} números reservados está confirmado como pagado. No hay participantes elegibles para la promo.";

            return response()->json([
                'success' => false,
                'message' => $message,
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

        return DB::transaction(function () use ($raffle, $soldNumbers, $prizeOrder) {
            Raffle::whereKey($raffle->id)->lockForUpdate()->firstOrFail();

            $prize = RafflePrize::where('raffle_id', $raffle->id)
                ->where('order', $prizeOrder)
                ->lockForUpdate()
                ->first();

            if (!$prize) {
                return response()->json([
                    'success' => false,
                    'message' => 'Premio no encontrado',
                ], 404);
            }

            // Si este premio ya fue sorteado, devolver el resultado guardado.
            if ($prize->winner_number) {
                return response()->json([
                    'success'           => true,
                    'already_drawn'     => true,
                    'prize_order'       => $prize->order,
                    'prize_name'        => $prize->name,
                    'prize_description' => $prize->description,
                    'winner_number'     => $prize->winner_number,
                    'winner_name'       => $prize->winner_name,
                ]);
            }

            $wonPrizes = RafflePrize::where('raffle_id', $raffle->id)
                ->whereNotNull('winner_number')
                ->get(['winner_number', 'winner_name']);

            $usedWinnerNumbers = $wonPrizes
                ->pluck('winner_number')
                ->map(fn($number) => (string) $number)
                ->all();

            // Contar premios ganados por cada cliente
            $winsPerCustomer = [];
            foreach ($wonPrizes as $won) {
                $entry = $soldNumbers->firstWhere('number', $won->winner_number);
                $name  = $entry ? ($entry->customer_name ?? 'Participante') : ($won->winner_name ?? 'Participante');
                $winsPerCustomer[$name] = ($winsPerCustomer[$name] ?? 0) + 1;
            }

            $eligible = $soldNumbers->reject(function ($num) use ($usedWinnerNumbers, $winsPerCustomer) {
                // Excluir números ya ganadores
                if (in_array((string) $num->number, $usedWinnerNumbers, true)) {
                    return true;
                }
                // Excluir números de clientes que ya alcanzaron el máximo de 2 premios
                $name = $num->customer_name ?? 'Participante';
                return ($winsPerCustomer[$name] ?? 0) >= 2;
            });

            if ($eligible->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay números elegibles para este premio',
                ], 422);
            }

            [$winner, $appliedWeight] = $this->selectWinner($eligible, $raffle, $soldNumbers);

            if (!$winner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Todos los participantes elegibles alcanzaron el límite de premios para este sorteo.',
                ], 422);
            }

            $prize->update([
                'winner_number'  => $winner->number,
                'winner_name'    => $winner->customer_name ?? 'Participante',
                'weight_applied' => $appliedWeight,
            ]);

            $weightInfo = $raffle->weighted_draw_enabled
                ? " [peso={$appliedWeight}]"
                : '';
            Log::info("🏆 PREMIO #{$prize->order} ({$prize->name}): {$winner->number} - " . ($winner->customer_name ?? 'Participante') . $weightInfo);

            $prizes   = RafflePrize::where('raffle_id', $raffle->id)->get();
            $allDrawn = $prizes->every(fn($p) => !is_null($p->winner_number));

            if ($allDrawn) {
                $mainPrize    = $prizes->sortByDesc('order')->first();
                $totalRevenue = $soldNumbers->count() * (int) $raffle->price;
                $totalCost    = $prizes->sum(fn($p) => (int) $p->cost);
                $realProfit   = $totalRevenue - $totalCost;

                $raffle->update([
                    'winner_number'  => $mainPrize->winner_number,
                    'winner_name'    => $mainPrize->winner_name,
                    'status'         => 'finished',
                    'real_profit_gs' => $realProfit,
                ]);
                Log::info("✅ TODOS LOS PREMIOS SORTEADOS — Sorteo #{$raffle->id} finalizado. Recaudado: ₲{$totalRevenue} | Costos: ₲{$totalCost} | Ganancia: ₲{$realProfit}");

                $this->notifyErpTreasury($raffle->id, $raffle->name, $realProfit);
            }

            return response()->json([
                'success'           => true,
                'prize_order'       => $prize->order,
                'prize_name'        => $prize->name,
                'prize_description' => $prize->description,
                'winner_number'     => $winner->number,
                'winner_name'       => $winner->customer_name ?? 'Participante',
                'all_drawn'         => $allDrawn,
                'prizes_total'      => $prizes->count(),
            ]);
        });
    }

    // ⚖️ Selección aleatoria ponderada según victorias previas del participante
    private function selectWinner($eligible, Raffle $raffle, $soldNumbers): array
    {
        if (!$raffle->weighted_draw_enabled) {
            return [$eligible->random(), 1.0];
        }

        // Mapear números ganadores anteriores → nombre del participante → cantidad de victorias
        $priorWinNumbers = RafflePrize::where('raffle_id', $raffle->id)
            ->whereNotNull('winner_number')
            ->pluck('winner_number')
            ->map(fn($n) => (string) $n)
            ->all();

        $winsPerCustomer = [];
        foreach ($priorWinNumbers as $winNum) {
            $entry = $soldNumbers->firstWhere('number', $winNum);
            $name  = $entry ? ($entry->customer_name ?? 'Participante') : 'Participante';
            $winsPerCustomer[$name] = ($winsPerCustomer[$name] ?? 0) + 1;
        }

        $weights = $raffle->win_weights ?? ['0' => 1.0, '1' => 0.5, '2' => 0.25, '3' => 0.0];
        $maxWins = count($weights) - 1;

        // Construir pool con pesos
        $pool        = [];
        $totalWeight = 0.0;

        foreach ($eligible as $num) {
            $name   = $num->customer_name ?? 'Participante';
            $wins   = $winsPerCustomer[$name] ?? 0;
            $key    = (string) min($wins, $maxWins);
            $weight = (float) ($weights[$key] ?? 0.0);

            if ($weight > 0.0) {
                $pool[]      = ['num' => $num, 'weight' => $weight];
                $totalWeight += $weight;
            }
        }

        if (empty($pool)) {
            return [null, 0.0];
        }

        // Selección aleatoria ponderada
        $rand       = (mt_rand() / mt_getrandmax()) * $totalWeight;
        $cumulative = 0.0;

        foreach ($pool as $entry) {
            $cumulative += $entry['weight'];
            if ($rand <= $cumulative) {
                return [$entry['num'], $entry['weight']];
            }
        }

        // Fallback por precisión de punto flotante
        $last = end($pool);
        return [$last['num'], $last['weight']];
    }

    // 🏦 Notificar al ERP para registrar la ganancia/pérdida en la caja SorteosPY
    private function notifyErpTreasury(int $raffleId, string $raffleName, int $profit): void
    {
        $erpUrl = rtrim(env('ERP_TREASURY_URL', 'https://erp.katuetegroup.com'), '/');
        $token  = env('HOLDING_API_TOKEN', '');

        if (!$erpUrl || !$token) {
            Log::warning("Treasury webhook: ERP_TREASURY_URL o HOLDING_API_TOKEN no configurados.");
            return;
        }

        try {
            $response = Http::timeout(8)->post("{$erpUrl}/api/treasury/sorteos-income", [
                'raffle_id'   => $raffleId,
                'raffle_name' => $raffleName,
                'amount'      => $profit,
                'token'       => $token,
            ]);

            if ($response->successful()) {
                Log::info("Treasury webhook OK: Sorteo #{$raffleId} → ERP. Respuesta: " . $response->body());
            } else {
                Log::error("Treasury webhook falló (HTTP {$response->status()}): " . $response->body());
            }
        } catch (\Throwable $e) {
            // No bloquear el sorteo por falla de webhook
            Log::error("Treasury webhook excepción: " . $e->getMessage());
        }
    }
}
