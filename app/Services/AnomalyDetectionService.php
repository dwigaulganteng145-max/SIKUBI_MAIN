<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AnomalyDetectionService
{
    /**
     * Income Anomaly Detection (Pemasukan)
     *
     * Detects accounts that sent >= 10 million IDR to Bigenmi,
     * either in a single transaction or accumulated across multiple transactions.
     * Groups by sender description keyword to identify unique senders.
     */
    public function detectIncomeAnomalies(?int $bankAccountId = null): array
    {
        $threshold = 10_000_000; // Rp 10 juta

        $query = Transaction::debit(); // DEBIT = money coming IN to Bigenmi
        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        // Group transactions by description to identify senders
        $transactions = $query->with('bankAccount:id,bank_name,account_alias')
            ->orderBy('transaction_date')
            ->get();

        $anomalies = [];

        // Strategy 1: Single transactions >= 10 juta (instant)
        foreach ($transactions as $tx) {
            if ((float) $tx->amount >= $threshold) {
                $anomalies[] = [
                    'type' => 'INCOME',
                    'subtype' => 'INSTANT',
                    'transaction_id' => $tx->id,
                    'severity' => (float) $tx->amount >= 50_000_000 ? 'HIGH' : 'MEDIUM',
                    'score' => min(1.0, (float) $tx->amount / 50_000_000),
                    'amount' => (float) $tx->amount,
                    'reason' => sprintf(
                        'Pemasukan instan %s dalam satu transaksi — melebihi batas Rp 10 juta. Perlu verifikasi sumber dana.',
                        $this->formatRp($tx->amount)
                    ),
                ];
            }
        }

        // Strategy 2: Accumulated from same sender >= 10 juta
        // Group by normalized description (first meaningful keyword)
        $senderGroups = $transactions->groupBy(function ($tx) {
            return $this->normalizeSender($tx->description);
        });

        foreach ($senderGroups as $sender => $txGroup) {
            if ($sender === 'LAINNYA') continue; // Skip generic

            $totalAmount = $txGroup->sum(fn($tx) => (float) $tx->amount);

            if ($totalAmount >= $threshold && $txGroup->count() > 1) {
                // Only flag if NOT already flagged as instant (avoid duplicates)
                $hasInstant = $txGroup->contains(fn($tx) => (float) $tx->amount >= $threshold);
                if ($hasInstant && $txGroup->count() <= 1) continue;

                $representativeTx = $txGroup->sortByDesc('amount')->first();

                $anomalies[] = [
                    'type' => 'INCOME',
                    'subtype' => 'ACCUMULATED',
                    'transaction_id' => $representativeTx->id,
                    'severity' => $totalAmount >= 50_000_000 ? 'HIGH' : 'MEDIUM',
                    'score' => min(1.0, $totalAmount / 50_000_000),
                    'amount' => $totalAmount,
                    'reason' => sprintf(
                        'Akumulasi pemasukan dari "%s" sebesar %s (%d transaksi) — melebihi batas Rp 10 juta. Perlu tinjauan.',
                        $sender,
                        $this->formatRp($totalAmount),
                        $txGroup->count()
                    ),
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Expense Anomaly Detection (Pengeluaran)
     *
     * Detects outgoing transactions to accounts where the total outgoing
     * exceeds the total incoming from that same account.
     * This flags imbalanced relationships (paying more than received).
     */
    public function detectExpenseAnomalies(?int $bankAccountId = null): array
    {
        // Get all transactions grouped by normalized counterparty
        $debitQuery = Transaction::debit(); // Incoming
        $creditQuery = Transaction::credit(); // Outgoing

        if ($bankAccountId) {
            $debitQuery->where('bank_account_id', $bankAccountId);
            $creditQuery->where('bank_account_id', $bankAccountId);
        }

        $incoming = $debitQuery->get();
        $outgoing = $creditQuery->with('bankAccount:id,bank_name,account_alias')->get();

        // Build incoming totals by counterparty
        $incomingByParty = [];
        foreach ($incoming as $tx) {
            $party = $this->normalizeSender($tx->description);
            if ($party === 'LAINNYA') continue;
            $incomingByParty[$party] = ($incomingByParty[$party] ?? 0) + (float) $tx->amount;
        }

        // Build outgoing totals by counterparty
        $outgoingByParty = [];
        $outgoingTxByParty = [];
        foreach ($outgoing as $tx) {
            $party = $this->normalizeSender($tx->description);
            if ($party === 'LAINNYA') continue;
            $outgoingByParty[$party] = ($outgoingByParty[$party] ?? 0) + (float) $tx->amount;
            if (!isset($outgoingTxByParty[$party])) {
                $outgoingTxByParty[$party] = collect();
            }
            $outgoingTxByParty[$party]->push($tx);
        }

        $anomalies = [];

        foreach ($outgoingByParty as $party => $totalOut) {
            $totalIn = $incomingByParty[$party] ?? 0;

            // Flag if outgoing exceeds incoming (paying more than received)
            if ($totalOut > $totalIn && $totalOut >= 1_000_000) {
                $excess = $totalOut - $totalIn;
                $txCollection = $outgoingTxByParty[$party];
                $representativeTx = $txCollection->sortByDesc('amount')->first();
                $txCount = $txCollection->count();

                $severity = 'MEDIUM';
                if ($excess >= 50_000_000) $severity = 'HIGH';
                elseif ($totalIn == 0 && $totalOut >= 10_000_000) $severity = 'HIGH';

                $txCountNote = $txCount > 1
                    ? sprintf(' (total %d transaksi senilai %s)', $txCount, $this->formatRp($totalOut))
                    : '';

                $reason = $totalIn > 0
                    ? sprintf(
                        'Pengeluaran ke "%s"%s melebihi pemasukan dari akun tersebut (%s). Selisih: %s.',
                        $party,
                        $txCountNote,
                        $this->formatRp($totalIn),
                        $this->formatRp($excess)
                    )
                    : sprintf(
                        'Pengeluaran ke "%s"%s tanpa ada pemasukan dari akun tersebut. Perlu verifikasi tujuan.',
                        $party,
                        $txCountNote
                    );

                $anomalies[] = [
                    'type' => 'EXPENSE',
                    'subtype' => 'MISMATCH',
                    'transaction_id' => $representativeTx->id,
                    'severity' => $severity,
                    'score' => $totalIn > 0 ? min(1.0, $excess / $totalOut) : 1.0,
                    'amount' => $totalOut,
                    'reason' => $reason,
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Run full anomaly detection (both income and expense).
     */
    public function runFullDetection(?int $bankAccountId = null): array
    {
        $income = $this->detectIncomeAnomalies($bankAccountId);
        $expense = $this->detectExpenseAnomalies($bankAccountId);

        return array_merge($income, $expense);
    }

    /**
     * Normalize transaction description to extract the actual person/account name.
     * Uses pattern-specific extraction for each bank transaction format.
     */
    private function normalizeSender(string $description): string
    {
        $desc = strtoupper(trim($description));

        // Skip system/fee transactions
        $skipPatterns = ['BIAYA ADM', 'BUNGA', 'PAJAK', 'TARIKAN ATM', 'SETORAN TUNAI', 'BIAYA TXN'];
        foreach ($skipPatterns as $skip) {
            if (str_contains($desc, $skip)) return 'LAINNYA';
        }

        $name = null;

        // Pattern 1: TRSF E-BANKING CR/DB ddmm/FTxxx/WSxxxxx amount NAME
        // e.g. "TRSF E-BANKING CR 0203/FTSCY/WS95031 675000.00 SYAMSUL ARIFIN"
        if (preg_match('/TRSF E-BANKING (?:CR|DB)\s+\S+\s+[\d,.]+\s+(.+)/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        // Pattern 1b: TRSF E-BANKING CR/DB ddmm/FTFVA/WSxxxxx ref/MERCHANT ID
        // e.g. "TRSF E-BANKING DB 0203/FTFVA/WS95051 12208/SHOPEEPAY 1334321011"
        if (!$name && preg_match('/TRSF E-BANKING (?:CR|DB)\s+\S+\s+\d+\/(\S+)\s/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        // Pattern 2: BI-FAST CR/DB TRANSFER DR/KE bankcode NAME [KBB]
        // e.g. "BI-FAST CR TRANSFER DR 002 RUT ITA PUJINARO S"
        // e.g. "BI-FAST DB TRANSFER KE 535 NESYA TANTRI REFYA KBB"
        if (!$name && preg_match('/BI-FAST\s+(?:CR|DB)\s+TRANSFER\s+(?:DR|KE)\s+\d+\s+(.+)/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        // Pattern 3: SWITCHING CR/DB TRF DR/KE bankcode NAME [KBB|NEW BRI MOB]
        // e.g. "SWITCHING CR TRF DR 002 RUT ITA PUJINARO S NEW BRI MOB"
        // e.g. "SWITCHING DB TRF KE 451 SUSKANTI KBB"
        if (!$name && preg_match('/SWITCHING\s+(?:CR|DB)\s+TRF\s+(?:DR|KE)\s+\d+\s+(.+)/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        // Pattern 4: KR OTOMATIS ... @Name
        // e.g. "KR OTOMATIS NTRF@... @Bayar biaya ... @AFR Atik Fadlilah"
        if (!$name && preg_match('/KR OTOMATIS.*@([A-Z][A-Z\s]+)$/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        // Pattern 5: Generic TRSF DB/CR NAME
        if (!$name && preg_match('/TRSF\s+(?:CR|DB)\s+(.+)/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        // Pattern 6: TRANSFER DR/CR NAME
        if (!$name && preg_match('/TRANSFER\s+(?:DR|CR|KE)\s+(.+)/i', $desc, $m)) {
            $name = trim($m[1]);
        }

        if (!$name) return 'LAINNYA';

        // Clean up: remove trailing bank suffixes
        $name = preg_replace('/\s+(KBB|NEW BRI MOB|MOBILE BANKING|M-BANKING|BCA MOBILE|MANDIRI)\s*$/i', '', $name);

        // Remove any trailing commas/dots
        $name = rtrim($name, '.,; ');

        // Remove reference numbers at the start (e.g. "200001012_81385713 25733...")
        $name = preg_replace('/^[\d_]+\s+[\d]+\s+/', '', $name);

        // Remove amounts embedded in name (e.g. leftover from bad parsing)
        $name = preg_replace('/\b\d{1,3}([.,]\d{3})*([.,]\d{2})?\b/', '', $name);

        // Clean whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name ?: 'LAINNYA';
    }

    private function formatRp(float $amount): string
    {
        return 'Rp ' . number_format(round($amount), 0, ',', '.');
    }
}
