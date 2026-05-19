<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with('category:id,name,color', 'bankAccount:id,bank_name,account_alias')
            ->orderByDesc('transaction_date');

        $this->applyFilters($query, $request);

        $transactions = $query->paginate($request->input('per_page', 50))->withQueryString();

        return Inertia::render('Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'type', 'category_id', 'account_id', 'date_from', 'date_to']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'type', 'color']),
            'accounts' => BankAccount::all(['id', 'bank_name', 'account_alias']),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'required|string|max:1000',
        ]);

        $transaction->update([
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id'),
            'classification_method' => $request->input('category_id') ? 'MANUAL' : 'UNCLASSIFIED',
            'confidence_score' => $request->input('category_id') ? 1.0 : 0,
        ]);

        return back();
    }

    /**
     * Export filtered transactions as CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Transaction::with('category:id,name', 'bankAccount:id,bank_name,account_alias')
            ->orderByDesc('transaction_date');

        $this->applyFilters($query, $request);

        $fileName = 'transaksi_sikubi_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            // Header
            fputcsv($out, ['Tanggal', 'Deskripsi', 'Kategori', 'Tipe', 'Rekening', 'Jumlah', 'Metode Klasifikasi']);

            // Data (chunked for memory efficiency)
            $query->chunk(500, function ($transactions) use ($out) {
                foreach ($transactions as $tx) {
                    fputcsv($out, [
                        $tx->transaction_date->format('Y-m-d'),
                        $tx->description,
                        $tx->category->name ?? '-',
                        $tx->type === 'DEBIT' ? 'Pemasukan' : 'Pengeluaran',
                        $tx->bankAccount->account_alias ?? $tx->bankAccount->bank_name ?? '-',
                        $tx->amount,
                        $tx->classification_method,
                    ]);
                }
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Apply shared filters to a transaction query.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where('description', 'like', "%{$search}%");
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($accountId = $request->input('account_id')) {
            $query->where('bank_account_id', $accountId);
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->where('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('transaction_date', '<=', $dateTo);
        }
    }
}
