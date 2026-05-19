<?php

namespace App\Http\Controllers;

use App\Models\AnomalyFlag;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Services\AnomalyDetectionService;
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
}
