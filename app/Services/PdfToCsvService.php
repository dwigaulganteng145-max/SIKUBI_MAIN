<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Converts raw PDF bank statement text into BCA-compatible CSV format.
 *
 * Output format matches exactly:
 *   Periode : DD/MM/YYYY - DD/MM/YYYY
 *   Tanggal Transaksi,"Keterangan","Cabang","Jumlah","Saldo"
 *   DD/MM,""DESCRIPTION"",""BRANCH"",""AMOUNT CR/DB"",""BALANCE""
 */
class PdfToCsvService
{
    /**
     * Convert PDF text to BCA CSV format.
     *
     * @return array{csv: string, rows: array, total_rows: int, periode: string}
     */
    public function convertToCsv(string $pdfText): array
    {
        $lines = preg_split('/\r?\n/', $pdfText);
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));

        Log::info('PdfToCsvService: processing PDF text', ['lines' => count($lines)]);

        // Try to extract transactions
        $transactions = $this->extractTransactions($lines);

        if (empty($transactions)) {
            Log::warning('PdfToCsvService: no transactions found');
            return ['csv' => '', 'rows' => [], 'total_rows' => 0, 'periode' => ''];
        }

        // Determine period from first/last transaction dates
        $dates = array_map(fn($t) => $t['date'], $transactions);
        $minDate = min($dates);
        $maxDate = max($dates);
        $periode = Carbon::parse($minDate)->format('d/m/Y') . ' - ' . Carbon::parse($maxDate)->format('d/m/Y');

        // Build CSV
        $csvLines = [];
        $csvLines[] = 'Periode : ' . $periode;
        $csvLines[] = 'Tanggal Transaksi,"Keterangan","Cabang","Jumlah","Saldo"';

        $previewRows = [];

        foreach ($transactions as $tx) {
            $date = Carbon::parse($tx['date'])->format('d/m');
            $desc = str_replace('"', "'", $tx['description']);
            $branch = $tx['branch'] ?? '0000';
            $amount = number_format($tx['amount'], 2, '.', ',');
            $indicator = $tx['type'] === 'DEBIT' ? 'CR' : 'DB';
            $saldo = isset($tx['balance']) && $tx['balance'] > 0
                ? number_format($tx['balance'], 2, '.', ',')
                : '0.00';

            $csvLine = sprintf(
                '%s,""%s"",""%s"",""%s %s"",""%s""',
                $date, $desc, $branch, $amount, $indicator, $saldo
            );
            $csvLines[] = $csvLine;

            $previewRows[] = [
                'date' => Carbon::parse($tx['date'])->format('d/m/Y'),
                'description' => $tx['description'],
                'amount' => $tx['amount'],
                'type' => $tx['type'],
                'balance' => $tx['balance'] ?? 0,
            ];
        }

        $csv = implode("\n", $csvLines);

        return [
            'csv' => $csv,
            'rows' => $previewRows,
            'total_rows' => count($transactions),
            'periode' => $periode,
        ];
    }

    /**
     * Extract transactions from PDF text lines.
     * Handles multiple bank statement PDF formats.
     */
    private function extractTransactions(array $lines): array
    {
        // Try BCA PDF format first
        $transactions = $this->tryBcaPdf($lines);
        if (!empty($transactions)) {
            Log::info('PdfToCsvService: detected BCA PDF format', ['count' => count($transactions)]);
            return $transactions;
        }

        // Try Mandiri PDF format
        $transactions = $this->tryMandiriPdf($lines);
        if (!empty($transactions)) {
            Log::info('PdfToCsvService: detected Mandiri PDF format', ['count' => count($transactions)]);
            return $transactions;
        }

        // Try BRI PDF format
        $transactions = $this->tryBriPdf($lines);
        if (!empty($transactions)) {
            Log::info('PdfToCsvService: detected BRI PDF format', ['count' => count($transactions)]);
            return $transactions;
        }

        // Generic fallback: look for any date + amount patterns
        $transactions = $this->tryGenericPdf($lines);
        if (!empty($transactions)) {
            Log::info('PdfToCsvService: detected generic PDF format', ['count' => count($transactions)]);
        }

        return $transactions;
    }

    /**
     * BCA PDF statement format.
     * Pattern: DD/MM DESCRIPTION AMOUNT DB/CR BALANCE
     */
    private function tryBcaPdf(array $lines): array
    {
        $transactions = [];
        $year = date('Y');
        $inData = false;

        // Detect year from period info
        foreach ($lines as $line) {
            if (preg_match('/Periode\s*:?\s*\d{1,2}[\/-]\d{1,2}[\/-](\d{4})/i', $line, $m)) {
                $year = $m[1];
                break;
            }
            if (preg_match('/\b(20\d{2})\b/', $line, $m)) {
                $year = $m[1];
            }
        }

        // Look for BCA indicators
        $headerArea = strtoupper(implode(' ', array_slice($lines, 0, min(20, count($lines)))));
        $isBca = str_contains($headerArea, 'BCA') ||
                 str_contains($headerArea, 'TANGGAL') ||
                 str_contains($headerArea, 'MUTASI');

        if (!$isBca) return [];

        $currentDesc = '';
        $currentDate = null;

        foreach ($lines as $line) {
            // Skip header/footer lines
            $upper = strtoupper($line);
            if (str_contains($upper, 'TANGGAL') && str_contains($upper, 'KETERANGAN')) {
                $inData = true;
                continue;
            }
            if (str_contains($upper, 'SALDO AWAL') || str_contains($upper, 'SALDO AKHIR') ||
                str_contains($upper, 'MUTASI DEBET') || str_contains($upper, 'MUTASI KREDIT')) {
                continue;
            }

            if (!$inData && !preg_match('/^\d{2}\/\d{2}/', $line)) {
                continue;
            }
            $inData = true;

            // Match: DD/MM  DESCRIPTION  AMOUNT  DB/CR  BALANCE
            if (preg_match('/^(\d{2}\/\d{2})\s+(.+?)\s+([\d,.]+)\s+(DB|CR)\s+([\d,.]+)$/i', $line, $m)) {
                $date = $this->parseDate($m[1], $year);
                if (!$date) continue;

                $transactions[] = [
                    'date' => $date,
                    'description' => trim($m[2]),
                    'amount' => $this->cleanAmount($m[3]),
                    'type' => strtoupper($m[4]) === 'CR' ? 'DEBIT' : 'CREDIT',
                    'balance' => $this->cleanAmount($m[5]),
                    'branch' => '0000',
                ];
                continue;
            }

            // BCA variant: date + description on one line, amount on next
            if (preg_match('/^(\d{2}\/\d{2})\s+(.+)$/i', $line, $m)) {
                $currentDate = $this->parseDate($m[1], $year);
                $currentDesc = trim($m[2]);

                // Check if amount is in the same line at the end
                if (preg_match('/^(.+?)\s+([\d,.]+)\s*(DB|CR)?\s*([\d,.]+)?$/i', $currentDesc, $am)) {
                    if ($this->cleanAmount($am[2]) > 0) {
                        $type = 'CREDIT';
                        if (!empty($am[3])) {
                            $type = strtoupper($am[3]) === 'CR' ? 'DEBIT' : 'CREDIT';
                        }
                        $transactions[] = [
                            'date' => $currentDate,
                            'description' => trim($am[1]),
                            'amount' => $this->cleanAmount($am[2]),
                            'type' => $type,
                            'balance' => !empty($am[4]) ? $this->cleanAmount($am[4]) : 0,
                            'branch' => '0000',
                        ];
                        $currentDate = null;
                        $currentDesc = '';
                    }
                }
                continue;
            }

            // Continuation line: amount after the description
            if ($currentDate && preg_match('/^\s*([\d,.]+)\s+(DB|CR)\s+([\d,.]+)\s*$/i', $line, $m)) {
                $transactions[] = [
                    'date' => $currentDate,
                    'description' => $currentDesc,
                    'amount' => $this->cleanAmount($m[1]),
                    'type' => strtoupper($m[2]) === 'CR' ? 'DEBIT' : 'CREDIT',
                    'balance' => $this->cleanAmount($m[3]),
                    'branch' => '0000',
                ];
                $currentDate = null;
                $currentDesc = '';
                continue;
            }

            // Append continuation description lines
            if ($currentDate && !preg_match('/^\d{2}\/\d{2}/', $line)) {
                $currentDesc .= ' ' . trim($line);
            }
        }

        return $transactions;
    }

    /**
     * Mandiri PDF statement format (Livin' by Mandiri / Internet Banking).
     * Real format uses multi-line blocks:
     *   DD Mon YYYY,
     *   HH:MM:SS
     *   description lines...
     *   DEBIT CREDIT BALANCE  (amounts with commas)
     */
    private function tryMandiriPdf(array $lines): array
    {
        $transactions = [];
        $headerArea = strtoupper(implode(' ', array_slice($lines, 0, min(30, count($lines)))));
        $isMandiri = str_contains($headerArea, 'MANDIRI') ||
                     str_contains($headerArea, 'PT BANK MANDIRI') ||
                     str_contains($headerArea, 'ACCOUNT STATEMENT') ||
                     str_contains($headerArea, 'LIVIN');

        if (!$isMandiri) return [];

        $inData = false;
        $currentDate = null;
        $currentDesc = [];
        $pendingTx = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $upper = strtoupper($line);

            // Detect data section start
            if ((str_contains($upper, 'POSTING DATE') || str_contains($upper, 'REFERENCE NO')) &&
                (str_contains($upper, 'DEBIT') || str_contains($upper, 'CREDIT') || str_contains($upper, 'BALANCE'))) {
                $inData = true;
                continue;
            }
            if (str_contains($upper, 'CREDITREFERENCE') || str_contains($upper, 'DEBITBALANCEPOSTING')) {
                $inData = true;
                continue;
            }

            if (!$inData) continue;

            // Skip summary lines
            if (preg_match('/^(Opening|Closing)\s*Balance/i', $line)) continue;
            if (preg_match('/^(Total\s+Amount|No\.\s+of)/i', $line)) continue;
            if (preg_match('/^(Account\s+No|Period|Currency|Branch)/i', $line)) continue;

            // Date line: "03 Mar 2026," or "03 Mar 2026"
            if (preg_match('/^(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4}),?\s*$/i', $line, $m)) {
                if ($pendingTx) {
                    $transactions[] = $pendingTx;
                    $pendingTx = null;
                }
                try {
                    $currentDate = Carbon::createFromFormat('d M Y', $m[1] . ' ' . $m[2] . ' ' . $m[3])->format('Y-m-d');
                } catch (\Exception $e) {
                    $currentDate = null;
                }
                $currentDesc = [];
                continue;
            }

            // Time line: "02:28:56"
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $line)) continue;

            // Amount line: "0.00 880,000.00 910,363.64"
            if ($currentDate && preg_match('/([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)\s*$/', $line, $am)) {
                $debit = $this->cleanAmount($am[1]);
                $credit = $this->cleanAmount($am[2]);
                $balance = $this->cleanAmount($am[3]);

                $prefix = trim(preg_replace('/([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)\s*$/', '', $line));
                if (!empty($prefix) && $prefix !== '-') $currentDesc[] = $prefix;

                $desc = implode(' ', $currentDesc);
                $desc = preg_replace('/\s+/', ' ', trim($desc));
                $desc = preg_replace('/^-\s*/', '', $desc);
                if (empty($desc)) $desc = 'Transaksi Mandiri';

                if ($credit > 0) {
                    $pendingTx = ['date' => $currentDate, 'description' => $desc, 'amount' => $credit, 'type' => 'DEBIT', 'balance' => $balance, 'branch' => '0000'];
                } elseif ($debit > 0) {
                    $pendingTx = ['date' => $currentDate, 'description' => $desc, 'amount' => $debit, 'type' => 'CREDIT', 'balance' => $balance, 'branch' => '0000'];
                }
                $currentDesc = [];
                continue;
            }

            // Description line
            if ($currentDate && !empty(trim($line))) {
                $trimmed = trim($line);
                if ($trimmed !== '-' && !preg_match('/^[\d,.]+$/', $trimmed)) {
                    $currentDesc[] = $trimmed;
                }
            }
        }

        if ($pendingTx) $transactions[] = $pendingTx;
        return $transactions;
    }



    /**
     * BRI PDF statement format.
     * Pattern: DD Mon YYYY, HH:MM:SS  REMARK  DEBIT  CREDIT  BALANCE
     */
    private function tryBriPdf(array $lines): array
    {
        $transactions = [];
        $headerArea = strtoupper(implode(' ', array_slice($lines, 0, min(20, count($lines)))));
        $isBri = str_contains($headerArea, 'BRI') ||
                 str_contains($headerArea, 'BANK RAKYAT') ||
                 str_contains($headerArea, 'POSTING DATE');

        if (!$isBri) return [];

        foreach ($lines as $line) {
            // BRI date: "03 Mar 2026, 02:28:56  REMARK  DEBIT  CREDIT  BALANCE"
            if (preg_match('/^(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}),?\s*[\d:]*\s+(.+)/i', $line, $m)) {
                $dateStr = trim($m[1]);
                $rest = trim($m[2]);

                try {
                    $date = Carbon::createFromFormat('d M Y', $dateStr)->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }

                // Try to split rest into: description, debit, credit, balance
                // Pattern: description  amount1  amount2  amount3
                if (preg_match('/^(.+?)\s+([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)\s*$/', $rest, $am)) {
                    $desc = trim($am[1]);
                    $debit = $this->cleanAmount($am[2]);
                    $credit = $this->cleanAmount($am[3]);
                    $balance = $this->cleanAmount($am[4]);

                    if ($credit > 0) {
                        $transactions[] = [
                            'date' => $date,
                            'description' => $desc,
                            'amount' => $credit,
                            'type' => 'DEBIT', // credit = money in
                            'balance' => $balance,
                            'branch' => '0000',
                        ];
                    } elseif ($debit > 0) {
                        $transactions[] = [
                            'date' => $date,
                            'description' => $desc,
                            'amount' => $debit,
                            'type' => 'CREDIT', // debit = money out
                            'balance' => $balance,
                            'branch' => '0000',
                        ];
                    }
                    continue;
                }

                // Simpler: description  amount
                if (preg_match('/^(.+?)\s+([\d,.]+)\s*$/', $rest, $am)) {
                    $desc = trim($am[1]);
                    $amount = $this->cleanAmount($am[2]);
                    if ($amount > 0) {
                        $transactions[] = [
                            'date' => $date,
                            'description' => $desc,
                            'amount' => $amount,
                            'type' => $this->guessType($desc, $amount),
                            'balance' => 0,
                            'branch' => '0000',
                        ];
                    }
                }
            }
        }

        return $transactions;
    }

    /**
     * Generic fallback: look for any lines with dates and amounts.
     */
    private function tryGenericPdf(array $lines): array
    {
        $transactions = [];

        foreach ($lines as $line) {
            // Pattern 1: DD/MM/YYYY ... amount
            if (preg_match('/(\d{2}[\/-]\d{2}[\/-]\d{2,4})\s+(.+?)\s+([\d.,]+(?:\.\d{2})?)\s*(?:(CR|DB))?\s*(?:([\d.,]+(?:\.\d{2})?))?\s*$/i', $line, $m)) {
                $date = $this->parseDateFull($m[1]);
                if (!$date) continue;

                $desc = trim($m[2]);
                $amount = $this->cleanAmount($m[3]);
                if ($amount <= 0 || $amount < 100) continue; // Filter noise

                $type = 'CREDIT';
                if (!empty($m[4])) {
                    $type = strtoupper($m[4]) === 'CR' ? 'DEBIT' : 'CREDIT';
                } else {
                    $type = $this->guessType($desc, $amount);
                }

                $balance = !empty($m[5]) ? $this->cleanAmount($m[5]) : 0;

                // Filter out non-transaction lines
                if (strlen($desc) < 3) continue;

                $transactions[] = [
                    'date' => $date,
                    'description' => preg_replace('/\s+/', ' ', $desc),
                    'amount' => $amount,
                    'type' => $type,
                    'balance' => $balance,
                    'branch' => '0000',
                ];
            }

            // Pattern 2: DD-Mon-YYYY ... amount
            if (preg_match('/(\d{1,2}[\s-](?:Jan|Feb|Mar|Apr|Mei|May|Jun|Jul|Agu|Aug|Sep|Okt|Oct|Nov|Des|Dec)[\s-]\d{2,4})\s+(.+?)\s+([\d.,]+)\s*$/i', $line, $m)) {
                try {
                    $dateStr = str_replace(['Mei', 'Agu', 'Okt', 'Des'], ['May', 'Aug', 'Oct', 'Dec'], $m[1]);
                    $dateStr = preg_replace('/\s+/', ' ', trim($dateStr));
                    $date = Carbon::parse($dateStr)->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }

                $desc = trim($m[2]);
                $amount = $this->cleanAmount($m[3]);
                if ($amount <= 0 || strlen($desc) < 3) continue;

                $transactions[] = [
                    'date' => $date,
                    'description' => preg_replace('/\s+/', ' ', $desc),
                    'amount' => $amount,
                    'type' => $this->guessType($desc, $amount),
                    'balance' => 0,
                    'branch' => '0000',
                ];
            }
        }

        return $transactions;
    }

    // ═══════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════

    private function parseDate(string $ddmm, string $year): ?string
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $ddmm . '/' . $year)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateFull(string $dateStr): ?string
    {
        $dateStr = str_replace('-', '/', $dateStr);
        try {
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
                return Carbon::createFromFormat('d/m/y', $dateStr)->format('Y-m-d');
            }
            return Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    private function cleanAmount(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-') return 0;
        $cleaned = preg_replace('/[^\d.,]/', '', $raw);

        // "1,234,567.89" format (comma = thousands, dot = decimal)
        if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }
        // "1.234.567,89" format (dot = thousands, comma = decimal)
        elseif (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }

    /**
     * Guess transaction type from description keywords.
     */
    private function guessType(string $desc, float $amount): string
    {
        $upper = strtoupper($desc);

        // Incoming (DEBIT in SIKUBI = money in)
        $incomingPatterns = [
            'TRSF CR', 'TRANSFER CR', 'CR ', ' CR',
            'SETORAN', 'SETOR TUNAI',
            'BUNGA', 'INTEREST',
            'REVERSAL DB', 'REV DB',
            'INCOMING', 'MASUK',
        ];

        foreach ($incomingPatterns as $pattern) {
            if (str_contains($upper, $pattern)) {
                return 'DEBIT';
            }
        }

        // Outgoing (CREDIT in SIKUBI = money out)
        $outgoingPatterns = [
            'TRSF DB', 'TRANSFER DB', 'DB ',
            'PEMBAYARAN', 'PAYMENT',
            'BIAYA', 'FEE', 'ADMIN',
            'TARIKAN', 'PENARIKAN', 'WITHDRAWAL',
            'PAJAK', 'TAX',
        ];

        foreach ($outgoingPatterns as $pattern) {
            if (str_contains($upper, $pattern)) {
                return 'CREDIT';
            }
        }

        return 'CREDIT'; // default to outgoing
    }
}
