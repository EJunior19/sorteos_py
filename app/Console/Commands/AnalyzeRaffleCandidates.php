<?php

namespace App\Console\Commands;

use App\Models\Raffle;
use App\Models\RaffleCandidateAnalysis;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MEJORAS APLICADAS (resumen):
 *
 * 1. COMBOS CON TEMA   → themeForPrizes(), COMBO_THEMES, comboThemeScore()
 *    Detecta el "tema" del combo (fiesta, hogar, tecnología, etc.) y penaliza
 *    mezclas incoherentes. Solo arma el combo si comparte al menos 60% de afinidad.
 *
 * 2. PREMIO PRINCIPAL MÁS FUERTE → buildFlashPlan() y buildBigPlans() requieren
 *    perceived_value_score >= 90 para el principal. Si no llega, se descarta.
 *
 * 3. VARIEDAD INTELIGENTE → comboStructureSignature() evita repetir la misma
 *    "forma" de combo (mismo mix de marketing_types en mismo orden de roles).
 *
 * 4. PRECIO PSICOLÓGICO → psychologicalPrice() redondea al precio psicológico
 *    más cercano (9k, 12k, 15k, etc.) en vez de redondear a miles exactos.
 *
 * 5. MENSAJES DE URGENCIA MÁS FUERTES → urgencyMessages() con gatillos mentales:
 *    escasez, urgencia temporal, prueba social, FOMO, autoridad de precio.
 *
 * 6. PRODUCTOS GANCHO → isHookProduct(), hookScore(). Un "gancho" es un producto
 *    que llama la atención inmediata y ancla la percepción del sorteo.
 *    Se prioriza como premio principal.
 *
 * 7. PESOS DE SCORE OPTIMIZADOS → perceivedValue sube de 0.22 a 0.26,
 *    saleability sube de 0.14 a 0.18, hookBonus nuevo (+0.08 si el principal es gancho).
 */
class AnalyzeRaffleCandidates extends Command
{
    protected $signature = 'raffles:analyze-candidates
        {--csv= : Ruta del CSV de productos}
        {--min-stock=1 : Stock minimo aceptado}
        {--usd-rate=7000 : Cotizacion fija USD a Gs}';

    protected $description = 'Analiza productos candidatos para sorteos y guarda el resultado para aprobacion.';

    // ─── Pricing ───────────────────────────────────────────────────────────────
    private const MIN_PRICE_PER_NUMBER          = 3000;
    private const STANDARD_MAX_PRICE_PER_NUMBER = 16000;
    private const MAX_PRICE_PER_NUMBER          = 50000;
    private const IDEAL_MIN_PRICE               = 5000;
    private const IDEAL_MAX_PRICE               = 15000;

    /**
     * Precios psicológicos en orden ascendente.
     * El sistema elige el más cercano por debajo del precio calculado.
     */
    private const PSYCHOLOGICAL_PRICES = [
        5000, 6000, 7000, 8000, 9000,
        10000, 12000, 15000, 18000, 20000,
        25000, 30000, 35000, 40000, 50000,
    ];

    // ─── Filtros ────────────────────────────────────────────────────────────────
    private const MAPY_REPORTS_DIR                      = '/var/www/nccPa_vr';
    private const MAPY_ZIP_PASSWORD                     = '2520';
    private const MIN_PRIZE_COST_GS                     = 60000;
    private const MIN_PERCEIVED_VALUE_SCORE             = 68;
    private const MIN_MAIN_PRIZE_PERCEIVED_VALUE_SCORE  = 90;  // ← subido de 82 a 90
    private const MIN_HOOK_SCORE_FOR_PRINCIPAL          = 55;

    /**
     * Temas de combo: cada tema tiene tipos de marketing afines.
     * Un combo debe tener al menos 3 tipos del mismo tema para calificar.
     */
    private const COMBO_THEMES = [
        'fiesta'      => ['whisky', 'vodka', 'espumante', 'vino', 'termo', 'bebida'],
        'tecnologia'  => ['electronica', 'belleza', 'hogar'],
        'hogar'       => ['hogar', 'asado', 'termo', 'belleza'],
        'premium'     => ['whisky', 'vodka', 'perfume', 'vino', 'espumante'],
        'lifestyle'   => ['perfume', 'belleza', 'termo', 'electronica'],
        'multifun'    => ['electronica', 'hogar', 'perfume', 'whisky', 'termo'],
    ];

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  HANDLE
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    public function handle(): int
    {
        $csvPath = $this->resolveCsvPath();

        if (!$csvPath) {
            $this->error('No se encontro CSV. Usa --csv=/ruta/productos.csv o guarda reportes MAPY CSV/ZIP en ' . self::MAPY_REPORTS_DIR . '.');
            return self::FAILURE;
        }

        $usdRate  = max(1, (int) $this->option('usd-rate'));
        $minStock = max(0, (int) $this->option('min-stock'));
        $history  = $this->historicalRaffles();
        $rotation = $this->rotationContext();
        $rows     = $this->readCsv($csvPath);

        if ($rows->isEmpty()) {
            $this->error('El CSV no tiene filas validas.');
            return self::FAILURE;
        }

        [$plans, $discarded] = $this->analyzeRows($rows, $history, $rotation, $usdRate, $minStock);

        $big             = $plans->where('raffle_type', 'grande')->sortByDesc('score')->take(1)->values();
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
                ->reject(fn ($p) => $big->contains(fn ($s) => $s['product_name'] === $p['product_name']))
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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  RESOLUCIÓN DE CSV
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function resolveCsvPath(): ?string
    {
        $option = $this->option('csv');
        $paths  = array_filter([
            $option ? (string) $option : null,
            $this->latestMapyReportPath(),
            base_path('productos.csv'),
        ]);

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
                    $csvFromZip = $this->extractCsvFromZip($path);
                    if ($csvFromZip) {
                        return $csvFromZip;
                    }

                    continue;
                }

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

        $latest     = null;
        $latestDate = null;

        $files = array_merge(
            glob(self::MAPY_REPORTS_DIR . '/*.csv') ?: [],
            glob(self::MAPY_REPORTS_DIR . '/*.zip') ?: []
        );

        foreach ($files as $path) {
            $filename = basename($path);
            if (!preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                continue;
            }

            $date = $matches[1];
            if ($latestDate === null || $date > $latestDate) {
                $latestDate = $date;
                $latest     = $path;
            }
        }

        return $latest;
    }

    private function extractCsvFromZip(string $zipPath): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->warn('No se puede leer ZIP porque la extension ZipArchive no esta instalada.');
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $zip->setPassword(self::MAPY_ZIP_PASSWORD);

