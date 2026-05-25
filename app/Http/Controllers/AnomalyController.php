<?php

namespace App\Http\Controllers;

use App\Models\AnomalyFlag;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Services\AnomalyDetectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnomalyController extends Controller
{
    public function index(Request $request)
    {
        $severity = $request->input('severity', 'ALL');
        $type = $request->input('type', 'ALL');

        $query = AnomalyFlag::with([
            'transaction' => fn($q) => $q->with('category:id,name,color', 'bankAccount:id,bank_name,account_alias'),
        ])->orderByDesc('detected_at');

        if ($severity !== 'ALL') {
            $query->where('severity', $severity);
        }

        if ($type !== 'ALL') {
            $query->where('detection_method', 'LIKE', $type . '%');
        }

        $anomalies = $query->paginate(20)->withQueryString();

        return Inertia::render('Anomalies', [
            'anomalies' => $anomalies,
            'accounts' => BankAccount::all(),
            'filters' => [
                'severity' => $severity,
                'type' => $type,
            ],
        ]);
    }

    public function detect(Request $request, AnomalyDetectionService $detector)
    {
        $accountId = $request->input('account_id') ?: null;

        // Clear unreviewed, non-dismissed flags before re-running
        AnomalyFlag::where('is_reviewed', false)
            ->where('is_dismissed', false)
            ->delete();

        $results = $detector->runFullDetection($accountId);

        $flagsCreated = 0;
        foreach ($results as $r) {
            // Skip if this transaction already has an active flag
            $exists = AnomalyFlag::where('transaction_id', $r['transaction_id'])
                ->where('detection_method', $r['type'] . '_' . $r['subtype'])
                ->where(function ($q) {
                    $q->where('is_reviewed', true)
                      ->orWhere('is_dismissed', true);
                })
                ->exists();

            if ($exists) continue;

            AnomalyFlag::create([
                'transaction_id' => $r['transaction_id'],
                'detection_method' => $r['type'] . '_' . $r['subtype'],
                'score' => $r['score'],
                'severity' => $r['severity'],
                'reason' => $r['reason'],
            ]);
            $flagsCreated++;
        }

        $incomeCount = collect($results)->where('type', 'INCOME')->count();
        $expenseCount = collect($results)->where('type', 'EXPENSE')->count();

        return back()->with('flash', [
            'detectResult' => [
                'message' => "Deteksi selesai. {$flagsCreated} anomali ditemukan ({$incomeCount} pemasukan, {$expenseCount} pengeluaran).",
                'flags_created' => $flagsCreated,
            ],
        ]);
    }

    /**
     * Review an anomaly flag with a note or dismiss it.
     */
    public function review(Request $request, $id)
    {
        $flag = AnomalyFlag::findOrFail($id);

        $flag->update([
            'is_reviewed' => true,
            'is_dismissed' => $request->boolean('dismiss', false),
            'review_note' => $request->input('review_note'),
        ]);

        return back();
    }

    /**
     * Request a review for an anomaly flag from the Pimpinan (Leader).
     */
    public function requestPimpinanReview(Request $request, $id)
    {
        $flag = AnomalyFlag::findOrFail($id);

        $flag->update([
            'ask_pimpinan_review' => true,
            'pimpinan_review_status' => 'PENDING',
        ]);

        return back();
    }

    /**
     * Submit Pimpinan's review on an anomaly flag.
     */
    public function pimpinanReview(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:ANOMALY,VALID',
            'note' => 'nullable|string',
        ]);

        $flag = AnomalyFlag::findOrFail($id);
        $status = $request->input('status');
        $note = $request->input('note');

        $flag->update([
            'pimpinan_review_status' => $status,
            'pimpinan_review_note' => $note,
            'is_reviewed' => true,
            'is_dismissed' => $status === 'VALID',
        ]);

        return back();
    }

    /**
     * Show anomalies page for Pimpinan.
     */
    public function pimpinanIndex(Request $request)
    {
        $accountId = $request->input('account_id');

        // Resolve date range
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $query = AnomalyFlag::with([
            'transaction' => fn($q) => $q->with('bankAccount:id,bank_name,account_alias')
        ])->whereHas('transaction', function ($q) use ($accountId, $dateFrom, $dateTo) {
            $q->forAccount($accountId);
            if ($dateFrom) $q->where('transaction_date', '>=', $dateFrom);
            if ($dateTo) $q->where('transaction_date', '<=', $dateTo);
        })->orderByDesc('created_at');

        // Stats calculation
        $totalAnomalies = (clone $query)->get();
        $unreviewedCount = $totalAnomalies->filter(fn($a) => !$a->is_reviewed)->count();
        $reviewedCount = $totalAnomalies->filter(fn($a) => $a->is_reviewed && !$a->is_dismissed)->count();
        $pimpinanPendingCount = $totalAnomalies->filter(fn($a) => $a->ask_pimpinan_review && $a->pimpinan_review_status === 'PENDING')->count();
        $pimpinanReviewedCount = $totalAnomalies->filter(fn($a) => $a->ask_pimpinan_review && $a->pimpinan_review_status !== 'PENDING')->count();

        // Paginate anomalies
        $anomalies = $query->paginate(20)->withQueryString();

        return Inertia::render('AnomaliesPimpinan', [
            'anomalies' => $anomalies,
            'accounts' => BankAccount::all(),
            'filters' => [
                'account_id' => $accountId,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'preset' => $request->input('preset'),
            ],
            'stats' => [
                'unreviewedCount' => $unreviewedCount,
                'reviewedCount' => $reviewedCount,
                'totalCount' => $totalAnomalies->count(),
                'pimpinanPendingCount' => $pimpinanPendingCount,
                'pimpinanReviewedCount' => $pimpinanReviewedCount,
            ],
        ]);
    }

    /**
     * Resolve date range from request params (preset or custom).
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = $request->input('preset');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($preset) {
            return match ($preset) {
                'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
                'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
                'last_3_months' => [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()],
                'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
                default => [null, null],
            };
        }

        return [
            $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null,
            $dateTo ? Carbon::parse($dateTo)->endOfDay() : null,
        ];
    }
}
