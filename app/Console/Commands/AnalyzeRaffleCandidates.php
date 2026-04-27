<?php

namespace App\Console\Commands;

use App\Models\Raffle;
use App\Models\RaffleCandidateAnalysis;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyzeRaffleCandidates extends Command
{
    protected $signature = 'raffles:analyze-candidates
        {--csv= : Ruta del CSV de productos}
        {--min-stock=1 : Stock minimo aceptado}
        {--usd-rate=7000 : Cotizacion fija USD a Gs}';

    protected $description = 'Analiza productos candidatos para sorteos y guarda el resultado para aprobacion.';

    private const MIN_PRICE_PER_NUMBER = 3000;
    private const STANDARD_MAX_PRICE_PER_NUMBER = 16000;
    private const MAX_PRICE_PER_NUMBER = 50000;
    private const MAPY_REPORTS_DIR = '/var/www/reportes_mapy';
    private const MIN_PERCEIVED_COST_GS = 25000;
    private const MIN_PRIZE_COST_GS = 60000;
    private const MIN_PERCEIVED_VALUE_SCORE = 68;
    private const MIN_MAIN_PRIZE_PERCEIVED_VALUE_SCORE = 82;
    private const IDEAL_MIN_PRICE = 5000;
    private const IDEAL_MAX_PRICE = 15000;

    public function handle(): int
    {
        $csvPath = $this->resolveCsvPath();

        if (!$csvPath) {
            $this->error('No se encontro CSV. Usa --csv=/ruta/productos.csv o guarda reportes MAPY en ' . self::MAPY_REPORTS_DIR . '.');
            return self::FAILURE;
        }

        $usdRate = max(1, (int) $this->option('usd-rate'));
        $minStock = max(0, (int) $this->option('min-stock'));
        $history = $this->historicalRaffles();
        $rotation = $this->rotationContext();
        $rows = $this->readCsv($csvPath);

        if ($rows->isEmpty()) {
            $this->error('El CSV no tiene filas validas.');
            return self::FAILURE;
        }

        [$plans, $discarded] = $this->analyzeRows($rows, $history, $rotation, $usdRate, $minStock);

        $big = $plans->where('raffle_type', 'grande')->sortByDesc('score')->take(1)->values();
        $selectedProducts = $this->planPrizeNames($big);
        $flash = $plans
            ->where('raffle_type', 'relampago')
            ->reject(fn ($p) => $this->planOverlapsProducts($p, $selectedProducts))
            ->sortByDesc('score')
            ->take(1)
            ->values();
        $selectedProducts = array_merge($selectedProducts, $this->planPrizeNames($flash));
        $alternativeBig = $plans
            ->where('raffle_type', 'grande')
            ->reject(fn ($p) => $this->planOverlapCount($p, $selectedProducts) > 2)
            ->sortByDesc('score')
            ->take(1)
            ->values();
        if ($alternativeBig->isEmpty()) {
            $alternativeBig = $plans
                ->where('raffle_type', 'grande')
                ->reject(fn ($p) => $big->contains(fn ($selected) => $selected['product_name'] === $p['product_name']))
                ->sortByDesc('score')
                ->take(1)
                ->values();
        }
        $selectedProducts = array_merge($selectedProducts, $this->planPrizeNames($alternativeBig));
        $alternativeFlash = $plans
            ->where('raffle_type', 'relampago')
            ->reject(fn ($p) => $this->planOverlapsProducts($p, $selectedProducts))
            ->sortByDesc('score')
            ->take(1)
            ->values();
        $alternatives = $alternativeBig->merge($alternativeFlash)->values();

        $batchUuid = (string) Str::uuid();
        $this->saveResults($batchUuid, $csvPath, $big, $flash, $alternatives, $discarded);
        $this->printResults($batchUuid, $big, $flash, $alternatives, $discarded->count(), $history->count());

        return self::SUCCESS;
    }

    private function resolveCsvPath(): ?string
    {
        $option = $this->option('csv');
        $paths = array_filter([
            $option ? (string) $option : null,
            $this->latestMapyReportPath(),
            base_path('productos.csv'),
        ]);

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function latestMapyReportPath(): ?string
    {
        if (!is_dir(self::MAPY_REPORTS_DIR)) {
            return null;
        }

        $latest = null;
        $latestDate = null;

        foreach (glob(self::MAPY_REPORTS_DIR . '/*.csv') ?: [] as $path) {
            $filename = basename($path);
            if (!preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                continue;
            }

            $date = $matches[1];
            if ($latestDate === null || $date > $latestDate) {
                $latestDate = $date;
                $latest = $path;
            }
        }

        return $latest;
    }

    private function readCsv(string $path): Collection
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return collect();
        }

        $rows = collect();
        $normalizedHeaders = null;
        $delimiter = ';';
        $currentCategory = '';

        while (($line = fgets($handle)) !== false) {
            $line = trim($this->stripBom($line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/^==\s*(.*?)\s*==$/u', $line, $matches)) {
                $currentCategory = $this->humanCategory($matches[1]);
                $normalizedHeaders = null;
                continue;
            }

            $lineDelimiter = $this->detectDelimiter($line);
            $values = str_getcsv($line, $lineDelimiter);
            $possibleHeaders = array_map(fn ($h) => $this->normalizeKey((string) $h), $values);

            if ($this->isMapyHeader($possibleHeaders)) {
                $normalizedHeaders = $possibleHeaders;
                $delimiter = $lineDelimiter;
                continue;
            }

            if ($normalizedHeaders === null) {
                continue;
            }

            if ($lineDelimiter !== $delimiter) {
                $values = str_getcsv($line, $delimiter);
            }

            if ($values === [null] || count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($normalizedHeaders as $index => $key) {
                $row[$key] = trim((string) ($values[$index] ?? ''));
            }
            if ($currentCategory !== '' && trim((string) ($row['rubro'] ?? '')) === '') {
                $row['rubro'] = $currentCategory;
            }
            $rows->push($row);
        }

        fclose($handle);
        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);
        return (string) array_key_first($counts);
    }

    private function isMapyHeader(array $headers): bool
    {
        return in_array('codigo', $headers, true)
            && in_array('nombre', $headers, true)
            && in_array('costo_promedio', $headers, true);
    }

    private function analyzeRows(Collection $rows, Collection $history, array $rotation, int $usdRate, int $minStock): array
    {
        $candidates = collect();
        $discarded = collect();

        foreach ($rows as $index => $row) {
            $productCode = $this->firstValue($row, ['codigo', 'cod', 'code', 'sku', 'id_producto', 'product_id', 'barcode', 'codigo_barra']);
            $productName = $this->firstValue($row, ['producto', 'product', 'nombre', 'name', 'descripcion', 'description']);
            $costBase = $this->parseMoney($this->firstValue($row, ['costo_promedio', 'costo', 'cost', 'precio', 'price', 'precio_usd', 'cost_usd', 'amount']));
            $stock = $this->parseNullableInt($this->firstValue($row, ['stock', 'existencia', 'qty', 'cantidad', 'quantity']));
            $givenCategory = $this->firstValue($row, ['rubro', 'categoria', 'category', 'tipo']);

            if ($productName === '' || $costBase <= 0) {
                $discarded->push($this->discard($row, $index, $productName ?: 'Fila ' . ($index + 1), ['Falta producto o costo USD valido.']));
                continue;
            }

            $costGs = $this->mapyCostToGs($costBase, $usdRate);
            $category = $givenCategory !== '' ? $this->humanCategory($givenCategory) : $this->inferCategory($productName);
            $filterReasons = $this->filterReasons($productName, $category, $costGs, $stock, $minStock);

            if ($filterReasons) {
                $discarded->push($this->discard($row, $index, $productName, $filterReasons, $category, $costGs, $stock));
                continue;
            }

            $candidate = [
                'row' => $row,
                'row_index' => $index,
                'product_code' => $productCode,
                'product_name' => $productName,
                'category' => $category,
                'cost_gs' => $costGs,
                'cost_base' => $costBase,
                'stock' => $stock,
                'attraction_score' => $this->attractionScore($productName, $category),
                'saleability_score' => $this->saleabilityScore($productName, $category, $costGs, $stock),
                'perceived_value_score' => $this->perceivedValueScore($productName, $category, $costGs),
                'perceived_value_level' => $this->perceivedValueLevel($this->perceivedValueScore($productName, $category, $costGs)),
                'rotation_score' => $this->productRotationScore($productName, $category, $this->marketingType($productName, $category), $rotation),
            ];

            $candidates->push($candidate);
        }

        $candidates = $this->dedupeCandidates($candidates);
        $flashPlans = $candidates
            ->map(fn ($candidate) => $this->buildFlashPlan($candidate, $history, $rotation))
            ->filter()
            ->values();
        $bigPlans = $this->buildBigPlans($candidates, $history, $rotation);
        $plans = $flashPlans->merge($bigPlans);

        $plannedNames = $plans->flatMap(fn ($plan) => collect($plan['metrics']['prizes'] ?? [])->pluck('name'))->unique()->all();
        foreach ($candidates as $candidate) {
            if (!in_array($candidate['product_name'], $plannedNames, true)) {
                $discarded->push($this->discard($candidate['row'], $candidate['row_index'], $candidate['product_name'], ['No logro una combinacion rentable y vendible con precio por numero entre 3.000 y 50.000 Gs segun el valor del premio.'], $candidate['category'], $candidate['cost_gs'], $candidate['stock']));
            }
        }

        return [$plans, $discarded];
    }

    private function dedupeCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->sortByDesc(fn ($candidate) => $candidate['attraction_score'] + min(30, $candidate['cost_gs'] / 10000))
            ->unique(fn ($candidate) => $this->candidateKey($candidate))
            ->values();
    }

    private function candidateKey(array $candidate): string
    {
        return ($candidate['product_code'] ?: 'sin_codigo') . '|' . $this->normalizeText($candidate['product_name']);
    }

    private function planPrizeNames(Collection $plans): array
    {
        return $plans
            ->flatMap(fn ($plan) => collect($plan['metrics']['prizes'] ?? [])->pluck('name')->push($plan['product_name']))
            ->map(fn ($name) => $this->rotationProductKey((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function planOverlapsProducts(array $plan, array $usedProductKeys): bool
    {
        return $this->planOverlapCount($plan, $usedProductKeys) > 0;
    }

    private function planOverlapCount(array $plan, array $usedProductKeys): int
    {
        $keys = collect($plan['metrics']['prizes'] ?? [])
            ->pluck('name')
            ->push($plan['product_name'])
            ->map(fn ($name) => $this->rotationProductKey((string) $name))
            ->filter()
            ->all();

        return count(array_intersect($keys, $usedProductKeys));
    }

    private function buildFlashPlan(array $candidate, Collection $history, array $rotation): ?array
    {
        if ($candidate['cost_gs'] >= 120000) {
            return null;
        }

        if ($candidate['perceived_value_score'] < self::MIN_MAIN_PRIZE_PERCEIVED_VALUE_SCORE) {
            return null;
        }

        return $this->buildPlanFromPrizes([$this->prizePayload($candidate, 'principal')], 'relampago', $history, $rotation);
    }

    private function buildBigPlans(Collection $candidates, Collection $history, array $rotation): Collection
    {
        $secondaryPool = $candidates
            ->filter(fn ($candidate) => $candidate['cost_gs'] >= self::MIN_PRIZE_COST_GS && $candidate['cost_gs'] <= 320000)
            ->filter(fn ($candidate) => $candidate['perceived_value_score'] >= self::MIN_PERCEIVED_VALUE_SCORE)
            ->sortByDesc(fn ($candidate) => ($candidate['perceived_value_score'] * 0.42) + ($candidate['saleability_score'] * 0.24) + ($candidate['attraction_score'] * 0.16) + ($candidate['rotation_score'] * 0.18))
            ->values();

        return $candidates
            ->filter(fn ($candidate) => $candidate['cost_gs'] >= 250000 && $candidate['attraction_score'] >= 58)
            ->filter(fn ($candidate) => $candidate['perceived_value_score'] >= self::MIN_MAIN_PRIZE_PERCEIVED_VALUE_SCORE)
            ->sortByDesc(fn ($candidate) => ($candidate['perceived_value_score'] * 0.42) + ($candidate['attraction_score'] * 0.22) + ($candidate['saleability_score'] * 0.18) + ($candidate['rotation_score'] * 0.18))
            ->take(70)
            ->map(function ($principal) use ($secondaryPool, $history, $rotation) {
                $secondary = $this->secondaryPrizesFor($principal, $secondaryPool);

                if ($secondary->count() < 4) {
                    return null;
                }

                $prizes = collect([$this->prizePayload($principal, 'principal')])
                    ->merge($secondary->take(5)->map(fn ($candidate) => $this->prizePayload($candidate, 'secundario')))
                    ->values()
                    ->all();

                return $this->buildPlanFromPrizes($prizes, 'grande', $history, $rotation);
            })
            ->filter()
            ->values();
    }

    private function secondaryPrizesFor(array $principal, Collection $pool): Collection
    {
        $principalKey = $this->candidateKey($principal);
        $targetMax = max(50000, (int) min(180000, $principal['cost_gs'] * 0.28));
        $usedTypes = [$this->marketingType($principal['product_name'], $principal['category'])];
        $usedCategories = [$principal['category']];

        $principalOffset = abs(crc32($principalKey));
        $ranked = $pool
            ->reject(fn ($candidate) => $this->candidateKey($candidate) === $principalKey)
            ->sortByDesc(function ($candidate) use ($principal, $targetMax, $principalOffset) {
                $categoryBonus = $candidate['category'] === $principal['category'] ? -18 : 18;
                $costFit = max(0, 30 - (abs($candidate['cost_gs'] - ($targetMax * 0.55)) / 4500));
                $noveltyJitter = (abs(crc32($this->candidateKey($candidate) . '|' . $principalOffset)) % 17) - 8;

                return ($candidate['perceived_value_score'] * 0.50) + ($candidate['saleability_score'] * 0.26) + $categoryBonus + ($costFit * 0.5) + $noveltyJitter;
            })
            ->values();

        $selected = collect();
        foreach ($ranked as $candidate) {
            $type = $this->marketingType($candidate['product_name'], $candidate['category']);

            if (in_array($type, $usedTypes, true)) {
                continue;
            }

            if (in_array($candidate['category'], $usedCategories, true) && count(array_unique($usedCategories)) < 4) {
                continue;
            }

            $selected->push($candidate);
            $usedTypes[] = $type;
            $usedCategories[] = $candidate['category'];

            if ($selected->count() === 5) {
                break;
            }
        }

        if ($selected->count() < 4) {
            foreach ($ranked as $candidate) {
                if ($selected->contains(fn ($item) => $this->candidateKey($item) === $this->candidateKey($candidate))) {
                    continue;
                }

                $type = $this->marketingType($candidate['product_name'], $candidate['category']);
                if (in_array($type, $usedTypes, true)) {
                    continue;
                }

                $selected->push($candidate);
                $usedTypes[] = $type;

                if ($selected->count() === 5) {
                    break;
                }
            }
        }

        return $selected->values();
    }

    private function prizePayload(array $candidate, string $role): array
    {
        return [
            'role' => $role,
            'name' => $candidate['product_name'],
            'category' => $candidate['category'],
            'marketing_type' => $this->marketingType($candidate['product_name'], $candidate['category']),
            'product_code' => $candidate['product_code'] ?: null,
            'cost_gs' => $candidate['cost_gs'],
            'cost_base' => $candidate['cost_base'] ?? null,
            'perceived_value_score' => $candidate['perceived_value_score'] ?? $this->perceivedValueScore($candidate['product_name'], $candidate['category'], $candidate['cost_gs']),
            'perceived_value_level' => $candidate['perceived_value_level'] ?? null,
            'rotation_score' => $candidate['rotation_score'] ?? 100,
            'stock' => $candidate['stock'],
            'raw_product' => $candidate['row'],
        ];
    }

    private function buildPlanFromPrizes(array $prizes, string $type, Collection $history, array $rotation): ?array
    {
        $mainPrize = $prizes[0];
        $totalCost = (int) collect($prizes)->sum('cost_gs');
        $prizesCount = count($prizes);
        $range = $type === 'relampago' ? [20, 50] : [80, 500];
        $targetMargin = $type === 'relampago' ? 0.42 : 0.35;
        $minProfit = $type === 'relampago' ? 45000 : max(150000, (int) round($totalCost * 0.28));

        if ($type === 'grande' && ($prizesCount < 5 || $prizesCount > 6)) {
            return null;
        }

        $marketingMixScore = $type === 'grande' ? $this->planMarketingMixScore($prizes) : 100;
        $rotationScore = $this->planRotationScore($prizes, $type, $rotation);
        $attractionScore = $this->planAttractionScore($prizes);
        $perceivedValueScore = $this->planPerceivedValueScore($prizes);
        if ($type === 'grande' && $marketingMixScore < 70) {
            return null;
        }

        $best = null;
        for ($numbers = $range[0]; $numbers <= $range[1]; $numbers++) {
            $targetRevenue = max($totalCost + $minProfit, (int) ceil($totalCost / (1 - $targetMargin)));
            $price = $this->priceForPlan($targetRevenue, $numbers, $type, $perceivedValueScore, $attractionScore, $totalCost, $prizesCount);

            if ($price > self::MAX_PRICE_PER_NUMBER) {
                continue;
            }

            $revenue = $numbers * $price;
            $profit = $revenue - $totalCost;
            $margin = $revenue > 0 ? $profit / $revenue : 0;

            if ($margin < 0.22 || $profit < $minProfit) {
                continue;
            }

            $priceFit = $this->priceFitScore($price);
            $profitScore = min(100, ($profit / max(1, $totalCost)) * 100);
            $saleabilityScore = $this->planSaleabilityScore($prizes, $price, $numbers);
            $historyScore = $this->historyScore([
                'product_name' => $mainPrize['name'],
                'category' => $mainPrize['category'],
                'cost_gs' => $totalCost,
            ], $type, $history);
            $typeFit = $this->typeFitScore($totalCost, $type, $prizesCount);

            $score = round(
                ($profitScore * 0.08)
                + ($priceFit * 0.16)
                + ($attractionScore * 0.12)
                + ($saleabilityScore * 0.14)
                + ($perceivedValueScore * 0.22)
                + ($historyScore * 0.06)
                + ($marketingMixScore * 0.10)
                + ($rotationScore * 0.12),
                2
            );

            if (!$best || $score > $best['score']) {
                $displayName = $type === 'grande' ? $this->comboName($prizes) : $mainPrize['name'];
                $displayCategory = $type === 'grande' ? 'Combo mixto' : $mainPrize['category'];
                $candidate = [
                    'product_name' => $displayName,
                    'category' => $displayCategory,
                    'cost_gs' => $totalCost,
                ];
                $comparison = $this->historicalComparison($candidate, $type, $history);
                $best = [
                    'product_name' => $displayName,
                    'category' => $displayCategory,
                    'raffle_type' => $type,
                    'cost_gs' => $totalCost,
                    'stock' => $mainPrize['stock'],
                    'numbers_count' => $numbers,
                    'price_per_number_gs' => $price,
                    'revenue_gs' => $revenue,
                    'estimated_profit_gs' => $profit,
                    'score' => $score,
                    'risk_level' => $this->riskLevel($score, $margin, $price),
                    'reason' => $this->reason($candidate, $type, $numbers, $price, $profit, $margin, $prizesCount, $saleabilityScore, $perceivedValueScore, $rotationScore),
                    'historical_comparison' => $comparison,
                    'filter_status' => 'selected',
                    'filter_reasons' => [],
                    'metrics' => [
                        'product_code' => $mainPrize['product_code'],
                        'raw_product' => $mainPrize['raw_product'],
                        'prizes' => $prizes,
                        'prizes_count' => $prizesCount,
                        'margin_pct' => round($margin * 100, 2),
                        'attraction_score' => $attractionScore,
                        'saleability_score' => round($saleabilityScore, 2),
                        'perceived_value_score' => round($perceivedValueScore, 2),
                        'perceived_value_level' => $this->perceivedValueLevel($perceivedValueScore),
                        'history_score' => round($historyScore, 2),
                        'price_fit_score' => round($priceFit, 2),
                        'profit_score' => round($profitScore, 2),
                        'type_fit_score' => round($typeFit, 2),
                        'marketing_mix_score' => round($marketingMixScore, 2),
                        'rotation_score' => round($rotationScore, 2),
                        'rotation_summary' => $this->rotationSummary($prizes, $rotation),
                        'urgency_messages' => $this->urgencyMessages($displayName, $type, $prizes, $numbers, $price),
                        'main_prize' => $mainPrize,
                    ],
                ];
            }
        }

        return $best;
    }

    private function historicalRaffles(): Collection
    {
        return Raffle::with(['prizes', 'numbers'])
            ->where('status', 'finished')
            ->orderBy('id')
            ->get()
            ->map(function (Raffle $raffle) {
                $sold = $raffle->numbers->where('status', 'sold')->count();
                $cost = (int) (($raffle->cost_gs ?? 0) ?: $raffle->prizes->sum('cost'));
                $revenue = (int) ($sold * (float) $raffle->price);
                $names = trim($raffle->name . ' ' . $raffle->prizes->pluck('name')->implode(' '));
                $saleHours = $raffle->created_at && $raffle->updated_at
                    ? max(1, $raffle->created_at->diffInHours($raffle->updated_at))
                    : null;

                return [
                    'name' => $raffle->name,
                    'category' => $raffle->category ?: $this->inferCategory($names),
                    'price' => (int) $raffle->price,
                    'total_numbers' => (int) $raffle->total_numbers,
                    'sold' => $sold,
                    'sold_pct' => $raffle->total_numbers > 0 ? $sold / $raffle->total_numbers : 0,
                    'cost' => $cost,
                    'profit' => $revenue - $cost,
                    'sale_hours' => $saleHours,
                    'text' => $names,
                ];
            });
    }

    private function rotationContext(): array
    {
        $productCounts = [];
        $categoryCounts = [];
        $typeCounts = [];
        $comboCounts = [];

        $remember = function (string $name, string $category, ?string $type = null, int $weight = 1) use (&$productCounts, &$categoryCounts, &$typeCounts): void {
            $key = $this->rotationProductKey($name);
            $productCounts[$key] = ($productCounts[$key] ?? 0) + $weight;
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + $weight;

            $typeKey = $type ?: $this->marketingType($name, $category);
            $typeCounts[$typeKey] = ($typeCounts[$typeKey] ?? 0) + $weight;
        };

        RaffleCandidateAnalysis::query()
            ->whereIn('filter_status', ['selected', 'archived'])
            ->where('selection_group', '!=', 'descartado')
            ->where('created_at', '>=', now()->subDays(21))
            ->latest('id')
            ->limit(60)
            ->get()
            ->each(function (RaffleCandidateAnalysis $analysis) use ($remember, &$comboCounts): void {
                $prizes = collect($analysis->metrics['prizes'] ?? []);
                if ($prizes->isEmpty()) {
                    $remember($analysis->product_name, $analysis->category, null, 2);
                    return;
                }

                $signature = $this->comboSignature($prizes->all());
                $comboCounts[$signature] = ($comboCounts[$signature] ?? 0) + 1;

                foreach ($prizes as $index => $prize) {
                    $remember(
                        (string) ($prize['name'] ?? ''),
                        (string) ($prize['category'] ?? $analysis->category),
                        $prize['marketing_type'] ?? null,
                        $index === 0 ? 3 : 2
                    );
                }
            });

        Raffle::with('prizes')
            ->latest('id')
            ->limit(20)
            ->get()
            ->each(function (Raffle $raffle) use ($remember): void {
                foreach ($raffle->prizes as $index => $prize) {
                    $name = (string) $prize->name;
                    $category = $raffle->category ?: $this->inferCategory($name);
                    $remember($name, $category, null, $index === 0 ? 3 : 2);
                }
            });

        return [
            'products' => $productCounts,
            'categories' => $categoryCounts,
            'types' => $typeCounts,
            'combos' => $comboCounts,
        ];
    }

    private function saveResults(string $batchUuid, string $csvPath, Collection $big, Collection $flash, Collection $alternatives, Collection $discarded): void
    {
        DB::transaction(function () use ($batchUuid, $csvPath, $big, $flash, $alternatives, $discarded) {
            RaffleCandidateAnalysis::query()
                ->where('filter_status', 'selected')
                ->whereNull('approved_at')
                ->update(['filter_status' => 'archived']);

            foreach (['top_grande' => $big, 'top_relampago' => $flash, 'alternativa' => $alternatives] as $group => $items) {
                foreach ($items as $item) {
                    RaffleCandidateAnalysis::create(array_merge($item, [
                        'batch_uuid' => $batchUuid,
                        'source_file' => $csvPath,
                        'selection_group' => $group,
                    ]));
                }
            }

            foreach ($discarded as $item) {
                RaffleCandidateAnalysis::create(array_merge($item, [
                    'batch_uuid' => $batchUuid,
                    'source_file' => $csvPath,
                    'selection_group' => 'descartado',
                ]));
            }
        });
    }

    private function printResults(string $batchUuid, Collection $big, Collection $flash, Collection $alternatives, int $discardedCount, int $historyCount): void
    {
        $this->info('Analisis guardado para aprobacion. Batch: ' . $batchUuid);
        $this->line('Historico usado: ' . $historyCount . ' sorteos terminados. Descartados por filtro: ' . $discardedCount);

        $this->printSection('Sorteo grande recomendado', $big);
        $this->printSection('Sorteo flash recomendado', $flash);
        $this->printSection('Alternativas recomendadas', $alternatives);
    }

    private function printSection(string $title, Collection $items): void
    {
        $this->newLine();
        $this->info($title);

        if ($items->isEmpty()) {
            $this->warn('Sin candidatos suficientes.');
            return;
        }

        foreach ($items as $index => $item) {
            $prizes = $item['metrics']['prizes'] ?? [];
            $this->line(($index + 1) . '. Producto: ' . $item['product_name']);
            $this->line('   Rubro: ' . $item['category']);
            $this->line('   Tipo: ' . ($item['raffle_type'] === 'relampago' ? 'flash' : 'grande'));
            if ($item['raffle_type'] === 'grande') {
                $this->line('   Premios:');
                foreach ($prizes as $prizeIndex => $prize) {
                    $this->line('      ' . ($prizeIndex + 1) . '. ' . $prize['name'] . ' - ' . $this->gs((int) $prize['cost_gs']));
                }
                $this->line('   Costo total en Gs: ' . $this->gs($item['cost_gs']));
            } else {
                $this->line('   Costo del premio en Gs: ' . $this->gs($item['cost_gs']));
            }
            $this->line('   Cantidad de numeros: ' . $item['numbers_count']);
            $this->line('   Precio por numero en Gs: ' . $this->gs($item['price_per_number_gs']));
            $this->line('   Recaudacion total: ' . $this->gs($item['revenue_gs']));
            $this->line('   Ganancia estimada: ' . $this->gs($item['estimated_profit_gs']));
            $this->line('   Score: ' . number_format((float) $item['score'], 2, ',', '.'));
            $this->line('   Nivel de riesgo: ' . $item['risk_level']);
            $this->line('   Motivo: ' . $item['reason']);
            $this->line('   Comparacion con sorteos anteriores: ' . $item['historical_comparison']);
        }
    }

    private function filterReasons(string $productName, string $category, int $costGs, ?int $stock, int $minStock): array
    {
        $reasons = [];
        $name = $this->normalizeText($productName);

        if ($stock !== null && $stock < $minStock) {
            $reasons[] = 'Stock bajo para sorteo.';
        }

        if ($this->isAccessoryLikeProduct($name)) {
            $reasons[] = 'Accesorio o producto secundario: no califica como premio principal ni secundario.';
        }

        $badKeywords = ['repuesto', 'flex', 'modulo', 'display', 'pantalla', 'placa', 'carcasa', 'funda', 'capa', 'case', 'cover', 'cable', 'cabo', 'adaptador', 'tornillo', 'antena', 'lnb', 'fuente', 'bateria', 'mount', 'clamp', 'remote', 'dock', 'bastao', 'backoor', 'alfajor', 'galleta', 'alcaparra', 'sachet', 'usb power', 'charger', 'alarma', 'nunchuk', 'wiiu', 'control p', 'headset ear', 'protector', 'floaty'];
        foreach ($badKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                $reasons[] = 'Producto con bajo valor percibido para sorteo.';
                break;
            }
        }

        if ($category === 'Repuestos') {
            $reasons[] = 'Rubro descartado por baja atraccion comercial.';
        }

        if ($costGs <= 0) {
            $reasons[] = 'Costo invalido.';
        }

        if ($costGs > 0 && $costGs < self::MIN_PRIZE_COST_GS) {
            $reasons[] = 'Costo demasiado bajo para sostener valor percibido en un sorteo.';
        }

        if ($this->perceivedValueScore($productName, $category, $costGs) < self::MIN_PERCEIVED_VALUE_SCORE) {
            $reasons[] = 'No parece un premio que alguien realmente quiera ganar.';
        }

        if ($this->saleabilityScore($productName, $category, $costGs, $stock) < 50) {
            $reasons[] = 'Producto dificil de vender por bajo atractivo o valor percibido.';
        }

        return array_values(array_unique($reasons));
    }

    private function discard(array $row, int $index, string $productName, array $reasons, ?string $category = null, int $costGs = 0, ?int $stock = null): array
    {
        return [
            'product_name' => $productName,
            'category' => $category ?: 'Sin clasificar',
            'raffle_type' => 'descartado',
            'cost_gs' => max(0, $costGs),
            'stock' => $stock,
            'numbers_count' => 0,
            'price_per_number_gs' => 0,
            'revenue_gs' => 0,
            'estimated_profit_gs' => 0,
            'score' => 0,
            'risk_level' => 'alto',
            'reason' => implode(' ', $reasons),
            'historical_comparison' => null,
            'filter_status' => 'discarded',
            'filter_reasons' => $reasons,
            'metrics' => ['csv_row' => $index + 1, 'raw' => $row],
        ];
    }

    private function reason(array $candidate, string $type, int $numbers, int $price, int $profit, float $margin, int $prizesCount, float $saleabilityScore, float $perceivedValueScore, float $rotationScore): string
    {
        $typeText = $type === 'relampago' ? 'flash de venta rapida' : 'sorteo grande con paquete de premios';
        $accessibility = $price >= self::IDEAL_MIN_PRICE && $price <= self::IDEAL_MAX_PRICE
            ? 'precio accesible dentro del rango ideal'
            : 'precio aceptable aunque fuera del rango ideal';
        $prizeText = $type === 'relampago'
            ? '1 premio directo'
            : $prizesCount . ' premios, con un principal atractivo y secundarios que aumentan valor percibido';

        return sprintf(
            'Elegido como %s porque combina rubro %s, %s, %s, %d numeros, valor percibido %s (%.0f/100), facilidad de venta %.0f/100, rotacion/novedad %.0f/100 y margen estimado de %.1f%% con ganancia de %s.',
            $typeText,
            $candidate['category'],
            $accessibility,
            $prizeText,
            $numbers,
            $this->perceivedValueLevel($perceivedValueScore),
            $perceivedValueScore,
            $saleabilityScore,
            $rotationScore,
            $margin * 100,
            $this->gs($profit)
        );
    }

    private function historicalComparison(array $candidate, string $type, Collection $history): string
    {
        if ($history->isEmpty()) {
            return 'Sin sorteos anteriores suficientes; se usa heuristica inicial por rubro, precio por numero y cantidad de numeros.';
        }

        $best = $history
            ->map(function ($h) use ($candidate, $type) {
                $similarity = $this->similarity($candidate['product_name'], $h['text']);
                $categoryMatch = $candidate['category'] === $h['category'] ? 0.35 : 0;
                $typeMatch = $type === 'relampago'
                    ? ($h['total_numbers'] <= 60 ? 0.15 : 0)
                    : ($h['total_numbers'] >= 70 ? 0.15 : 0);

                return $h + ['match_score' => $similarity + $categoryMatch + $typeMatch];
            })
            ->sortByDesc('match_score')
            ->first();

        if (!$best || $best['match_score'] <= 0.05) {
            return 'No hay producto historico muy similar; referencia general: los sorteos anteriores completos vendieron entre 50 y 110 numeros a 10.000 Gs.';
        }

        return sprintf(
            'Mas cercano: "%s" (%s), %d numeros a %s, venta %.0f%% completada. Este candidato mantiene precio/cantidad dentro de ese comportamiento historico.',
            $best['name'],
            $best['category'],
            $best['total_numbers'],
            $this->gs($best['price']),
            $best['sold_pct'] * 100
        );
    }

    private function historyScore(array $candidate, string $type, Collection $history): float
    {
        if ($history->isEmpty()) {
            return 50;
        }

        return (float) $history->map(function ($h) use ($candidate, $type) {
            $score = 35;
            $score += $candidate['category'] === $h['category'] ? 25 : 0;
            $score += $this->similarity($candidate['product_name'], $h['text']) * 25;
            $score += $h['sold_pct'] >= 1 ? 10 : ($h['sold_pct'] * 10);
            $score += ($type === 'relampago' && $h['total_numbers'] <= 60) || ($type === 'grande' && $h['total_numbers'] >= 70) ? 5 : 0;
            return min(100, $score);
        })->max();
    }

    private function attractionScore(string $productName, string $category): int
    {
        $scoreByCategory = [
            'Electronica' => 88,
            'Perfumeria' => 82,
            'Bebidas' => 80,
            'Comestibles' => 45,
            'Cosmeticos Salud E Hig.' => 76,
            'Camping/Pesca/Ferr/Jard/Auto' => 50,
            'Hogar' => 74,
            'Asado' => 76,
            'Termos' => 78,
            'Belleza' => 78,
            'Otros' => 62,
        ];

        $score = $scoreByCategory[$category] ?? 62;
        $name = $this->normalizeText($productName);

        foreach (['smart', 'tv', 'jbl', 'iphone', 'samsung', 'perfume', 'whisky', 'combo', 'kit', 'termo', 'absolut', 'johnnie', 'jack', 'speaker', 'licuadora'] as $keyword) {
            if (str_contains($name, $keyword)) {
                $score += 6;
            }
        }

        return min(100, $score);
    }

    private function saleabilityScore(string $productName, string $category, int $costGs, ?int $stock): float
    {
        $score = ($this->attractionScore($productName, $category) * 0.45)
            + ($this->perceivedValueScore($productName, $category, $costGs) * 0.55);
        $name = $this->normalizeText($productName);

        if ($costGs >= self::MIN_PRIZE_COST_GS && $costGs <= 450000) {
            $score += 10;
        } elseif ($costGs > 800000) {
            $score -= 12;
        } elseif ($costGs < self::MIN_PRIZE_COST_GS) {
            $score -= 25;
        }

        if ($stock !== null) {
            $score += $stock >= 5 ? 6 : -10;
        }

        foreach (['mini', 'repuesto', 'adaptador', 'cable', 'cabo', 'funda', 'capa', 'case', 'cover', 'alfombra', 'alcaparra', 'sachet', 'agrupado', 'antena', 'lnb', 'fuente', 'bateria', 'mount', 'clamp', 'remote', 'dock', 'bastao', 'backoor', 'usb power', 'charger', 'alarma', 'nunchuk', 'wiiu', 'control p', 'headset ear', 'protector', 'floaty'] as $keyword) {
            if (str_contains($name, $keyword)) {
                $score -= 14;
            }
        }

        foreach (['combo', 'kit', '1l', '750ml', 'whisky', 'vodka', 'perfume', 'termo', 'smart', 'jbl'] as $keyword) {
            if (str_contains($name, $keyword)) {
                $score += 5;
            }
        }

        return max(0, min(100, $score));
    }

    private function perceivedValueScore(string $productName, string $category, int $costGs): float
    {
        $name = $this->normalizeText($productName);
        $scoreByCategory = [
            'Bebidas' => 76,
            'Perfumeria' => 78,
            'Electronica' => 58,
            'Hogar' => 72,
            'Belleza' => 70,
            'Termos' => 68,
            'Asado' => 68,
            'Cosmeticos Salud E Hig.' => 62,
            'Camping/Pesca/Ferr/Jard/Auto' => 48,
            'Comestibles' => 34,
            'Otros' => 45,
        ];

        $score = $scoreByCategory[$category] ?? 45;

        if ($this->isAccessoryLikeProduct($name)) {
            $score -= 45;
        }

        foreach ($this->highPerceivedKeywords() as $keyword) {
            if (str_contains($name, $keyword)) {
                $score += 18;
                break;
            }
        }

        foreach ($this->mediumPerceivedKeywords() as $keyword) {
            if (str_contains($name, $keyword)) {
                $score += 8;
                break;
            }
        }

        foreach ($this->lowPerceivedKeywords() as $keyword) {
            if (str_contains($name, $keyword)) {
                $score -= 28;
                break;
            }
        }

        if ($this->looksLikeTechnicalAccessory($name)) {
            $score -= 22;
        }

        if ($costGs >= 120000 && $costGs <= 700000) {
            $score += 8;
        } elseif ($costGs >= self::MIN_PRIZE_COST_GS && $costGs < 120000) {
            $score += 2;
        } elseif ($costGs < self::MIN_PRIZE_COST_GS) {
            $score -= 22;
        } elseif ($costGs > 1500000) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    private function perceivedValueLevel(float $score): string
    {
        if ($score >= 78) {
            return 'alto';
        }

        if ($score >= self::MIN_PERCEIVED_VALUE_SCORE) {
            return 'medio';
        }

        return 'bajo';
    }

    private function highPerceivedKeywords(): array
    {
        return [
            'whisky', 'vodka', 'vino', 'champagne', 'gin', 'ron', 'tequila', 'absolut', 'johnnie', 'jack', 'chivas',
            'ballantines', 'black label', 'red label', 'perfume', 'fragancia', 'colonia', 'smart tv', 'iphone',
            'samsung', 'jbl', 'parlante', 'auricular', 'xbox', 'ps4', 'ps5', 'console', 'consola',
            'licuadora', 'cafetera', 'freidora', 'olla', 'termo', 'combo', 'kit',
        ];
    }

    private function mediumPerceivedKeywords(): array
    {
        return [
            '750ml', '1l', 'set', 'pack', 'speaker', 'shaver', 'afeitadora', 'secador', 'planchita', 'hover',
            'power', 'digital',
        ];
    }

    private function lowPerceivedKeywords(): array
    {
        return [
            'alfajor', 'galleta', 'alcaparra', 'aceite oliva', 'sachet', 'mini', 'plato', 'bowl', 'taza', 'vaso',
            'repuesto', 'flex', 'modulo', 'display', 'pantalla', 'placa', 'carcasa', 'funda', 'cable', 'cabo',
            'adaptador', 'tornillo', 'antena', 'lnb', 'fuente', 'bateria', 'mount', 'clamp', 'remote', 'dock',
            'protector', 'backoor', 'bastao', 'usb power', 'charger', 'alarma', 'nunchuk', 'wiiu',
            'control p', 'headset ear', 'dualshock', 'floaty', 'gopro', 'capa', 'case', 'cover',
        ];
    }

    private function looksLikeTechnicalAccessory(string $normalizedName): bool
    {
        return $this->isAccessoryLikeProduct($normalizedName);
    }

    private function isAccessoryLikeProduct(string $normalizedName): bool
    {
        if (str_starts_with($normalizedName, 'acc ') || str_starts_with($normalizedName, 'ace ')) {
            return true;
        }

        foreach (['control p', 'dualshock', 'headset ear', 'usb power', 'charger', 'protector', 'floaty', 'mount', 'clamp', 'remote', 'dock', 'bateria', 'fuente', 'cable', 'cabo', 'adaptador', 'antena', 'lnb', 'capa', 'case', 'cover'] as $keyword) {
            if (str_contains($normalizedName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function planAttractionScore(array $prizes): float
    {
        $scores = collect($prizes)->map(fn ($prize) => $this->attractionScore($prize['name'], $prize['category']));

        if ($scores->count() === 1) {
            return (float) $scores->first();
        }

        return (float) round(($scores->first() * 0.55) + ($scores->slice(1)->avg() * 0.45), 2);
    }

    private function planSaleabilityScore(array $prizes, int $price, int $numbers): float
    {
        $scores = collect($prizes)->map(fn ($prize) => $this->saleabilityScore($prize['name'], $prize['category'], $prize['cost_gs'], $prize['stock']));
        $priceScore = $this->priceFitScore($price);
        $numbersScore = $numbers <= 120 ? 92 : max(55, 92 - (($numbers - 120) * 0.8));

        return (float) round(($scores->avg() * 0.52) + ($priceScore * 0.32) + ($numbersScore * 0.16), 2);
    }

    private function planPerceivedValueScore(array $prizes): float
    {
        $scores = collect($prizes)->map(fn ($prize) => (float) ($prize['perceived_value_score'] ?? $this->perceivedValueScore($prize['name'], $prize['category'], $prize['cost_gs'])));

        if ($scores->count() === 1) {
            return (float) $scores->first();
        }

        return (float) round(($scores->first() * 0.62) + ($scores->slice(1)->avg() * 0.38), 2);
    }

    private function priceForPlan(int $targetRevenue, int $numbers, string $type, float $perceivedValueScore, float $attractionScore, int $totalCost, int $prizesCount): int
    {
        $rawPrice = $targetRevenue / max(1, $numbers);
        $price = (int) (ceil($rawPrice / 1000) * 1000);
        $maxAllowed = self::STANDARD_MAX_PRICE_PER_NUMBER;

        if ($type === 'grande' && $prizesCount >= 6 && $totalCost >= 1800000 && $perceivedValueScore >= 90 && $attractionScore >= 85) {
            $maxAllowed = self::MAX_PRICE_PER_NUMBER;
        }

        $commercialFloor = $type === 'relampago' ? self::MIN_PRICE_PER_NUMBER : self::IDEAL_MIN_PRICE;
        if ($perceivedValueScore >= 85 || $attractionScore >= 85) {
            $commercialFloor = max($commercialFloor, $type === 'relampago' ? 6000 : 8000);
        }

        if ($perceivedValueScore >= 95) {
            $commercialFloor = max($commercialFloor, $type === 'relampago' ? 7000 : 9000);
        }

        return max(self::MIN_PRICE_PER_NUMBER, min($maxAllowed, max($commercialFloor, $price)));
    }

    private function planMarketingMixScore(array $prizes): float
    {
        $categories = collect($prizes)->pluck('category')->unique()->count();
        $types = collect($prizes)->pluck('marketing_type')->unique()->count();
        $mainScore = (float) ($prizes[0]['perceived_value_score'] ?? 0);
        $secondaryAvg = (float) collect($prizes)->slice(1)->avg('perceived_value_score');

        $score = 30;
        $score += min(30, $categories * 8);
        $score += min(25, $types * 5);
        $score += $mainScore >= 82 ? 10 : 0;
        $score += $secondaryAvg >= 72 ? 5 : 0;

        if ($categories < 3 || $types < 5) {
            $score -= 25;
        }

        return max(0, min(100, $score));
    }

    private function productRotationScore(string $productName, string $category, string $type, array $rotation): float
    {
        $productHits = (int) ($rotation['products'][$this->rotationProductKey($productName)] ?? 0);
        $categoryHits = (int) ($rotation['categories'][$category] ?? 0);
        $typeHits = (int) ($rotation['types'][$type] ?? 0);

        $score = 100;
        $score -= min(55, $productHits * 18);
        $score -= min(24, $typeHits * 4);
        $score -= min(18, $categoryHits * 2);

        return max(10, min(100, $score));
    }

    private function planRotationScore(array $prizes, string $type, array $rotation): float
    {
        $scores = collect($prizes)->map(function ($prize, $index) use ($rotation) {
            $score = $this->productRotationScore(
                (string) $prize['name'],
                (string) $prize['category'],
                (string) ($prize['marketing_type'] ?? $this->marketingType($prize['name'], $prize['category'])),
                $rotation
            );

            return $index === 0 ? $score * 1.35 : $score;
        });

        $base = (float) ($scores->sum() / max(1, $scores->count() + 0.35));

        if ($type === 'grande') {
            $signatureHits = (int) ($rotation['combos'][$this->comboSignature($prizes)] ?? 0);
            $base -= min(35, $signatureHits * 18);
        }

        return max(10, min(100, round($base, 2)));
    }

    private function rotationSummary(array $prizes, array $rotation): array
    {
        return [
            'repeated_products' => collect($prizes)
                ->filter(fn ($prize) => ($rotation['products'][$this->rotationProductKey((string) $prize['name'])] ?? 0) > 0)
                ->map(fn ($prize) => $prize['name'])
                ->values()
                ->all(),
            'combo_signature_hits' => (int) ($rotation['combos'][$this->comboSignature($prizes)] ?? 0),
            'categories' => collect($prizes)->pluck('category')->unique()->values()->all(),
            'marketing_types' => collect($prizes)->pluck('marketing_type')->unique()->values()->all(),
        ];
    }

    private function comboSignature(array $prizes): string
    {
        return collect($prizes)
            ->pluck('marketing_type')
            ->filter()
            ->sort()
            ->implode('|');
    }

    private function rotationProductKey(string $name): string
    {
        return implode(' ', array_slice($this->tokens($name), 0, 6));
    }

    private function comboName(array $prizes): string
    {
        $name = collect($prizes)
            ->map(fn ($prize) => $this->shortPrizeName((string) $prize['name']))
            ->take(5)
            ->implode(' + ');

        return 'Combo ' . $name;
    }

    private function urgencyMessages(string $displayName, string $type, array $prizes, int $numbers, int $price): array
    {
        $main = $this->shortPrizeName((string) ($prizes[0]['name'] ?? $displayName));
        $priceText = $this->gs($price);
        $numbersText = number_format($numbers, 0, ',', '.');

        if ($type === 'grande') {
            return [
                'Combo fuerte en juego: ' . $displayName . '.',
                'Son solo ' . $numbersText . ' numeros para este combo.',
                'Con ' . $priceText . ' ya competis por los ' . count($prizes) . ' premios.',
                'El premio principal es ' . $main . ', y viene con extras.',
                'Si estabas esperando un combo completo, este es el momento.',
                'Pocos sorteos mezclan tantos premios vendibles en una sola jugada.',
                'Elegí tu número antes de que se ocupen los mejores.',
                'Combo de alto valor, precio accesible y cupos limitados.',
            ];
        }

        return [
            'Flash activo: ' . $main . ' puede salir rapido.',
            'Solo ' . $numbersText . ' numeros disponibles.',
            'Por ' . $priceText . ' participas por un premio directo.',
            'Sorteo corto, premio deseado y venta rapida.',
            'No lo dejes para despues: los flash se llenan primero.',
            'Elegí tu número mientras todavía hay lugares libres.',
            'Premio simple, claro y fácil de ganar.',
            'Ideal para entrar rapido sin gastar de más.',
        ];
    }

    private function shortPrizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?: $name);
        $name = preg_replace('/[-\/]?\d{6,}$/', '', $name) ?: $name;
        $name = preg_replace('/\s+/', ' ', $name) ?: $name;
        $normalized = $this->normalizeText($name);

        if (str_contains($normalized, 'almaviva')) {
            return 'Vino Almaviva';
        }

        if (str_contains($normalized, 'abercrombie')) {
            return 'Perfume Abercrombie';
        }

        if (str_contains($normalized, 'a banderas')) {
            return 'Perfume A.Banderas';
        }

        if (str_contains($normalized, 'absolut')) {
            return 'Vodka Absolut';
        }

        if (str_contains($normalized, 'alfaparf')) {
            return 'Kit Alfaparf';
        }

        if (str_contains($normalized, 'album digital')) {
            return 'Album Digital';
        }

        if (str_contains($normalized, 'carretilla')) {
            return 'Carretilla Sumax';
        }

        return Str::limit(trim($name), 24, '');
    }

    private function marketingType(string $productName, string $category): string
    {
        $name = $this->normalizeText($productName);

        $map = [
            'whisky' => ['whisky', 'johnnie', 'jack', 'chivas', 'ballantines', 'black label', 'red label'],
            'vodka' => ['vodka', 'absolut'],
            'vino' => ['vino', 'vinho', 'cabernet', 'malbec', 'pinot', 'sauvignon'],
            'espumante' => ['champagne', 'espumante', 'brut'],
            'perfume' => ['perfume', 'fragancia', 'colonia', 'edt', 'edp'],
            'electronica' => ['smart tv', 'tv', 'parlante', 'jbl', 'speaker', 'iphone', 'samsung'],
            'hogar' => ['licuadora', 'cafetera', 'freidora', 'olla', 'cocina'],
            'termo' => ['termo', 'guampa', 'mate'],
            'herramienta' => ['taladro', 'herramienta', 'kit herramientas'],
            'belleza' => ['secador', 'planchita', 'shaver', 'afeitadora'],
        ];

        foreach ($map as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $type;
                }
            }
        }

        return $this->normalizeKey($category);
    }

    private function priceFitScore(int $price): float
    {
        if ($price >= self::IDEAL_MIN_PRICE && $price <= self::IDEAL_MAX_PRICE) {
            return 100 - (abs($price - 10000) / 100);
        }

        if ($price >= self::MIN_PRICE_PER_NUMBER && $price < self::IDEAL_MIN_PRICE) {
            return max(45, 70 - ((self::IDEAL_MIN_PRICE - $price) / 80));
        }

        return max(20, 70 - (abs($price - self::IDEAL_MAX_PRICE) / 180));
    }

    private function typeFitScore(int $costGs, string $type, int $prizesCount = 1): int
    {
        if ($type === 'relampago') {
            return $costGs < 120000 ? 100 : 0;
        }

        if ($prizesCount >= 5 && $costGs >= 250000 && $costGs <= 1500000) {
            return 100;
        }

        if ($costGs >= 300000) {
            return 86;
        }

        if ($costGs >= 120000) {
            return 72;
        }

        return 35;
    }

    private function riskLevel(float $score, float $margin, int $price): string
    {
        if ($score >= 78 && $margin >= 0.35 && $price <= 15000) {
            return 'bajo';
        }

        if ($score >= 62 && $margin >= 0.25 && $price <= self::MAX_PRICE_PER_NUMBER) {
            return 'medio';
        }

        return 'alto';
    }

    private function inferCategory(string $text): string
    {
        $normalized = $this->normalizeText($text);

        $map = [
            'Bebidas' => ['whisky', 'vino', 'cerveza', 'champagne', 'hoppy', 'bebida'],
            'Electronica' => ['tv', 'smart', 'jbl', 'parlante', 'auricular', 'celular', 'iphone', 'samsung', 'tablet', 'notebook'],
            'Perfumeria' => ['perfume', 'colonia', 'fragancia', 'salvang'],
            'Belleza' => ['secador', 'shaver', 'planchita', 'afeitadora'],
            'Termos' => ['termo', 'guampa', 'mate', 'bombilla', 'rodax'],
            'Asado' => ['cuchillo', 'asado', 'parrilla', 'tenedor'],
            'Hogar' => ['licuadora', 'cocina', 'olla', 'freidora', 'cafetera'],
            'Repuestos' => ['repuesto', 'flex', 'modulo', 'placa', 'display', 'pantalla'],
        ];

        foreach ($map as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $category;
                }
            }
        }

        return 'Otros';
    }

    private function humanCategory(string $category): string
    {
        return Str::title(trim($category)) ?: 'Otros';
    }

    private function similarity(string $a, string $b): float
    {
        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);

        if (!$tokensA || !$tokensB) {
            return 0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        return $union > 0 ? $intersection / $union : 0;
    }

    private function tokens(string $text): array
    {
        $words = preg_split('/\s+/', $this->normalizeText($text)) ?: [];
        return array_values(array_filter(array_unique($words), fn ($word) => strlen($word) >= 3));
    }

    private function firstValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeKey($key);
            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return '';
    }

    private function normalizeKey(string $key): string
    {
        return str_replace([' ', '-', '.'], '_', $this->normalizeText($key));
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?: '';
        return trim($text);
    }

    private function stripBom(string $text): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $text) ?: $text;
    }

    private function mapyCostToGs(float $costBase, int $usdRate): int
    {
        // MAPY puede traer COSTO_PROMEDIO ya en Gs en este reporte. Los valores USD reales
        // suelen venir como decimales bajos; estos si se convierten por la cotizacion fija.
        if ($costBase >= 1000) {
            return (int) round($costBase);
        }

        return (int) round($costBase * $usdRate);
    }

    private function parseMoney(string $value): float
    {
        $value = trim(str_replace(['$', 'USD', 'usd', 'Gs', 'gs', ' '], '', $value));

        if ($value === '') {
            return 0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } elseif (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function parseNullableInt(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $number = preg_replace('/[^0-9]/', '', $value);
        return $number === '' ? null : (int) $number;
    }

    private function gs(int|float $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' Gs';
    }

}
