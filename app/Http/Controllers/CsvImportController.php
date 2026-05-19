<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Services\CsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CsvImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Import', [
            'accounts' => BankAccount::all(),
            'batches' => ImportBatch::with('bankAccount:id,bank_name,account_alias')
                ->orderByDesc('imported_at')
                ->get(),
            'trashedBatches' => ImportBatch::onlyTrashed()
                ->with('bankAccount:id,bank_name,account_alias')
                ->orderByDesc('deleted_at')
                ->get(),
            'pendingDuplicates' => \App\Models\DuplicateTransaction::with('bankAccount:id,bank_name,account_alias')
                ->where('status', 'PENDING')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:bank_accounts,id',
            'csv_files' => 'required|array|min:1|max:3',
            'csv_files.*' => 'required|file|max:10240',
        ], [
            'csv_files.max' => 'Maksimal 3 file dapat diunggah sekaligus.'
        ]);

        $accountId = $request->input('account_id');
        $service = app(CsvImportService::class);
        $pdfParser = app(\App\Services\PdfParserService::class);
        
        $totalRows = 0;
        $successRows = 0;
        $duplicateRows = 0;
        $failedRows = 0;
        $failedDetails = [];
        $filesProcessed = [];

        foreach ($request->file('csv_files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['csv', 'txt', 'pdf'])) continue;

            $fileName = $file->getClientOriginalName();
            $filesProcessed[] = $fileName;

            try {
                if ($ext === 'pdf') {
                    set_time_limit(120); // PDF processing may take longer
                    $rawText = $pdfParser->extractText($file->getRealPath());
                    $pdfConverter = app(\App\Services\PdfToCsvService::class);
                    $result = $pdfConverter->convertToCsv($rawText);
                    $rawContent = $result['csv'];
                    if (empty($rawContent)) {
                        throw new \Exception("Tidak ada transaksi yang dapat diekstrak dari PDF.");
                    }
                } else {
                    $rawContent = file_get_contents($file->getRealPath());
                }
            } catch (\Exception $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'csv_files' => "Gagal membaca file {$fileName}: " . $e->getMessage()
                ]);
            }

            $res = $service->importFromRawCsv($accountId, $rawContent, $fileName, auth()->id());
            
            $totalRows += $res['total_rows'];
            $successRows += $res['success_rows'];
            $duplicateRows += $res['duplicate_rows'];
            $failedRows += $res['failed_rows'];
            
            if (!empty($res['failed_details'])) {
                foreach ($res['failed_details'] as $fd) {
                    $fd['file'] = $fileName;
                    $failedDetails[] = $fd;
                }
            }
        }

        return back()->with('importResult', [
            'status' => $successRows > 0 ? 'COMPLETED' : 'FAILED',
            'bank_format' => 'Multiple Files',
            'periode' => implode(', ', $filesProcessed),
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'failed_rows' => $failedRows,
            'duplicate_rows' => $duplicateRows,
            'failed_details' => $failedDetails,
            'message' => "Diproses " . count($filesProcessed) . " file.",
        ]);
    }

    /**
     * Soft-delete batch + its transactions (data stays in DB)
     */
    public function destroy(ImportBatch $batch)
    {
        $fileName = $batch->file_name;
        $txIds = Transaction::where('import_batch_id', $batch->id)->pluck('id');
        $txCount = $txIds->count();

        // Soft-delete anomaly flags
        \App\Models\AnomalyFlag::whereIn('transaction_id', $txIds)->delete();

        // Soft-delete transactions
        Transaction::where('import_batch_id', $batch->id)->delete();

        // Soft-delete the batch
        $batch->delete();

        // Recalculate rule hit_counts
        $rules = \App\Models\ClassificationRule::all();
        foreach ($rules as $rule) {
            $rule->update([
                'hit_count' => Transaction::where('classification_method', 'RULE_BASED')
                    ->where('category_id', $rule->category_id)
                    ->count(),
            ]);
        }

        Log::info("Soft-deleted import batch: {$fileName} ({$txCount} transactions)");

        return back()->with('importResult', [
            'status' => 'DELETED',
            'bank_format' => 'Dihapus',
            'periode' => '',
            'total_rows' => $txCount,
            'success_rows' => 0,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
            'failed_details' => [],
            'message' => "Berhasil menghapus \"{$fileName}\" beserta {$txCount} transaksi",
        ]);
    }

    /**
     * Restore a soft-deleted batch + its transactions
     */
    public function restore($id)
    {
        $batch = ImportBatch::onlyTrashed()->findOrFail($id);
        $fileName = $batch->file_name;

        // Restore transactions
        Transaction::onlyTrashed()
            ->where('import_batch_id', $batch->id)
            ->restore();

        // Restore the batch
        $batch->restore();

        // Recalculate rule hit_counts
        $rules = \App\Models\ClassificationRule::all();
        foreach ($rules as $rule) {
            $rule->update([
                'hit_count' => Transaction::where('classification_method', 'RULE_BASED')
                    ->where('category_id', $rule->category_id)
                    ->count(),
            ]);
        }

        $txCount = Transaction::where('import_batch_id', $batch->id)->count();

        Log::info("Restored import batch: {$fileName} ({$txCount} transactions)");

        return back()->with('importResult', [
            'status' => 'RESTORED',
            'bank_format' => 'Dipulihkan',
            'periode' => '',
            'total_rows' => $txCount,
            'success_rows' => $txCount,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
            'failed_details' => [],
            'message' => "Berhasil memulihkan \"{$fileName}\" beserta {$txCount} transaksi",
        ]);
    }

    /**
     * Permanently delete a soft-deleted batch + its transactions
     */
    public function forceDestroy($id)
    {
        $batch = ImportBatch::onlyTrashed()->findOrFail($id);
        $fileName = $batch->file_name;

        // Permanently delete anomaly flags
        $txIds = Transaction::onlyTrashed()->where('import_batch_id', $batch->id)->pluck('id');
        \App\Models\AnomalyFlag::whereIn('transaction_id', $txIds)->forceDelete();

        // Permanently delete transactions
        $txCount = $txIds->count();
        Transaction::onlyTrashed()->where('import_batch_id', $batch->id)->forceDelete();

        // Permanently delete the batch
        $batch->forceDelete();

        Log::info("Permanently deleted import batch: {$fileName} ({$txCount} transactions)");

        return back()->with('importResult', [
            'status' => 'DELETED',
            'bank_format' => 'Dihapus Permanen',
            'periode' => '',
            'total_rows' => $txCount,
            'success_rows' => 0,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
            'failed_details' => [],
            'message' => "Berhasil menghapus permanen \"{$fileName}\" beserta {$txCount} transaksi",
        ]);
    }

    public function forceDestroyBatch(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return back();

        $batches = ImportBatch::onlyTrashed()->whereIn('id', $ids)->get();
        $totalTxCount = 0;
        $batchCount = 0;

        foreach ($batches as $batch) {
            $txIds = Transaction::onlyTrashed()->where('import_batch_id', $batch->id)->pluck('id');
            \App\Models\AnomalyFlag::whereIn('transaction_id', $txIds)->forceDelete();
            
            $txCount = $txIds->count();
            Transaction::onlyTrashed()->where('import_batch_id', $batch->id)->forceDelete();
            
            $batch->forceDelete();
            $totalTxCount += $txCount;
            $batchCount++;
        }

        Log::info("Permanently deleted {$batchCount} import batches ({$totalTxCount} transactions)");

        return back()->with('importResult', [
            'status' => 'DELETED',
            'bank_format' => 'Dihapus Permanen',
            'periode' => '',
            'total_rows' => $totalTxCount,
            'success_rows' => 0,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
            'failed_details' => [],
            'message' => "Berhasil menghapus permanen {$batchCount} file beserta {$totalTxCount} transaksi",
        ]);
    }

    public function resolveBatch(Request $request)
    {
        $ids = $request->input('ids', []);
        $action = $request->input('action'); // 'IMPORT' or 'DISMISS'

        if (empty($ids)) {
            return back();
        }

        $duplicates = \App\Models\DuplicateTransaction::whereIn('id', $ids)
            ->where('status', 'PENDING')
            ->get();
            
        $classifier = app(\App\Services\ClassificationService::class);
        $successCount = 0;

        foreach ($duplicates as $duplicate) {
            if ($action === 'IMPORT') {
                // Force import by generating a new 64-char hash
                $newHash = hash('sha256', $duplicate->deduplication_hash . uniqid());

                \App\Models\Transaction::create([
                    'import_batch_id' => $duplicate->import_batch_id,
                    'bank_account_id' => $duplicate->bank_account_id,
                    'transaction_date' => $duplicate->transaction_date,
                    'description' => $duplicate->description,
                    'amount' => $duplicate->amount,
                    'type' => $duplicate->type,
                    'raw_data' => $duplicate->raw_data,
                    'deduplication_hash' => $newHash,
                ]);
                $duplicate->update(['status' => 'IMPORTED']);
                
                // Also classify it
                $classification = $classifier->classify($duplicate->description, $duplicate->type);
                \App\Models\Transaction::where('deduplication_hash', $newHash)
                    ->update([
                        'category_id' => $classification['category_id'],
                        'classification_method' => $classification['method'],
                        'confidence_score' => $classification['confidence'],
                    ]);
                $successCount++;
            } else {
                $duplicate->update(['status' => 'DISMISSED']);
            }
        }

        return back()->with('importResult', [
            'status' => 'COMPLETED',
            'bank_format' => 'Resolusi Duplikat',
            'periode' => '',
            'total_rows' => count($ids),
            'success_rows' => $action === 'IMPORT' ? $successCount : 0,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
            'failed_details' => [],
            'message' => $action === 'IMPORT' 
                ? "{$successCount} transaksi duplikat berhasil dipaksa masuk." 
                : count($ids) . ' transaksi duplikat diabaikan/dihapus.',
        ]);
    }
}
