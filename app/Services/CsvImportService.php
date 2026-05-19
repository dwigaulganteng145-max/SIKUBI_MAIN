<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ImportBatch;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CsvImportService
{
    protected ClassificationService $classifier;

    public function __construct(ClassificationService $classifier)
    {
        $this->classifier = $classifier;
    }

    /**
     * Main entry point — auto-detects bank format and parses accordingly.
     */
    public function importFromRawCsv(int $accountId, string $rawContent, string $fileName, ?int $userId = null): array
    {
        $account = BankAccount::findOrFail($accountId);

        $rawContent = str_replace(["\r\n", "\r"], "\n", $rawContent);
        $lines = explode("\n", $rawContent);

        // Detect bank format
        $format = $this->detectBankFormat($lines);
        Log::info("Detected bank format: {$format}", ['file' => $fileName]);

        return match ($format) {
            'BRI' => $this->importBri($accountId, $lines, $fileName, $userId),
            default => $this->importBca($accountId, $lines, $rawContent, $fileName, $userId),
        };
    }

    /**
     * Scan file headers to detect BCA vs BRI vs generic format.
     */
    public function detectBankFormat(array $lines): string
    {
        $headerArea = implode(' ', array_slice($lines, 0, min(15, count($lines))));
        $upper = strtoupper($headerArea);

        // BRI indicators
        if (str_contains($upper, 'POSTING DATE') && str_contains($upper, 'REMARK')) {
            return 'BRI';
        }
        if (str_contains($upper, 'POSTING DATE') && (str_contains($upper, 'DEBIT') && str_contains($upper, 'CREDIT'))) {
            return 'BRI';
        }

        // BCA indicators (default)
        if (str_contains($upper, 'TANGGAL TRANSAKSI') || str_contains($upper, 'KETERANGAN') || str_contains($upper, 'MUTASI')) {
            return 'BCA';
        }

        // Fallback: try to detect by content patterns
        foreach ($lines as $line) {
            // BRI date format: "03 Mar 2026, 02:28:56"
            if (preg_match('/\d{2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4},\s*\d{2}:\d{2}:\d{2}/i', $line)) {
                return 'BRI';
            }
        }

        return 'BCA'; // default fallback
    }

    // ═══════════════════════════════════════════════════════════════
    //  BCA PARSER (existing logic, preserved)
    // ═══════════════════════════════════════════════════════════════

    private function importBca(int $accountId, array $lines, string $rawContent, string $fileName, ?int $userId): array
    {
        // Extract year from Periode line
        $year = date('Y');
        $periode = '';
        foreach ($lines as $line) {
            if (preg_match('/Periode\s*:\s*(\d{2}\/\d{2}\/(\d{4}))\s*-\s*(\d{2}\/\d{2}\/\d{4})/', trim($line), $m)) {
                $year = $m[2];
                $periode = $m[1] . ' - ' . $m[3];
                break;
            }
        }

        // Find header row
        $headerIndex = null;
        foreach ($lines as $i => $line) {
            $upper = strtoupper($line);
            if (str_contains($upper, 'TANGGAL TRANSAKSI') ||
                (str_contains($upper, 'TANGGAL') && str_contains($upper, 'KETERANGAN') && str_contains($upper, 'JUMLAH'))) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) $headerIndex = 5;

        $dataLines = array_values(array_filter(
            array_slice($lines, $headerIndex + 1),
            function ($l) {
                $trimmed = trim($l);
                if ($trimmed === '' || $trimmed === '""') return false;
                $upper = strtoupper($trimmed);
                if (str_starts_with($upper, 'SALDO AWAL')) return false;
                if (str_starts_with($upper, 'SALDO AKHIR')) return false;
                if (str_contains($upper, 'MUTASI DEBET')) return false;
                if (str_contains($upper, 'MUTASI KREDIT')) return false;
                if (str_starts_with($trimmed, '"MUTASI')) return false;
                return true;
            }
        ));

        return $this->processRows($accountId, $fileName, $userId, 'BCA', $periode, $dataLines, function ($rawLine) use ($year) {
            return $this->parseBcaRow($rawLine, $year);
        });
    }

    // ═══════════════════════════════════════════════════════════════
    //  BRI PARSER (new — adaptive column mapping)
    // ═══════════════════════════════════════════════════════════════

    private function importBri(int $accountId, array $lines, string $fileName, ?int $userId): array
    {
        // Find header row & map columns
        $headerIndex = null;
        $columnMap = [];

        foreach ($lines as $i => $line) {
            $upper = strtoupper(trim($line));
            if (str_contains($upper, 'POSTING DATE') || str_contains($upper, 'REMARK')) {
                $headerIndex = $i;
                $columnMap = $this->detectColumnMapping($line);
                break;
            }
        }

        if ($headerIndex === null) {
            // Try CSV header detection
            foreach ($lines as $i => $line) {
                $fields = str_getcsv($line);
                $upper = strtoupper(implode(' ', $fields));
                if (str_contains($upper, 'DATE') && str_contains($upper, 'DEBIT') && str_contains($upper, 'CREDIT')) {
                    $headerIndex = $i;
                    $columnMap = $this->detectColumnMappingFromFields($fields);
                    break;
                }
            }
        }

        if ($headerIndex === null) $headerIndex = 0;

        // Extract period from first/last dates
        $dataLines = array_values(array_filter(
            array_slice($lines, $headerIndex + 1),
            fn($l) => trim($l) !== '' && !preg_match('/^(Total|Saldo|Page|\d+\s*of\s*\d+)/i', trim($l))
        ));

        return $this->processRows($accountId, $fileName, $userId, 'BRI', '', $dataLines, function ($rawLine) use ($columnMap) {
            return $this->parseBriRow($rawLine, $columnMap);
        });
    }

    /**
     * Detect column mapping from a header line.
     */
    private function detectColumnMapping(string $headerLine): array
    {
        $fields = str_getcsv($headerLine);
        return $this->detectColumnMappingFromFields($fields);
    }

    /**
     * Map column positions based on header names.
     */
    private function detectColumnMappingFromFields(array $fields): array
    {
        $map = ['date' => 0, 'remark' => 1, 'ref' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5];

        foreach ($fields as $i => $field) {
            $upper = strtoupper(trim($field));
            if (str_contains($upper, 'POSTING') || str_contains($upper, 'TANGGAL') || $upper === 'DATE') {
                $map['date'] = $i;
            } elseif (str_contains($upper, 'REMARK') || str_contains($upper, 'KETERANGAN') || str_contains($upper, 'DESCRIPTION')) {
                $map['remark'] = $i;
            } elseif (str_contains($upper, 'REFERENCE') || str_contains($upper, 'REF')) {
                $map['ref'] = $i;
            } elseif (str_contains($upper, 'DEBIT') || str_contains($upper, 'DEBET')) {
                $map['debit'] = $i;
            } elseif (str_contains($upper, 'CREDIT') || str_contains($upper, 'KREDIT')) {
                $map['credit'] = $i;
            } elseif (str_contains($upper, 'BALANCE') || str_contains($upper, 'SALDO')) {
                $map['balance'] = $i;
            }
        }

        return $map;
    }

    /**
     * Parse a single BRI CSV row.
     */
    private function parseBriRow(string $rawLine, array $columnMap): ?array
    {
        $fields = str_getcsv($rawLine);
        if (count($fields) < 4) return null;

        $dateStr = trim($fields[$columnMap['date']] ?? '');
        $remark = trim($fields[$columnMap['remark']] ?? '');
        $debitRaw = trim($fields[$columnMap['debit']] ?? '0');
        $creditRaw = trim($fields[$columnMap['credit']] ?? '0');
        $balanceRaw = trim($fields[$columnMap['balance']] ?? '');

        if (empty($dateStr) || empty($remark)) return null;

        // Parse BRI date formats
        $date = $this->parseBriDate($dateStr);
        if (!$date) return null;

        // Clean description (remove multi-line artifacts, extra whitespace)
        $remark = preg_replace('/\s+/', ' ', $remark);

        // Parse amounts
        $debit = $this->parseAmount($debitRaw);
        $credit = $this->parseAmount($creditRaw);

        // BRI: Credit column = money IN (DEBIT in our system), Debit column = money OUT (CREDIT in our system)
        if ($credit > 0) {
            $type = 'DEBIT'; // incoming
            $amount = $credit;
        } elseif ($debit > 0) {
            $type = 'CREDIT'; // outgoing
            $amount = $debit;
        } else {
            return null; // no amount
        }

        $saldo = $this->parseAmount($balanceRaw);

        return [
            'date' => $date,
            'description' => $remark,
            'amount' => $amount,
            'type' => $type,
            'cabang' => null,
            'saldo' => $saldo > 0 ? (string) $saldo : null,
        ];
    }

    /**
     * Parse BRI date format: "03 Mar 2026, 02:28:56" or "03 Mar 2026" or "2026-03-03"
     */
    private function parseBriDate(string $dateStr): ?Carbon
    {
        // Format: "03 Mar 2026, 02:28:56"
        if (preg_match('/^(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})/i', $dateStr, $m)) {
            try {
                return Carbon::createFromFormat('d M Y', $m[1] . ' ' . $m[2] . ' ' . $m[3])->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        }

        // ISO format fallback
        try {
            return Carbon::parse($dateStr)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse an amount string (handles commas, dots, etc.)
     */
    private function parseAmount(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-' || $raw === '0' || $raw === '0.00') return 0;
        $cleaned = preg_replace('/[^\d.,]/', '', $raw);
        // Handle "180,000.00" format (comma as thousand separator, dot as decimal)
        if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }
        // Handle "180.000,00" format (dot as thousand separator, comma as decimal)
        elseif (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }
        return (float) $cleaned;
    }

    // ═══════════════════════════════════════════════════════════════
    //  SHARED PROCESSING LOGIC
    // ═══════════════════════════════════════════════════════════════

    /**
     * Process parsed rows: deduplicate, classify, create transactions.
     */
    private function processRows(int $accountId, string $fileName, ?int $userId, string $bankFormat, string $periode, array $dataLines, callable $parser): array
    {
        $totalRows = count($dataLines);
        $batch = ImportBatch::create([
            'bank_account_id' => $accountId,
            'uploaded_by' => $userId,
            'file_name' => $fileName,
            'bank_format' => $bankFormat,
            'total_rows' => $totalRows,
            'status' => 'PROCESSING',
            'imported_at' => now(),
        ]);

        $successCount = 0;
        $failedCount = 0;
        $duplicateCount = 0;
        $failedRows = [];

        foreach ($dataLines as $rowIndex => $rawLine) {
            try {
                $parsed = $parser($rawLine);

                if (!$parsed) {
                    $failedCount++;
                    $failedRows[] = [
                        'row' => $rowIndex + 1,
                        'line' => mb_substr(trim($rawLine), 0, 120),
                        'reason' => 'Gagal parsing baris (format tidak dikenali)',
                    ];
                    continue;
                }

                $hashInput = $parsed['date']->format('Y-m-d')
                    . '-' . $parsed['description']
                    . '-' . $parsed['amount']
                    . '-' . $parsed['type']
                    . '-' . $accountId;
                $hash = hash('sha256', $hashInput);

                if (Transaction::where('deduplication_hash', $hash)->exists()) {
                    \App\Models\DuplicateTransaction::create([
                        'import_batch_id' => $batch->id,
                        'bank_account_id' => $accountId,
                        'transaction_date' => $parsed['date'],
                        'description' => $parsed['description'],
                        'amount' => $parsed['amount'],
                        'type' => $parsed['type'],
                        'raw_data' => $parsed['raw_data'] ?? null,
                        'deduplication_hash' => $hash,
                        'status' => 'PENDING'
                    ]);
                    $duplicateCount++;
                    continue;
                }

                $classification = $this->classifier->classify($parsed['description'], $parsed['type'], $accountId);

                Transaction::create([
                    'import_batch_id' => $batch->id,
                    'bank_account_id' => $accountId,
                    'transaction_date' => $parsed['date'],
                    'description' => $parsed['description'],
                    'amount' => $parsed['amount'],
                    'type' => $parsed['type'],
                    'category_id' => $classification['category_id'],
                    'classification_method' => $classification['method'],
                    'confidence_score' => $classification['confidence'],
                    'raw_data' => [
                        'raw_line' => $rawLine,
                        'cabang' => $parsed['cabang'] ?? null,
                        'saldo' => $parsed['saldo'] ?? null,
                    ],
                    'deduplication_hash' => $hash,
                ]);

                $successCount++;
            } catch (\Exception $e) {
                Log::warning('Row parse failed: ' . $e->getMessage(), ['line' => $rawLine]);
                $failedCount++;
                $failedRows[] = [
                    'row' => $rowIndex + 1,
                    'line' => mb_substr(trim($rawLine), 0, 120),
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $batch->update([
            'success_rows' => $successCount,
            'failed_rows' => $failedCount,
            'duplicate_rows' => $duplicateCount,
            'status' => ($successCount === 0 && $totalRows > 0) ? 'FAILED' : 'COMPLETED',
        ]);

        return [
            'batch_id' => $batch->id,
            'bank_format' => $bankFormat . ' Mutasi Rekening',
            'periode' => $periode,
            'total_rows' => $totalRows,
            'success_rows' => $successCount,
            'failed_rows' => $failedCount,
            'duplicate_rows' => $duplicateCount,
            'failed_details' => $failedRows,
            'status' => $batch->status,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  BCA ROW PARSERS (preserved from original)
    // ═══════════════════════════════════════════════════════════════

    private function parseBcaRow(string $rawLine, string $year): ?array
    {
        $line = trim($rawLine);
        if (str_starts_with($line, '"') && str_ends_with($line, '"')) {
            $line = substr($line, 1, -1);
        }

        $firstSep = strpos($line, ',""');
        if ($firstSep === false) {
            return $this->parsePlainCsvRow($line, $year);
        }

        $dateStr = trim(substr($line, 0, $firstSep));
        $rest = substr($line, $firstSep + 3);
        $parts = explode('","', $rest);
        $lastIdx = count($parts) - 1;
        if ($lastIdx >= 0) $parts[$lastIdx] = rtrim($parts[$lastIdx], '"');
        $fields = array_map(fn($f) => trim(trim($f), '"'), $parts);

        if (count($fields) < 2) return null;

        $description = $fields[0] ?? '';
        $cabang = $fields[1] ?? '';
        $jumlahRaw = $fields[2] ?? '';
        $saldoRaw = $fields[3] ?? '';

        // Parse date
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $dateStr, $dm)) {
            $dateStr = $dm[1] . '/' . $dm[2] . '/' . $year;
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $dateStr, $dmFull)) {
            $fullYear = strlen($dmFull[3]) === 2 ? '20' . $dmFull[3] : $dmFull[3];
            $dateStr = $dmFull[1] . '/' . $dmFull[2] . '/' . $fullYear;
        } else {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $dateStr)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }

        $description = trim(preg_replace('/\s+/', ' ', $description));
        if (empty($description)) return null;

        // Parse amount + CR/DB
        $jumlahRaw = trim($jumlahRaw);
        $type = null;
        if (preg_match('/\b(CR|DB)\s*$/i', $jumlahRaw, $typeMatch)) {
            $indicator = strtoupper($typeMatch[1]);
            $type = ($indicator === 'CR') ? 'DEBIT' : 'CREDIT';
            $amountStr = trim(substr($jumlahRaw, 0, -strlen($typeMatch[0])));
        } else {
            if (stripos($description, ' CR ') !== false || str_starts_with(strtoupper($description), 'TRSF E-BANKING CR')) {
                $type = 'DEBIT';
            } else {
                $type = 'CREDIT';
            }
            $amountStr = $jumlahRaw;
        }

        $amountStr = preg_replace('/[^\d.,]/', '', $amountStr);
        $amountStr = str_replace(',', '', $amountStr);
        $amount = (float) $amountStr;
        if ($amount <= 0) return null;

        $saldo = str_replace(',', '', preg_replace('/[^\d.,]/', '', $saldoRaw));

        return compact('date', 'description', 'amount', 'type', 'cabang', 'saldo');
    }

    private function parsePlainCsvRow(string $line, string $year): ?array
    {
        $fields = str_getcsv($line);
        if (count($fields) < 4) return null;

        $dateStr = trim($fields[0]);
        $description = trim($fields[1] ?? '');
        $jumlahRaw = trim($fields[3] ?? $fields[2] ?? '');

        if (empty($dateStr) || empty($description)) return null;

        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $dateStr, $dm)) {
            $dateStr = $dm[1] . '/' . $dm[2] . '/' . $year;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $dateStr)->startOfDay();
        } catch (\Exception $e) {
            try { $date = Carbon::parse($dateStr); } catch (\Exception $e2) { return null; }
        }

        $type = 'CREDIT';
        if (preg_match('/\b(CR|DB)\s*$/i', $jumlahRaw, $m)) {
            $type = strtoupper($m[1]) === 'CR' ? 'DEBIT' : 'CREDIT';
            $jumlahRaw = trim(substr($jumlahRaw, 0, -strlen($m[0])));
        }

        $amount = (float) str_replace(',', '', preg_replace('/[^\d.,]/', '', $jumlahRaw));
        if ($amount <= 0) return null;

        $description = preg_replace('/\s+/', ' ', $description);

        return [
            'date' => $date, 'description' => $description, 'amount' => $amount,
            'type' => $type, 'cabang' => $fields[2] ?? null, 'saldo' => $fields[4] ?? null,
        ];
    }
}
