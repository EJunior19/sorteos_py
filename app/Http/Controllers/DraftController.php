<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleCandidateAnalysis;
use App\Models\RaffleNumber;
use App\Models\RafflePrize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DraftController extends Controller
{
    public function index(): View
    {
        $groups = RaffleCandidateAnalysis::query()
            ->where('filter_status', 'selected')
            ->whereNull('approved_at')
            ->orderByRaw("case selection_group when 'top_grande' then 1 when 'top_relampago' then 2 when 'alternativa' then 3 else 4 end")
            ->orderByDesc('score')
            ->get()
            ->groupBy('selection_group');

        return view('admin.drafts.index', compact('groups'));
    }

    public function show(int $id): View
    {
        $candidate = RaffleCandidateAnalysis::findOrFail($id);

        return view('admin.drafts.show', compact('candidate'));
    }

    public function approve(int $id): RedirectResponse
    {
        $raffle = DB::transaction(function () use ($id) {
            $candidate = RaffleCandidateAnalysis::where('filter_status', 'selected')
                ->whereNull('approved_at')
                ->lockForUpdate()
                ->findOrFail($id);

            $prizes = collect($candidate->metrics['prizes'] ?? [])
                ->filter(fn ($prize) => !empty($prize['name']) && (int) ($prize['cost_gs'] ?? 0) > 0)
                ->values();

            if ($prizes->isEmpty()) {
                $prizes = collect([[
                    'name' => $candidate->product_name,
                    'cost_gs' => $candidate->cost_gs,
                ]]);
            }

            $raffle = Raffle::create([
                'name'             => $candidate->product_name,
                'price'            => $candidate->price_per_number_gs,
                'total_numbers'    => $candidate->numbers_count,
                'category'         => $candidate->category,
                'original_product' => $candidate->product_name,
                'raffle_type'      => $candidate->raffle_type,
                'cost_gs'          => $candidate->cost_gs,
                'status'           => 'active',
                'prizes_count'     => $prizes->count(),
                'urgency_messages' => $candidate->metrics['urgency_messages'] ?? [],
            ]);

            foreach ($prizes as $index => $prize) {
                RafflePrize::create([
                    'raffle_id' => $raffle->id,
                    'order'     => $index + 1,
                    'name'      => $prize['name'],
                    'cost'      => (int) $prize['cost_gs'],
                ]);
            }

            $now = now();
            $numbers = [];

            for ($i = 1; $i <= $candidate->numbers_count; $i++) {
                $numbers[] = [
                    'raffle_id'  => $raffle->id,
                    'number'     => $i,
                    'status'     => 'free',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($numbers, 500) as $chunk) {
                RaffleNumber::insert($chunk);
            }

            $candidate->update([
                'approved_at'       => $now,
                'created_raffle_id' => $raffle->id,
            ]);

            return $raffle;
        });

        return redirect()
            ->route('admin.roulette', $raffle->id)
            ->with('success', 'Borrador aprobado y sorteo creado correctamente.');
    }

    public function reject(int $id): RedirectResponse
    {
        $candidate = RaffleCandidateAnalysis::whereNull('approved_at')->findOrFail($id);

        $candidate->update([
            'filter_status' => 'rejected',
        ]);

        return redirect()
            ->route('admin.drafts.index')
            ->with('success', 'Borrador rechazado.');
    }
}
