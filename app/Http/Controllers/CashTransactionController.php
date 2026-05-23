<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CashTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with('category:id,name,color,type')
            ->cash()
            ->orderByDesc('transaction_date');

        if ($search = $request->input('search')) {
            $query->where('description', 'like', "%{$search}%");
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->where('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $transactions = $query->paginate(30)->withQueryString();

        return Inertia::render('CashTransactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'type', 'date_from', 'date_to']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'type', 'color']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:DEBIT,CREDIT',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        Transaction::create([
            'type' => $request->type,
            'amount' => $request->amount,
            'transaction_date' => $request->transaction_date,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'source' => 'CASH_MANUAL',
            'classification_method' => $request->category_id ? 'MANUAL' : 'UNCLASSIFIED',
            'confidence_score' => $request->category_id ? 1.0 : 0,
            'deduplication_hash' => 'CASH_' . Str::uuid()->toString(),
            'bank_account_id' => null,
            'import_batch_id' => null,
        ]);

        return back();
    }

    public function destroy(Transaction $transaction)
    {
        // Only allow deleting cash transactions
        if ($transaction->source !== 'CASH_MANUAL') {
            abort(403, 'Hanya transaksi tunai yang dapat dihapus.');
        }

        $transaction->delete();
        return back();
    }
}
