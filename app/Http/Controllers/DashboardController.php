<?php

namespace App\Http\Controllers;

use App\Models\AnomalyFlag;
use App\Models\BankAccount;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $accountId = $request->input('account_id');
        $granularity = $request->input('granularity', 'daily');

        // Date range filtering
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        // Summary KPIs
        $summaryQuery = Transaction::query()->forAccount($accountId);
        if ($dateFrom) $summaryQuery->where('transaction_date', '>=', $dateFrom);
        if ($dateTo) $summaryQuery->where('transaction_date', '<=', $dateTo);

        $totalDebit = (clone $summaryQuery)->debit()->sum('amount');
        $totalCredit = (clone $summaryQuery)->credit()->sum('amount');
        $debitCount = (clone $summaryQuery)->debit()->count();
        $creditCount = (clone $summaryQuery)->credit()->count();
        $anomalyCount = AnomalyFlag::whereHas('transaction', function ($q) use ($accountId, $dateFrom, $dateTo) {
            $q->forAccount($accountId);
            if ($dateFrom) $q->where('transaction_date', '>=', $dateFrom);
            if ($dateTo) $q->where('transaction_date', '<=', $dateTo);
        })->where('is_dismissed', false)->count();
        $unclassifiedCount = (clone $summaryQuery)->where('classification_method', 'UNCLASSIFIED')->count();

        // Cashflow chart data — use date range or default
        $minDate = Transaction::forAccount($accountId)->min('transaction_date');
        $chartStart = $dateFrom ?? ($minDate ? Carbon::parse($minDate)->startOfDay() : now()->subMonths(3)->startOfMonth());
        $chartEnd = $dateTo ?? now();
        $cashflow = $this->getCashflowData($chartStart, $chartEnd, $granularity, $accountId);

        // Category breakdown (All transactions: Pemasukan + Pengeluaran)
        $breakdownQuery = Transaction::query()
            ->forAccount($accountId);
        if ($dateFrom) $breakdownQuery->where('transaction_date', '>=', $dateFrom);
        if ($dateTo) $breakdownQuery->where('transaction_date', '<=', $dateTo);

        $breakdown = $breakdownQuery
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category:id,name,color,type')
            ->orderByDesc('total')
            ->get()
            ->map(fn($item) => [
                'name' => $item->category ? "{$item->category->name} (" . ($item->category->type === 'DEBIT' ? 'In' : 'Out') . ")" : 'Belum Diklasifikasi',
                'value' => round($item->total),
                'color' => $item->category->color ?? '#CBD5E1', // Gray-300
            ]);

        // Recent transactions
        $recentTxQuery = Transaction::with('category:id,name,color')
            ->forAccount($accountId)
            ->orderByDesc('transaction_date');
        if ($dateFrom) $recentTxQuery->where('transaction_date', '>=', $dateFrom);
        if ($dateTo) $recentTxQuery->where('transaction_date', '<=', $dateTo);
        $recentTransactions = $recentTxQuery->limit(8)->get();

        // Bank accounts for filter
        $accounts = BankAccount::all();

        // Determine which dashboard to render
        $user = $request->user();
        $view = $user->isDirektur() ? 'Dashboard/Pimpinan' : 'Dashboard/Admin';

        $data = [
            'summary' => [
                'totalDebit' => round($totalDebit),
                'totalCredit' => round($totalCredit),
                'netCashFlow' => round($totalDebit - $totalCredit),
                'debitCount' => $debitCount,
                'creditCount' => $creditCount,
                'transactionCount' => $debitCount + $creditCount,
                'anomalyCount' => $anomalyCount,
                'unclassifiedCount' => $unclassifiedCount,
            ],
            'cashflow' => $cashflow,
            'breakdown' => $breakdown,
            'recentTransactions' => $recentTransactions,
            'accounts' => $accounts,
            'filters' => [
                'account_id' => $accountId,
                'granularity' => $granularity,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'preset' => $request->input('preset'),
            ],
        ];

        // Admin-specific: recent imports + pending anomalies
        if ($user->isAdmin()) {
            $data['recentImports'] = \App\Models\ImportBatch::with('bankAccount:id,bank_name,account_alias')
                ->orderByDesc('imported_at')
                ->limit(5)
                ->get();
            $data['pendingAnomalies'] = AnomalyFlag::with('transaction:id,description,amount,type,transaction_date')
                ->where('is_dismissed', false)
                ->where('is_reviewed', false)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        return Inertia::render($view, $data);
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
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'last_3_months' => [now()->subMonths(3)->startOfMonth(), now()->endOfMonth()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [null, null],
            };
        }

        return [
            $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null,
            $dateTo ? Carbon::parse($dateTo)->endOfDay() : null,
        ];
    }

    private function getCashflowData(Carbon $startDate, Carbon $endDate, string $granularity, $accountId): array
    {
        $transactions = Transaction::query()
            ->forAccount($accountId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('transaction_date', 'amount', 'type')
            ->orderBy('transaction_date')
            ->get();

        $groupMap = [];
        foreach ($transactions as $tx) {
            $d = $tx->transaction_date;
            $key = match ($granularity) {
                'monthly' => $d->format('Y-m'),
                'yearly' => $d->format('Y'),
                default => $d->format('Y-m-d'),
            };

            if (!isset($groupMap[$key])) {
                $groupMap[$key] = ['debit' => 0, 'credit' => 0];
            }
            if ($tx->type === 'DEBIT') {
                $groupMap[$key]['debit'] += $tx->amount;
            } else {
                $groupMap[$key]['credit'] += $tx->amount;
            }
        }

        $dates = [];
        $debitData = [];
        $creditData = [];
        $netData = [];

        $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        if ($granularity === 'daily') {
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $key = $current->format('Y-m-d');
                $entry = $groupMap[$key] ?? ['debit' => 0, 'credit' => 0];
                $dates[] = $key;
                $debitData[] = round($entry['debit']);
                $creditData[] = round($entry['credit']);
                $netData[] = round($entry['debit'] - $entry['credit']);
                $current->addDay();
            }
        } elseif ($granularity === 'monthly') {
            $current = $startDate->copy()->startOfMonth();
            while ($current->lte($endDate)) {
                $key = $current->format('Y-m');
                $entry = $groupMap[$key] ?? ['debit' => 0, 'credit' => 0];
                $dates[] = $monthNames[$current->month - 1] . ' ' . $current->year;
                $debitData[] = round($entry['debit']);
                $creditData[] = round($entry['credit']);
                $netData[] = round($entry['debit'] - $entry['credit']);
                $current->addMonth();
            }
        } else {
            ksort($groupMap);
            foreach ($groupMap as $key => $entry) {
                $dates[] = $key;
                $debitData[] = round($entry['debit']);
                $creditData[] = round($entry['credit']);
                $netData[] = round($entry['debit'] - $entry['credit']);
            }
        }

        return compact('dates', 'debitData', 'creditData', 'netData');
    }
}