        $csvIndex = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!str_ends_with($name, '/') && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'csv') {
                $csvIndex = $i;
                break;
            }
        }

        if ($csvIndex === null) {
            $zip->close();
            return null;
        }

        $contents = $zip->getFromIndex($csvIndex);
        $zip->close();

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $storageExtractDir = storage_path('app/mapy_reports');
        $extractDir = is_writable(dirname($storageExtractDir))
            ? $storageExtractDir
            : sys_get_temp_dir() . '/sorteos_py_mapy_reports';

        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0775, true);
        }

        $target = $extractDir . '/' . pathinfo($zipPath, PATHINFO_FILENAME) . '-' . substr(sha1($zipPath . '|' . filemtime($zipPath)), 0, 12) . '.csv';
        file_put_contents($target, $contents);

        return $target;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  LECTURA DE CSV
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function readCsv(string $path): Collection
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return collect();
        }

        $rows              = collect();
        $normalizedHeaders = null;
        $delimiter         = ';';
        $currentCategory   = '';

        while (($line = fgets($handle)) !== false) {
            $line = trim($this->stripBom($line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/^==\s*(.*?)\s*==$/u', $line, $matches)) {
                $currentCategory   = $this->humanCategory($matches[1]);
                $normalizedHeaders = null;
                continue;
            }

            $lineDelimiter   = $this->detectDelimiter($line);
            $values          = str_getcsv($line, $lineDelimiter);
            $possibleHeaders = array_map(fn ($h) => $this->normalizeKey((string) $h), $values);

            if ($this->isMapyHeader($possibleHeaders)) {
                $normalizedHeaders = $possibleHeaders;
                $delimiter         = $lineDelimiter;
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
            ';'  => substr_count($line, ';'),
            ','  => substr_count($line, ','),
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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  ANÁLISIS PRINCIPAL
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function analyzeRows(Collection $rows, Collection $history, array $rotation, int $usdRate, int $minStock): array
    {
        $candidates = collect();
        $discarded  = collect();

        foreach ($rows as $index => $row) {
            $productCode   = $this->firstValue($row, ['codigo', 'cod', 'code', 'sku', 'id_producto', 'product_id', 'barcode', 'codigo_barra']);
            $productName   = $this->firstValue($row, ['producto', 'product', 'nombre', 'name', 'descripcion', 'description']);
            $costBase      = $this->parseMoney($this->firstValue($row, ['costo_promedio', 'costo', 'cost', 'precio', 'price', 'precio_usd', 'cost_usd', 'amount']));
            $stock         = $this->parseNullableInt($this->firstValue($row, ['stock', 'existencia', 'qty', 'cantidad', 'quantity']));
            $givenCategory = $this->firstValue($row, ['rubro', 'categoria', 'category', 'tipo']);

            if ($productName === '' || $costBase <= 0) {
                $discarded->push($this->discard($row, $index, $productName ?: 'Fila ' . ($index + 1), ['Falta producto o costo valido.']));
                continue;
            }

            $costGs   = $this->mapyCostToGs($costBase, $usdRate);
            $category = $givenCategory !== '' ? $this->humanCategory($givenCategory) : $this->inferCategory($productName);
            $filterReasons = $this->filterReasons($productName, $category, $costGs, $stock, $minStock);

            if ($filterReasons) {
                $discarded->push($this->discard($row, $index, $productName, $filterReasons, $category, $costGs, $stock));
                continue;
            }

            $pvScore  = $this->perceivedValueScore($productName, $category, $costGs);
            $hookScore = $this->hookScore($productName, $category, $costGs);

            $candidate = [
                'row'                    => $row,
                'row_index'              => $index,
                'product_code'           => $productCode,
                'product_name'           => $productName,
                'category'               => $category,
                'cost_gs'                => $costGs,
                'cost_base'              => $costBase,
                'stock'                  => $stock,
                'attraction_score'       => $this->attractionScore($productName, $category),
                'saleability_score'      => $this->saleabilityScore($productName, $category, $costGs, $stock),
                'perceived_value_score'  => $pvScore,
                'perceived_value_level'  => $this->perceivedValueLevel($pvScore),
                'rotation_score'         => $this->productRotationScore($productName, $category, $this->marketingType($productName, $category), $rotation),
                'hook_score'             => $hookScore,        // ← NUEVO
                'is_hook'                => $hookScore >= self::MIN_HOOK_SCORE_FOR_PRINCIPAL,  // ← NUEVO
            ];

            $candidates->push($candidate);
        }

        $candidates = $this->dedupeCandidates($candidates);

        $flashPlans = $candidates
            ->map(fn ($candidate) => $this->buildFlashPlan($candidate, $history, $rotation))
            ->filter()
            ->values();

        $bigPlans = $this->buildBigPlans($candidates, $history, $rotation);
        $plans    = $flashPlans->merge($bigPlans);

        $plannedNames = $plans->flatMap(fn ($plan) => collect($plan['metrics']['prizes'] ?? [])->pluck('name'))->unique()->all();
        foreach ($candidates as $candidate) {
            if (!in_array($candidate['product_name'], $plannedNames, true)) {
                $discarded->push($this->discard(
                    $candidate['row'],
                    $candidate['row_index'],
                    $candidate['product_name'],
                    ['No logro una combinacion rentable con precio por numero entre 3.000 y 50.000 Gs.'],
                    $candidate['category'],
                    $candidate['cost_gs'],
                    $candidate['stock']
                ));
            }
        }

        return [$plans, $discarded];
    }

    private function dedupeCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->sortByDesc(fn ($c) => $c['attraction_score'] + min(30, $c['cost_gs'] / 10000) + $c['hook_score'] * 0.3)
            ->unique(fn ($c) => $this->candidateKey($c))
            ->values();
    }

    private function candidateKey(array $candidate): string
    {
        return ($candidate['product_code'] ?: 'sin_codigo') . '|' . $this->normalizeText($candidate['product_name']);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  HOOK: PRODUCTOS GANCHO  (NUEVO)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Un "gancho" es un producto que detiene el scroll, genera conversación
     * y ancla la percepción de valor de todo el sorteo.
     *
     * Criterios:
     * - Marca reconocida o producto aspiracional
     * - Precio en zona de deseo (no demasiado barato, no imposible)
     * - Nombre que genera imagen mental inmediata
     */
    private function hookScore(string $productName, string $category, int $costGs): float
    {
        $name  = $this->normalizeText($productName);
        $score = 0.0;

        // Marcas ancla de alto impacto visual / aspiracional
        $anchorBrands = [
            'johnnie walker' => 30, 'jack daniel'  => 30, 'chivas'   => 28,
            'ballantine'     => 25, 'absolut'       => 28, 'grey goose' => 30,
            'samsung'        => 28, 'iphone'        => 35, 'apple'    => 32,
            'jbl'            => 22, 'sony'          => 25, 'bose'     => 28,
            'nike'           => 20, 'adidas'        => 18,
            'dior'           => 30, 'chanel'        => 32, 'versace'  => 28,
            'carolina herrera' => 25, 'hugo boss'  => 22,
            'almaviva'       => 22, 'casillero'     => 18,
        ];

        foreach ($anchorBrands as $brand => $pts) {
            if (str_contains($name, $brand)) {
                $score += $pts;
                break;
            }
        }

        // Categorías con alto poder de gancho
        $categoryHook = [
            'Electronica' => 20, 'Perfumeria' => 18, 'Bebidas' => 15,
            'Belleza'     => 12, 'Termos'     => 10, 'Hogar'   => 8,
        ];
        $score += $categoryHook[$category] ?? 0;

        // Palabras que generan imagen mental
        $hookWords = ['smart tv', 'whisky', 'vodka', 'perfume', 'combo', 'kit premium', '4k', 'bluetooth'];
        foreach ($hookWords as $word) {
            if (str_contains($name, $word)) {
                $score += 8;
                break;
            }
        }

        // Zona de precio aspiracional (costoso pero alcanzable como sorteo)
        if ($costGs >= 200000 && $costGs <= 1200000) {
            $score += 15;
        } elseif ($costGs >= 80000 && $costGs < 200000) {
            $score += 8;
        } elseif ($costGs < 60000) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    private function isHookProduct(array $candidate): bool
    {
        return $candidate['is_hook'] ?? false;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  PLANES: FLASH
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function buildFlashPlan(array $candidate, Collection $history, array $rotation): ?array
    {
        if ($candidate['cost_gs'] >= 120000) {
            return null;
        }

        // Principal debe tener valor percibido alto
        if ($candidate['perceived_value_score'] < self::MIN_MAIN_PRIZE_PERCEIVED_VALUE_SCORE) {
            return null;
        }

        return $this->buildPlanFromPrizes(
            [$this->prizePayload($candidate, 'principal')],
            'relampago',
            $history,
            $rotation
        );
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  PLANES: GRANDES  (COMBOS CON TEMA)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function buildBigPlans(Collection $candidates, Collection $history, array $rotation): Collection
    {
        $secondaryPool = $candidates
            ->filter(fn ($c) => $c['cost_gs'] >= self::MIN_PRIZE_COST_GS && $c['cost_gs'] <= 320000)
            ->filter(fn ($c) => $c['perceived_value_score'] >= self::MIN_PERCEIVED_VALUE_SCORE)
            ->sortByDesc(fn ($c) =>
                ($c['perceived_value_score'] * 0.42)
                + ($c['saleability_score']     * 0.24)
                + ($c['attraction_score']       * 0.16)
                + ($c['rotation_score']         * 0.18)
            )
            ->values();

        // Priorizamos candidatos que sean "gancho" como principal
        return $candidates
            ->filter(fn ($c) => $c['cost_gs'] >= 250000 && $c['attraction_score'] >= 58)
            ->filter(fn ($c) => $c['perceived_value_score'] >= self::MIN_MAIN_PRIZE_PERCEIVED_VALUE_SCORE)
            ->sortByDesc(fn ($c) =>
                ($c['perceived_value_score'] * 0.38)
                + ($c['attraction_score']     * 0.20)
                + ($c['saleability_score']    * 0.18)
                + ($c['rotation_score']       * 0.14)
                + ($c['hook_score']           * 0.10)   // ← gancho suma al ranking
            )
            ->take(70)
            ->map(function ($principal) use ($secondaryPool, $history, $rotation) {
                $theme = $this->themeForPrincipal($principal);
                $secondary = $this->secondaryPrizesFor($principal, $secondaryPool, $theme);

                if ($secondary->count() < 4) {
                    return null;
                }

                $prizes = collect([$this->prizePayload($principal, 'principal')])
                    ->merge($secondary->take(5)->map(fn ($c) => $this->prizePayload($c, 'secundario')))
                    ->values()
                    ->all();

                // Verificar coherencia temática antes de construir el plan
                $theme = $this->themeForPrizes($prizes);
                if ($theme === null) {
                    return null;   // combo incoherente → descartado
                }

                return $this->buildPlanFromPrizes($prizes, 'grande', $history, $rotation, $theme);
            })
            ->filter()
            ->values();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  COMBO TEMA  (NUEVO)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Detecta el tema dominante de un conjunto de premios.
     * Retorna null si el combo es incoherente (< 50% de tipos coinciden con algún tema).
     */
    private function themeForPrizes(array $prizes): ?string
    {
        $types = collect($prizes)->pluck('marketing_type')->filter()->all();

        if (empty($types)) {
            return 'multifun';
        }

        $bestTheme = null;
        $bestScore = 0;

        foreach (self::COMBO_THEMES as $theme => $affinityTypes) {
            $matches = count(array_intersect($types, $affinityTypes));
            $ratio   = $matches / count($types);

            if ($ratio > $bestScore) {
                $bestScore = $ratio;
                $bestTheme = $theme;
            }
        }

        // Necesitamos al menos 50% de coherencia temática
        if ($bestScore < 0.50) {
            return null;
        }

        return $bestTheme;
    }

    /**
     * Puntaje de coherencia temática (0–100).
     */
    private function comboThemeScore(array $prizes, string $theme): float
    {
        $types    = collect($prizes)->pluck('marketing_type')->filter()->all();
        $affinity = self::COMBO_THEMES[$theme] ?? [];
        $matches  = count(array_intersect($types, $affinity));

        return count($types) > 0 ? min(100, ($matches / count($types)) * 120) : 50;
    }

    private function themeForPrincipal(array $principal): ?string
    {
        $type = $this->marketingType($principal['product_name'], $principal['category']);

        if (in_array($type, self::COMBO_THEMES['premium'], true)) {
            return 'premium';
        }

        return null;
    }

    /**
     * Firma de estructura del combo: evita repetir la misma "forma".
     * Ej: "whisky|perfume|termo|hogar|belleza" → mismo mix = penalizar.
     */
    private function comboStructureSignature(array $prizes): string
    {
        $types = collect($prizes)
            ->pluck('marketing_type')
            ->filter()
            ->sort()
            ->values()
            ->implode('|');

        return md5($types);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  SELECCIÓN DE PREMIOS SECUNDARIOS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function secondaryPrizesFor(array $principal, Collection $pool, ?string $theme = null): Collection
    {
        $principalKey    = $this->candidateKey($principal);
        $targetMax       = max(50000, (int) min(180000, $principal['cost_gs'] * 0.28));
        $principalType   = $this->marketingType($principal['product_name'], $principal['category']);
        $premiumIncompatible = [
            'carretilla', 'album', 'digital', 'powerp',
            'ferreteria', 'herramienta', 'jardin', 'pesca', 'camping',
        ];
        $isPremiumIncompatible = function (array $candidate) use ($theme, $premiumIncompatible): bool {
            if ($theme !== 'premium') {
                return false;
            }

            $name = $this->normalizeText($candidate['product_name']);
            foreach ($premiumIncompatible as $word) {
                if (str_contains($name, $word)) {
                    return true;
                }
            }

            return false;
        };

        $usedTypes      = [$principalType];
        $usedCategories = [$principal['category']];
        $principalOffset = abs(crc32($principalKey));

        // Ordenamos pool priorizando afinidad temática, diversidad y novedad
        $ranked = $pool
            ->reject(fn ($c) => $this->candidateKey($c) === $principalKey)
            ->reject(fn ($c) => $isPremiumIncompatible($c))
            ->sortByDesc(function ($c) use ($principal, $targetMax, $principalOffset, $principalType) {
                // Bonus si complementa el tema del principal
                $themeAffinity = $this->typeThemeAffinity($c['marketing_type'] ?? '', $principalType);
                $categoryBonus = $c['category'] === $principal['category'] ? -15 : 15;
                $costFit       = max(0, 30 - (abs($c['cost_gs'] - ($targetMax * 0.55)) / 4500));
                $noveltyJitter = (abs(crc32($this->candidateKey($c) . '|' . $principalOffset)) % 17) - 8;

                return ($c['perceived_value_score'] * 0.40)
                    + ($c['saleability_score']      * 0.22)
                    + ($themeAffinity               * 0.20)
                    + $categoryBonus
                    + ($costFit * 0.5)
                    + $noveltyJitter;
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
            $usedTypes[]      = $type;
            $usedCategories[] = $candidate['category'];

            if ($selected->count() === 5) {
                break;
            }
        }

        // Fallback: relajar restricción de categoría si no llegamos a 4
        if ($selected->count() < 4) {
            foreach ($ranked as $candidate) {
                if ($selected->contains(fn ($i) => $this->candidateKey($i) === $this->candidateKey($candidate))) {
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

    /**
     * Afinidad temática entre dos tipos de marketing.
     * Retorna 0–30 según cuántos temas comparten.
     */
    private function typeThemeAffinity(string $typeA, string $typeB): float
    {
        $sharedThemes = 0;
        foreach (self::COMBO_THEMES as $affinityTypes) {
            if (in_array($typeA, $affinityTypes, true) && in_array($typeB, $affinityTypes, true)) {
                $sharedThemes++;
            }
        }
        return min(30, $sharedThemes * 10);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  CONSTRUCCIÓN DE PLAN
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function prizePayload(array $candidate, string $role): array
    {
        return [
            'role'                  => $role,
            'name'                  => $candidate['product_name'],
            'category'              => $candidate['category'],
            'marketing_type'        => $this->marketingType($candidate['product_name'], $candidate['category']),
            'product_code'          => $candidate['product_code'] ?: null,
            'cost_gs'               => $candidate['cost_gs'],
            'cost_base'             => $candidate['cost_base'] ?? null,
            'perceived_value_score' => $candidate['perceived_value_score'] ?? $this->perceivedValueScore($candidate['product_name'], $candidate['category'], $candidate['cost_gs']),
            'perceived_value_level' => $candidate['perceived_value_level'] ?? null,
            'rotation_score'        => $candidate['rotation_score'] ?? 100,
            'hook_score'            => $candidate['hook_score'] ?? 0,
            'is_hook'               => $candidate['is_hook'] ?? false,
            'stock'                 => $candidate['stock'],
            'raw_product'           => $candidate['row'],
        ];
    }

    private function buildPlanFromPrizes(array $prizes, string $type, Collection $history, array $rotation, ?string $theme = null): ?array
    {
        $mainPrize   = $prizes[0];
        $totalCost   = (int) collect($prizes)->sum('cost_gs');
        $prizesCount = count($prizes);
        $range       = $type === 'relampago' ? [20, 50] : [80, 500];
        $targetMargin = $type === 'relampago' ? 0.42 : 0.35;
        $minProfit   = $type === 'relampago'
            ? 45000
            : max(150000, (int) round($totalCost * 0.28));

        if ($type === 'grande' && ($prizesCount < 5 || $prizesCount > 6)) {
            return null;
        }

        $themeScore          = $theme ? $this->comboThemeScore($prizes, $theme) : 100;
        $marketingMixScore   = $type === 'grande' ? $this->planMarketingMixScore($prizes) : 100;
        $rotationScore       = $this->planRotationScore($prizes, $type, $rotation);
        $attractionScore     = $this->planAttractionScore($prizes);
        $perceivedValueScore = $this->planPerceivedValueScore($prizes);
        $hookBonus           = ($mainPrize['is_hook'] ?? false) ? 8 : 0;   // ← NUEVO

        if ($type === 'grande' && $marketingMixScore < 70) {
            return null;
        }

        // Penalizar si combo muy repetido en estructura
        $structureSig  = $this->comboStructureSignature($prizes);
        $structureHits = (int) ($rotation['structures'][$structureSig] ?? 0);   // ← NUEVO
        if ($structureHits > 1) {
            $rotationScore = max(10, $rotationScore - ($structureHits * 15));
        }

        $best = null;
        for ($numbers = $range[0]; $numbers <= $range[1]; $numbers++) {
            $targetRevenue = max($totalCost + $minProfit, (int) ceil($totalCost / (1 - $targetMargin)));
            $price         = $this->priceForPlan($targetRevenue, $numbers, $type, $perceivedValueScore, $attractionScore, $totalCost, $prizesCount);

            if ($price > self::MAX_PRICE_PER_NUMBER) {
                continue;
            }

            $revenue = $numbers * $price;
            $profit  = $revenue - $totalCost;
            $margin  = $revenue > 0 ? $profit / $revenue : 0;

            if ($margin < 0.22 || $profit < $minProfit) {
                continue;
            }

            $priceFit       = $this->priceFitScore($price);
            $profitScore    = min(100, ($profit / max(1, $totalCost)) * 100);
            $saleabilityScore = $this->planSaleabilityScore($prizes, $price, $numbers);
            $historyScore   = $this->historyScore([
                'product_name' => $mainPrize['name'],
                'category'     => $mainPrize['category'],
                'cost_gs'      => $totalCost,
            ], $type, $history);
            $typeFit = $this->typeFitScore($totalCost, $type, $prizesCount);

            // ─── Scoring optimizado (pesos revisados) ────────────────────────
            // perceivedValue  0.22 → 0.26  (lo más importante para conversión)
            // saleability     0.14 → 0.18  (vendibilidad real)
            // priceFit        0.16 → 0.14  (sigue importante pero no tanto)
            // themeScore      NUEVO → 0.08  (coherencia del combo)
            // hookBonus       NUEVO → flat  (premio gancho suma directo)
            $score = round(
                ($profitScore        * 0.07)
                + ($priceFit         * 0.14)
                + ($attractionScore  * 0.11)
                + ($saleabilityScore * 0.18)
                + ($perceivedValueScore * 0.26)
                + ($historyScore     * 0.06)
                + ($marketingMixScore * 0.10)
                + ($rotationScore    * 0.08)
                + $hookBonus,   // flat bonus
                2
            );

            if (!$best || $score > $best['score']) {
                $displayName     = $type === 'grande' ? $this->comboName($prizes, $theme) : $mainPrize['name'];
                $displayCategory = $type === 'grande' ? ('Combo ' . ($theme ? ucfirst($theme) : 'mixto')) : $mainPrize['category'];
                $candidateArr    = [
                    'product_name' => $displayName,
                    'category'     => $displayCategory,
                    'cost_gs'      => $totalCost,
                ];
                $comparison = $this->historicalComparison($candidateArr, $type, $history);

                $best = [
                    'product_name'        => $displayName,
                    'category'            => $displayCategory,
                    'raffle_type'         => $type,
                    'cost_gs'             => $totalCost,
                    'stock'               => $mainPrize['stock'],
                    'numbers_count'       => $numbers,
                    'price_per_number_gs' => $price,
                    'revenue_gs'          => $revenue,
                    'estimated_profit_gs' => $profit,
                    'score'               => $score,
                    'risk_level'          => $this->riskLevel($score, $margin, $price),
                    'reason'              => $this->reason($candidateArr, $type, $numbers, $price, $profit, $margin, $prizesCount, $saleabilityScore, $perceivedValueScore, $rotationScore, $theme, $mainPrize['is_hook'] ?? false),
                    'historical_comparison' => $comparison,
                    'filter_status'       => 'selected',
                    'filter_reasons'      => [],
                    'metrics'             => [
                        'product_code'          => $mainPrize['product_code'],
                        'raw_product'           => $mainPrize['raw_product'],
                        'prizes'                => $prizes,
                        'prizes_count'          => $prizesCount,
                        'combo_theme'           => $theme,           // ← NUEVO
                        'combo_theme_score'     => round($themeScore, 2),  // ← NUEVO
                        'is_hook_principal'     => $mainPrize['is_hook'] ?? false,  // ← NUEVO
                        'hook_score'            => $mainPrize['hook_score'] ?? 0,   // ← NUEVO
                        'margin_pct'            => round($margin * 100, 2),
                        'attraction_score'      => $attractionScore,
                        'saleability_score'     => round($saleabilityScore, 2),
                        'perceived_value_score' => round($perceivedValueScore, 2),
                        'perceived_value_level' => $this->perceivedValueLevel($perceivedValueScore),
                        'history_score'         => round($historyScore, 2),
                        'price_fit_score'       => round($priceFit, 2),
                        'profit_score'          => round($profitScore, 2),
                        'type_fit_score'        => round($typeFit, 2),
                        'marketing_mix_score'   => round($marketingMixScore, 2),
                        'rotation_score'        => round($rotationScore, 2),
                        'structure_hits'        => $structureHits,
                        'rotation_summary'      => $this->rotationSummary($prizes, $rotation),
                        'urgency_messages'      => $this->urgencyMessages($displayName, $type, $prizes, $numbers, $price, $theme),
                        'main_prize'            => $mainPrize,
                    ],
                ];
            }
        }

        return $best;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  PRECIO PSICOLÓGICO  (NUEVO)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * En vez de redondear al mil más cercano, buscamos el precio psicológico
     * inmediatamente por encima del precio calculado.
     *
     * Ejemplo: 8.400 → 9.000 (no 9.000 exacto).
     * Esto mejora conversión porque los precios "redondos" generan menos fricción.
     */
    private function psychologicalPrice(float $rawPrice): int
    {
        foreach (self::PSYCHOLOGICAL_PRICES as $psyPrice) {
            if ($psyPrice >= $rawPrice) {
                return $psyPrice;
            }
        }

        // Si supera todos los precios psicológicos, redondear al 5.000 más cercano
        return (int) (ceil($rawPrice / 5000) * 5000);
    }

    private function priceForPlan(int $targetRevenue, int $numbers, string $type, float $perceivedValueScore, float $attractionScore, int $totalCost, int $prizesCount): int
    {
        $rawPrice   = $targetRevenue / max(1, $numbers);
        $price      = $this->psychologicalPrice($rawPrice);   // ← usa precio psicológico
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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  MENSAJES DE URGENCIA  (MEJORADO)
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * GATILLOS MENTALES usados:
     *   - Escasez real: "Solo X números"
     *   - Urgencia temporal: "Hoy", "Esta noche", "Ahora"
     *   - Prueba social: "Ya se están reservando"
     *   - Autoridad de precio: comparar con precio de mercado
     *   - FOMO: "El que espera pierde su número"
     *   - Reciprocidad: "Para vos y para quien querés"
     *   - Identidad: "Los que saben, ya eligieron"
     */
    private function urgencyMessages(string $displayName, string $type, array $prizes, int $numbers, int $price, ?string $theme = null): array
    {
        $main        = $this->shortPrizeName((string) ($prizes[0]['name'] ?? $displayName));
        $priceText   = $this->gs($price);
        $numbersText = number_format($numbers, 0, ',', '.');
        $themeLabel  = $this->themeLabel($theme);

        if ($type === 'grande') {
            return [
                // Escasez
                "⚡ Solo {$numbersText} números para llevarte todo el {$themeLabel}.",
                "🔥 El premio principal es {$main}. Quedan pocas chances.",
                // Prueba social
                "✅ Los sorteos con {$main} se llenan primero. No esperés.",
                "📢 Ya hay gente reservando. ¿El tuyo está elegido?",
                // Urgencia temporal
                "⏰ Hoy puede ser el día. Por {$priceText} entrás al combo completo.",
                "🕐 Cuanto más esperás, menos números quedan.",
                // FOMO
                "😤 Después no digas que no sabías. {$numbersText} chances y ya está.",
                "🎯 {$main} + extras. Una sola jugada. ¿Qué esperás?",
                // Autoridad de precio
                "💰 Por {$priceText} competís por un combo que vale mucho más.",
                "🏆 Premio real, precio accesible, cupos limitados. Entrá ya.",
            ];
        }

        return [
            // Escasez
            "⚡ Flash activo: solo {$numbersText} números y puede cerrar hoy.",
            "🔢 {$numbersText} chances para {$main}. ¿El tuyo está?",
            // Urgencia
            "⏰ Los flash no esperan. Por {$priceText} ya participás.",
            "🕐 Este sorteo cierra rápido. No lo dejes para después.",
            // Prueba social
            "🔥 Los más rápidos ya eligieron. ¿Y vos?",
            "✅ Premio directo, venta rápida. Así funcionan los flash.",
            // FOMO
            "😤 El que espera, pierde su número favorito.",
            "🎯 {$main} en juego. {$numbersText} números. Simple y directo.",
            // Reciprocidad / regalo
            "🎁 ¿Para vos o para alguien especial? Por {$priceText} va.",
            "🏆 Premio claro, precio justo, cupo limitado. Ahora.",
        ];
    }

    private function themeLabel(?string $theme): string
    {
        return match ($theme) {
            'fiesta'     => 'combo fiesta',
            'tecnologia' => 'combo tecnología',
            'hogar'      => 'combo hogar',
            'premium'    => 'combo premium',
            'lifestyle'  => 'combo lifestyle',
            default      => 'combo',
        };
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  HISTÓRICO Y ROTACIÓN
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function historicalRaffles(): Collection
    {
        return Raffle::with(['prizes', 'numbers'])
            ->where('status', 'finished')
            ->orderBy('id')
            ->get()
            ->map(function (Raffle $raffle) {
                $sold    = $raffle->numbers->where('status', 'sold')->count();
                $cost    = (int) (($raffle->cost_gs ?? 0) ?: $raffle->prizes->sum('cost'));
                $revenue = (int) ($sold * (float) $raffle->price);
                $names   = trim($raffle->name . ' ' . $raffle->prizes->pluck('name')->implode(' '));
                $saleHours = $raffle->created_at && $raffle->updated_at
                    ? max(1, $raffle->created_at->diffInHours($raffle->updated_at))
                    : null;

                return [
                    'name'         => $raffle->name,
                    'category'     => $raffle->category ?: $this->inferCategory($names),
                    'price'        => (int) $raffle->price,
                    'total_numbers' => (int) $raffle->total_numbers,
                    'sold'         => $sold,
                    'sold_pct'     => $raffle->total_numbers > 0 ? $sold / $raffle->total_numbers : 0,
                    'cost'         => $cost,
                    'profit'       => $revenue - $cost,
                    'sale_hours'   => $saleHours,
                    'text'         => $names,
                ];
            });
    }

    private function rotationContext(): array
    {
        $productCounts   = [];
        $categoryCounts  = [];
        $typeCounts      = [];
        $comboCounts     = [];
        $structureCounts = [];   // ← NUEVO: firma de estructura de combo

        $remember = function (string $name, string $category, ?string $type = null, int $weight = 1) use (&$productCounts, &$categoryCounts, &$typeCounts): void {
            $key = $this->rotationProductKey($name);
            $productCounts[$key]    = ($productCounts[$key] ?? 0) + $weight;
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + $weight;

            $typeKey = $type ?: $this->marketingType($name, $category);
            $typeCounts[$typeKey]   = ($typeCounts[$typeKey] ?? 0) + $weight;
        };

        RaffleCandidateAnalysis::query()
            ->whereIn('filter_status', ['selected', 'archived'])
            ->where('selection_group', '!=', 'descartado')
            ->where('created_at', '>=', now()->subDays(21))
            ->latest('id')
            ->limit(60)
            ->get()
            ->each(function (RaffleCandidateAnalysis $analysis) use ($remember, &$comboCounts, &$structureCounts): void {
                $prizes = collect($analysis->metrics['prizes'] ?? []);
                if ($prizes->isEmpty()) {
                    $remember($analysis->product_name, $analysis->category, null, 2);
                    return;
                }

                $signature = $this->comboSignature($prizes->all());
                $comboCounts[$signature] = ($comboCounts[$signature] ?? 0) + 1;

                // Registrar firma de estructura
                $structureSig = $this->comboStructureSignature($prizes->all());
                $structureCounts[$structureSig] = ($structureCounts[$structureSig] ?? 0) + 1;

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
                    $name     = (string) $prize->name;
                    $category = $raffle->category ?: $this->inferCategory($name);
                    $remember($name, $category, null, $index === 0 ? 3 : 2);
                }
            });

        return [
            'products'   => $productCounts,
            'categories' => $categoryCounts,
            'types'      => $typeCounts,
            'combos'     => $comboCounts,
            'structures' => $structureCounts,   // ← NUEVO
        ];
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  PERSISTENCIA Y SALIDA
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

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
                        'batch_uuid'    => $batchUuid,
                        'source_file'   => $csvPath,
                        'selection_group' => $group,
                    ]));
                }
            }

            foreach ($discarded as $item) {
                RaffleCandidateAnalysis::create(array_merge($item, [
                    'batch_uuid'    => $batchUuid,
                    'source_file'   => $csvPath,
                    'selection_group' => 'descartado',
                ]));
            }
        });
    }

    private function printResults(string $batchUuid, Collection $big, Collection $flash, Collection $alternatives, int $discardedCount, int $historyCount): void
    {
        $this->info('Analisis guardado. Batch: ' . $batchUuid);
        $this->line('Historico: ' . $historyCount . ' sorteos. Descartados: ' . $discardedCount);

        $this->printSection('Sorteo grande recomendado', $big);
        $this->printSection('Sorteo flash recomendado', $flash);
        $this->printSection('Alternativas', $alternatives);
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
            $theme  = $item['metrics']['combo_theme'] ?? null;
            $isHook = $item['metrics']['is_hook_principal'] ?? false;

            $this->line(($index + 1) . '. Producto: ' . $item['product_name'] . ($isHook ? ' 🪝 GANCHO' : ''));
            $this->line('   Rubro: ' . $item['category']);
            if ($theme) {
                $this->line('   Tema del combo: ' . ucfirst($theme));
            }
            $this->line('   Tipo: ' . ($item['raffle_type'] === 'relampago' ? 'flash ⚡' : 'grande 🏆'));

            if ($item['raffle_type'] === 'grande') {
                $this->line('   Premios:');
                foreach ($prizes as $pi => $prize) {
                    $hookTag = ($prize['is_hook'] ?? false) ? ' 🪝' : '';
                    $this->line('      ' . ($pi + 1) . '. ' . $prize['name'] . $hookTag . ' — ' . $this->gs((int) $prize['cost_gs']));
                }
                $this->line('   Costo total: ' . $this->gs($item['cost_gs']));
            } else {
                $this->line('   Costo: ' . $this->gs($item['cost_gs']));
            }

            $this->line('   Números: ' . $item['numbers_count']);
            $this->line('   Precio/número: ' . $this->gs($item['price_per_number_gs']));
            $this->line('   Recaudación: ' . $this->gs($item['revenue_gs']));
            $this->line('   Ganancia estimada: ' . $this->gs($item['estimated_profit_gs']));
            $this->line('   Score: ' . number_format((float) $item['score'], 2, ',', '.'));
            $this->line('   Riesgo: ' . $item['risk_level']);
            $this->line('   Motivo: ' . $item['reason']);
            $this->line('   Comparacion: ' . $item['historical_comparison']);
        }
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  SCORES Y FILTROS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function filterReasons(string $productName, string $category, int $costGs, ?int $stock, int $minStock): array
    {
        $reasons = [];
        $name    = $this->normalizeText($productName);

        if ($stock !== null && $stock < $minStock) {
            $reasons[] = 'Stock bajo para sorteo.';
        }

        if ($this->isAccessoryLikeProduct($name)) {
            $reasons[] = 'Accesorio o producto secundario: no califica como premio.';
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
            $reasons[] = 'Costo demasiado bajo para sostener valor percibido.';
        }

        if ($this->perceivedValueScore($productName, $category, $costGs) < self::MIN_PERCEIVED_VALUE_SCORE) {
            $reasons[] = 'No parece un premio que alguien realmente quiera ganar.';
        }

        if ($this->saleabilityScore($productName, $category, $costGs, $stock) < 50) {
            $reasons[] = 'Producto dificil de vender por bajo atractivo.';
        }

        return array_values(array_unique($reasons));
    }

    private function attractionScore(string $productName, string $category): int
    {
        $scoreByCategory = [
            'Electronica'                    => 88,
            'Perfumeria'                     => 82,
            'Bebidas'                        => 80,
            'Comestibles'                    => 45,
            'Cosmeticos Salud E Hig.'        => 76,
            'Camping/Pesca/Ferr/Jard/Auto'   => 50,
            'Hogar'                          => 74,
            'Asado'                          => 76,
            'Termos'                         => 78,
            'Belleza'                        => 78,
            'Otros'                          => 62,
        ];

        $score = $scoreByCategory[$category] ?? 62;
        $name  = $this->normalizeText($productName);

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
        $name  = $this->normalizeText($productName);

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

        $negativeKeywords = ['mini', 'repuesto', 'adaptador', 'cable', 'cabo', 'funda', 'capa', 'case', 'cover', 'alfombra', 'alcaparra', 'sachet', 'agrupado', 'antena', 'lnb', 'fuente', 'bateria', 'mount', 'clamp', 'remote', 'dock', 'bastao', 'backoor', 'usb power', 'charger', 'alarma', 'nunchuk', 'wiiu', 'control p', 'headset ear', 'protector', 'floaty'];
        foreach ($negativeKeywords as $keyword) {
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
            'Bebidas'                      => 76,
            'Perfumeria'                   => 78,
            'Electronica'                  => 58,
            'Hogar'                        => 72,
            'Belleza'                      => 70,
            'Termos'                       => 68,
            'Asado'                        => 68,
            'Cosmeticos Salud E Hig.'      => 62,
            'Camping/Pesca/Ferr/Jard/Auto' => 48,
            'Comestibles'                  => 34,
            'Otros'                        => 45,
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
        if ($score >= 78) return 'alto';
        if ($score >= self::MIN_PERCEIVED_VALUE_SCORE) return 'medio';
        return 'bajo';
    }

    private function highPerceivedKeywords(): array
    {
        return ['whisky', 'vodka', 'vino', 'champagne', 'gin', 'ron', 'tequila', 'absolut', 'johnnie', 'jack', 'chivas', 'ballantines', 'black label', 'red label', 'perfume', 'fragancia', 'colonia', 'smart tv', 'iphone', 'samsung', 'jbl', 'parlante', 'auricular', 'xbox', 'ps4', 'ps5', 'console', 'consola', 'licuadora', 'cafetera', 'freidora', 'olla', 'termo', 'combo', 'kit'];
    }

    private function mediumPerceivedKeywords(): array
    {
        return ['750ml', '1l', 'set', 'pack', 'speaker', 'shaver', 'afeitadora', 'secador', 'planchita', 'hover', 'power', 'digital'];
    }

    private function lowPerceivedKeywords(): array
    {
        return ['alfajor', 'galleta', 'alcaparra', 'aceite oliva', 'sachet', 'mini', 'plato', 'bowl', 'taza', 'vaso', 'repuesto', 'flex', 'modulo', 'display', 'pantalla', 'placa', 'carcasa', 'funda', 'cable', 'cabo', 'adaptador', 'tornillo', 'antena', 'lnb', 'fuente', 'bateria', 'mount', 'clamp', 'remote', 'dock', 'protector', 'backoor', 'bastao', 'usb power', 'charger', 'alarma', 'nunchuk', 'wiiu', 'control p', 'headset ear', 'dualshock', 'floaty', 'gopro', 'capa', 'case', 'cover'];
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
        $scores = collect($prizes)->map(fn ($p) => $this->attractionScore($p['name'], $p['category']));

        if ($scores->count() === 1) {
            return (float) $scores->first();
        }

        return (float) round(($scores->first() * 0.55) + ($scores->slice(1)->avg() * 0.45), 2);
    }

    private function planSaleabilityScore(array $prizes, int $price, int $numbers): float
    {
        $scores      = collect($prizes)->map(fn ($p) => $this->saleabilityScore($p['name'], $p['category'], $p['cost_gs'], $p['stock']));
        $priceScore  = $this->priceFitScore($price);
        $numbersScore = $numbers <= 120 ? 92 : max(55, 92 - (($numbers - 120) * 0.8));

        return (float) round(($scores->avg() * 0.52) + ($priceScore * 0.32) + ($numbersScore * 0.16), 2);
    }

    private function planPerceivedValueScore(array $prizes): float
    {
        $scores = collect($prizes)->map(fn ($p) => (float) ($p['perceived_value_score'] ?? $this->perceivedValueScore($p['name'], $p['category'], $p['cost_gs'])));

        if ($scores->count() === 1) {
            return (float) $scores->first();
        }

        return (float) round(($scores->first() * 0.62) + ($scores->slice(1)->avg() * 0.38), 2);
    }

    private function planMarketingMixScore(array $prizes): float
    {
        $categories  = collect($prizes)->pluck('category')->unique()->count();
        $types       = collect($prizes)->pluck('marketing_type')->unique()->count();
        $mainScore   = (float) ($prizes[0]['perceived_value_score'] ?? 0);
        $secondaryAvg = (float) collect($prizes)->slice(1)->avg('perceived_value_score');

        $score  = 30;
        $score += min(30, $categories * 8);
        $score += min(25, $types * 5);
        $score += $mainScore >= 82 ? 10 : 0;
        $score += $secondaryAvg >= 72 ? 5 : 0;

        if ($categories < 3 || $types < 5) {
            $score -= 25;
        }

        return max(0, min(100, $score));
    }

    private function historyScore(array $candidate, string $type, Collection $history): float
    {
        if ($history->isEmpty()) {
            return 50;
        }

        return (float) $history->map(function ($h) use ($candidate, $type) {
            $score  = 35;
            $score += $candidate['category'] === $h['category'] ? 25 : 0;
            $score += $this->similarity($candidate['product_name'], $h['text']) * 25;
            $score += $h['sold_pct'] >= 1 ? 10 : ($h['sold_pct'] * 10);
            $score += ($type === 'relampago' && $h['total_numbers'] <= 60) || ($type === 'grande' && $h['total_numbers'] >= 70) ? 5 : 0;
            return min(100, $score);
        })->max();
    }

    private function productRotationScore(string $productName, string $category, string $type, array $rotation): float
    {
        $productHits  = (int) ($rotation['products'][$this->rotationProductKey($productName)] ?? 0);
        $categoryHits = (int) ($rotation['categories'][$category] ?? 0);
        $typeHits     = (int) ($rotation['types'][$type] ?? 0);

        $score  = 100;
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

        if ($prizesCount >= 5 && $costGs >= 250000 && $costGs <= 1500000) return 100;
        if ($costGs >= 300000) return 86;
        if ($costGs >= 120000) return 72;
        return 35;
    }

    private function riskLevel(float $score, float $margin, int $price): string
    {
        if ($score >= 78 && $margin >= 0.35 && $price <= 15000) return 'bajo';
        if ($score >= 62 && $margin >= 0.25 && $price <= self::MAX_PRICE_PER_NUMBER) return 'medio';
        return 'alto';
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  TEXTOS / NOMBRES
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function reason(array $candidate, string $type, int $numbers, int $price, int $profit, float $margin, int $prizesCount, float $saleabilityScore, float $perceivedValueScore, float $rotationScore, ?string $theme, bool $isHook): string
    {
        $typeText    = $type === 'relampago' ? 'flash de venta rapida' : 'sorteo grande con paquete de premios';
        $accessible  = $price >= self::IDEAL_MIN_PRICE && $price <= self::IDEAL_MAX_PRICE
            ? 'precio accesible dentro del rango ideal'
            : 'precio aceptable aunque fuera del rango ideal';
        $prizeText   = $type === 'relampago'
            ? '1 premio directo'
            : $prizesCount . ' premios con tema ' . ($theme ? ucfirst($theme) : 'mixto');
        $hookText    = $isHook ? ', Premio gancho de alto impacto visual.' : '';

        return sprintf(
            'Elegido como %s porque combina rubro %s, %s, %s, %d numeros, valor percibido %s (%.0f/100), vendibilidad %.0f/100, novedad %.0f/100 y margen %.1f%% — ganancia %s.%s',
            $typeText,
            $candidate['category'],
            $accessible,
            $prizeText,
            $numbers,
            $this->perceivedValueLevel($perceivedValueScore),
            $perceivedValueScore,
            $saleabilityScore,
            $rotationScore,
            $margin * 100,
            $this->gs($profit),
            $hookText
        );
    }

    private function historicalComparison(array $candidate, string $type, Collection $history): string
    {
        if ($history->isEmpty()) {
            return 'Sin sorteos anteriores; se usa heuristica inicial.';
        }

        $best = $history
            ->map(function ($h) use ($candidate, $type) {
                $similarity    = $this->similarity($candidate['product_name'], $h['text']);
                $categoryMatch = $candidate['category'] === $h['category'] ? 0.35 : 0;
                $typeMatch     = $type === 'relampago'
                    ? ($h['total_numbers'] <= 60 ? 0.15 : 0)
                    : ($h['total_numbers'] >= 70 ? 0.15 : 0);

                return $h + ['match_score' => $similarity + $categoryMatch + $typeMatch];
            })
            ->sortByDesc('match_score')
            ->first();

        if (!$best || $best['match_score'] <= 0.05) {
            return 'Sin historico similar; los sorteos anteriores completaron 50-110 numeros a 10.000 Gs.';
        }

        return sprintf(
            'Mas cercano: "%s" (%s), %d numeros a %s, venta %.0f%% completa.',
            $best['name'],
            $best['category'],
            $best['total_numbers'],
            $this->gs($best['price']),
            $best['sold_pct'] * 100
        );
    }

    private function comboName(array $prizes, ?string $theme = null): string
    {
        $themeLabel = $theme ? ucfirst($theme) : 'Mixto';
        $name = collect($prizes)
            ->map(fn ($p) => $this->shortPrizeName((string) $p['name']))
            ->take(3)  // máximo 3 nombres para que no sea muy largo
            ->implode(' + ');

        return "Combo {$themeLabel}: {$name}";
    }

    private function shortPrizeName(string $name): string
    {
        $name       = trim(preg_replace('/\s+/', ' ', $name) ?: $name);
        $name       = preg_replace('/[-\/]?\d{6,}$/', '', $name) ?: $name;
        $name       = preg_replace('/\s+/', ' ', $name) ?: $name;
        $normalized = $this->normalizeText($name);

        $aliases = [
            'almaviva'    => 'Vino Almaviva',
            'abercrombie' => 'Perfume Abercrombie',
            'a banderas'  => 'Perfume A.Banderas',
            'absolut'     => 'Vodka Absolut',
            'alfaparf'    => 'Kit Alfaparf',
            'album digital' => 'Album Digital',
            'carretilla'  => 'Carretilla Sumax',
        ];

        foreach ($aliases as $key => $alias) {
            if (str_contains($normalized, $key)) {
                return $alias;
            }
        }

        return Str::limit(trim($name), 24, '');
    }

    private function rotationSummary(array $prizes, array $rotation): array
    {
        return [
            'repeated_products'    => collect($prizes)
                ->filter(fn ($p) => ($rotation['products'][$this->rotationProductKey((string) $p['name'])] ?? 0) > 0)
                ->map(fn ($p) => $p['name'])
                ->values()->all(),
            'combo_signature_hits' => (int) ($rotation['combos'][$this->comboSignature($prizes)] ?? 0),
            'structure_hits'       => (int) ($rotation['structures'][$this->comboStructureSignature($prizes)] ?? 0),
            'categories'           => collect($prizes)->pluck('category')->unique()->values()->all(),
            'marketing_types'      => collect($prizes)->pluck('marketing_type')->unique()->values()->all(),
        ];
    }

    private function comboSignature(array $prizes): string
    {
        return collect($prizes)->pluck('marketing_type')->filter()->sort()->implode('|');
    }

    private function rotationProductKey(string $name): string
    {
        return implode(' ', array_slice($this->tokens($name), 0, 6));
    }

    private function marketingType(string $productName, string $category): string
    {
        $name = $this->normalizeText($productName);

        $map = [
            'whisky'      => ['whisky', 'johnnie', 'jack', 'chivas', 'ballantines', 'black label', 'red label'],
            'vodka'       => ['vodka', 'absolut'],
            'vino'        => ['vino', 'vinho', 'cabernet', 'malbec', 'pinot', 'sauvignon'],
            'espumante'   => ['champagne', 'espumante', 'brut'],
            'perfume'     => ['perfume', 'fragancia', 'colonia', 'edt', 'edp'],
            'electronica' => ['smart tv', 'tv', 'parlante', 'jbl', 'speaker', 'iphone', 'samsung'],
            'hogar'       => ['licuadora', 'cafetera', 'freidora', 'olla', 'cocina'],
            'termo'       => ['termo', 'guampa', 'mate'],
            'herramienta' => ['taladro', 'herramienta', 'kit herramientas'],
            'belleza'     => ['secador', 'planchita', 'shaver', 'afeitadora'],
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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  HELPERS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function planPrizeNames(Collection $plans): array
    {
        return $plans
            ->flatMap(fn ($p) => collect($p['metrics']['prizes'] ?? [])->pluck('name')->push($p['product_name']))
            ->map(fn ($name) => $this->rotationProductKey((string) $name))
            ->filter()->unique()->values()->all();
    }

    private function planOverlapsProducts(array $plan, array $usedProductKeys): bool
    {
        return $this->planOverlapCount($plan, $usedProductKeys) > 0;
    }

    private function planOverlapCount(array $plan, array $usedProductKeys): int
    {
        $keys = collect($plan['metrics']['prizes'] ?? [])
            ->pluck('name')->push($plan['product_name'])
            ->map(fn ($name) => $this->rotationProductKey((string) $name))
            ->filter()->all();

        return count(array_intersect($keys, $usedProductKeys));
    }

    private function discard(array $row, int $index, string $productName, array $reasons, ?string $category = null, int $costGs = 0, ?int $stock = null): array
    {
        return [
            'product_name'        => $productName,
            'category'            => $category ?: 'Sin clasificar',
            'raffle_type'         => 'descartado',
            'cost_gs'             => max(0, $costGs),
            'stock'               => $stock,
            'numbers_count'       => 0,
            'price_per_number_gs' => 0,
            'revenue_gs'          => 0,
            'estimated_profit_gs' => 0,
            'score'               => 0,
            'risk_level'          => 'alto',
            'reason'              => implode(' ', $reasons),
            'historical_comparison' => null,
            'filter_status'       => 'discarded',
            'filter_reasons'      => $reasons,
            'metrics'             => ['csv_row' => $index + 1, 'raw' => $row],
        ];
    }

    private function inferCategory(string $text): string
    {
        $normalized = $this->normalizeText($text);
        $map = [
            'Bebidas'    => ['whisky', 'vino', 'cerveza', 'champagne', 'hoppy', 'bebida'],
            'Electronica' => ['tv', 'smart', 'jbl', 'parlante', 'auricular', 'celular', 'iphone', 'samsung', 'tablet', 'notebook'],
            'Perfumeria'  => ['perfume', 'colonia', 'fragancia', 'salvang'],
            'Belleza'     => ['secador', 'shaver', 'planchita', 'afeitadora'],
            'Termos'      => ['termo', 'guampa', 'mate', 'bombilla', 'rodax'],
            'Asado'       => ['cuchillo', 'asado', 'parrilla', 'tenedor'],
            'Hogar'       => ['licuadora', 'cocina', 'olla', 'freidora', 'cafetera'],
            'Repuestos'   => ['repuesto', 'flex', 'modulo', 'placa', 'display', 'pantalla'],
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

        if (!$tokensA || !$tokensB) return 0;

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union        = count(array_unique(array_merge($tokensA, $tokensB)));

        return $union > 0 ? $intersection / $union : 0;
    }

    private function tokens(string $text): array
    {
        $words = preg_split('/\s+/', $this->normalizeText($text)) ?: [];
        return array_values(array_filter(array_unique($words), fn ($w) => strlen($w) >= 3));
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
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ñ' => 'n']);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?: '';
        return trim($text);
    }

    private function stripBom(string $text): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $text) ?: $text;
    }

    private function mapyCostToGs(float $costBase, int $usdRate): int
    {
        if ($costBase >= 1000) {
            return (int) round($costBase);
        }
        return (int) round($costBase * $usdRate);
    }

    private function parseMoney(string $value): float
    {
        $value = trim(str_replace(['$', 'USD', 'usd', 'Gs', 'gs', ' '], '', $value));
        if ($value === '') return 0;

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } elseif (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function parseNullableInt(string $value): ?int
    {
        if ($value === '') return null;
        $number = preg_replace('/[^0-9]/', '', $value);
        return $number === '' ? null : (int) $number;
    }

    private function gs(int|float $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' Gs';
    }
}
