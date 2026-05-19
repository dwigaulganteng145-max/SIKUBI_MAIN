<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\AnomalyFlag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportController extends Controller
{
    /**
     * Show the printable monthly recap report page.
     */
    public function printRecap(Request $request)
    {
        $month = $request->input('month');
        $year  = $request->input('year');
        $accountId = $request->input('account_id');

        $accounts = BankAccount::all(['id', 'bank_name', 'account_alias']);

        // If no month/year selected, show selector only
        if (!$month || !$year) {
            return \Inertia\Inertia::render('Reports/PrintRecap', [
                'accounts'     => $accounts,
                'transactions' => null,
                'summary'      => null,
                'filters'      => [
                    'month'      => $month,
                    'year'       => $year,
                    'account_id' => $accountId,
                ],
            ]);
        }

        // Build date range for the selected month/year
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        // Query transactions
        $query = Transaction::with('category:id,name,color,type', 'bankAccount:id,bank_name,account_alias')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($accountId) {
            $query->where('bank_account_id', $accountId);
        }

        $transactions = $query->get()->map(function ($tx, $index) {
            return [
                'no'          => $index + 1,
                'date'        => $tx->transaction_date->format('d/m/Y'),
                'description' => $this->getCleanDescription($tx),
                'type'        => $tx->type === 'DEBIT' ? 'Pendapatan' : 'Pengeluaran',
                'type_raw'    => $tx->type,
                'amount'      => $tx->amount,
                'category'    => $tx->category->name ?? '-',
                'account'     => $tx->bankAccount->account_alias ?? $tx->bankAccount->bank_name ?? '-',
            ];
        });

        // Calculate counts
        $totalDebitCount  = $transactions->where('type_raw', 'DEBIT')->count();
        $totalCreditCount = $transactions->where('type_raw', 'CREDIT')->count();

        // Get categorized breakdown for DEBIT (Income)
        $incomeBreakdown = Transaction::debit()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($accountId, function($q) use ($accountId) {
                $q->where('bank_account_id', $accountId);
            })
            ->select('category_id', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total_amount'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_count'))
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category->name ?? 'Lain-lain / Belum Terklasifikasi',
                    'color' => $item->category->color ?? '#8B5E6B',
                    'amount' => (float)$item->total_amount,
                    'count' => (int)$item->total_count,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        // Get categorized breakdown for CREDIT (Expense)
        $expenseBreakdown = Transaction::credit()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->when($accountId, function($q) use ($accountId) {
                $q->where('bank_account_id', $accountId);
            })
            ->select('category_id', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total_amount'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_count'))
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category->name ?? 'Lain-lain / Belum Terklasifikasi',
                    'color' => $item->category->color ?? '#8B5E6B',
                    'amount' => (float)$item->total_amount,
                    'count' => (int)$item->total_count,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        // Query real anomalies inside this month (ONLY HIGH SEVERITY)
        $anomalyFlags = AnomalyFlag::where('severity', 'HIGH')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate, $accountId) {
                $q->whereBetween('transaction_date', [$startDate, $endDate]);
                if ($accountId) {
                    $q->where('bank_account_id', $accountId);
                }
            })
            ->with('transaction:id,transaction_date,description,amount,type,bank_account_id', 'transaction.bankAccount:id,bank_name,account_alias')
            ->orderByDesc('severity')
            ->get()
            ->map(function($flag) {
                return [
                    'id'           => $flag->id,
                    'severity'     => $flag->severity,
                    'reason'       => $flag->reason,
                    'is_reviewed'  => (bool)$flag->is_reviewed,
                    'is_dismissed' => (bool)$flag->is_dismissed,
                    'review_note'  => $flag->review_note ?: null,
                    'date'         => $flag->transaction ? Carbon::parse($flag->transaction->transaction_date)->format('d/m/Y') : '-',
                    'description'  => $flag->transaction ? $flag->transaction->description : '-',
                    'amount'       => $flag->transaction ? (float)$flag->transaction->amount : 0,
                    'type'         => $flag->transaction ? $flag->transaction->type : '-',
                    'account'      => $flag->transaction && $flag->transaction->bankAccount ? ($flag->transaction->bankAccount->account_alias ?: $flag->transaction->bankAccount->bank_name) : '-',
                ];
            });

        $totalDebit  = $transactions->where('type_raw', 'DEBIT')->sum('amount');
        $totalCredit = $transactions->where('type_raw', 'CREDIT')->sum('amount');

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return \Inertia\Inertia::render('Reports/PrintRecap', [
            'accounts'          => $accounts,
            'transactions'      => $transactions->values(),
            'income_breakdown'  => $incomeBreakdown,
            'expense_breakdown' => $expenseBreakdown,
            'anomalies'         => $anomalyFlags,
            'summary'           => [
                'month_label'        => $monthNames[(int)$month] . ' ' . $year,
                'total_debit'        => round($totalDebit),
                'total_credit'       => round($totalCredit),
                'balance'            => round($totalDebit - $totalCredit),
                'total_debit_count'  => $totalDebitCount,
                'total_credit_count' => $totalCreditCount,
            ],
            'filters' => [
                'month'      => $month,
                'year'       => $year,
                'account_id' => $accountId,
            ],
        ]);
    }

    public function recapCsv(Request $request)
    {
        $accountId = $request->input('account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($dateFrom === 'null' || $dateFrom === '' || !$dateFrom) $dateFrom = null;
        if ($dateTo === 'null' || $dateTo === '' || !$dateTo) $dateTo = null;

        $fileName = 'rekap_mutasi_' . now()->format('Ymd_His') . '.csv';
        
        // Use system temp directory for maximum compatibility
        $tempFile = tempnam(sys_get_temp_dir(), 'sikubi_');
        $out = fopen($tempFile, 'w');
        
        // BOM for Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['LAPORAN REKAP MUTASI KEUANGAN SIKUBI']);
        fputcsv($out, ['Dicetak Pada:', now()->format('Y-m-d H:i:s')]);
        if ($dateFrom || $dateTo) {
            fputcsv($out, ['Periode:', ($dateFrom ?? 'Awal') . ' s/d ' . ($dateTo ?? 'Sekarang')]);
        }
        fputcsv($out, []);

        // Summary Accounts
        fputcsv($out, ['RINGKASAN PER REKENING']);
        fputcsv($out, ['Nama Bank', 'Alias', 'Total Masuk', 'Total Keluar', 'Saldo']);
        
        $accountSummaryQuery = Transaction::query()
            ->select('bank_account_id', 'type', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->groupBy('bank_account_id', 'type');

        if ($accountId) $accountSummaryQuery->where('bank_account_id', $accountId);
        if ($dateFrom) $accountSummaryQuery->where('transaction_date', '>=', $dateFrom);
        if ($dateTo) $accountSummaryQuery->where('transaction_date', '<=', $dateTo);

        $accountTotals = $accountSummaryQuery->get()->groupBy('bank_account_id');
        
        $accounts = BankAccount::query();
        if ($accountId) $accounts->where('id', $accountId);
        
        foreach ($accounts->get() as $acc) {
            $accTotals = $accountTotals->get($acc->id, collect());
            $debit = $accTotals->where('type', 'DEBIT')->first()->total ?? 0;
            $credit = $accTotals->where('type', 'CREDIT')->first()->total ?? 0;
            
            fputcsv($out, [$acc->bank_name, $acc->account_alias ?? '-', $debit, $credit, $debit - $credit]);
        }
        fputcsv($out, []);

        // Summary Categories
        fputcsv($out, ['RINGKASAN PER KATEGORI']);
        fputcsv($out, ['Kategori', 'Tipe', 'Jml Transaksi', 'Total Nominal']);

        $categorySummaryQuery = Transaction::query()
            ->select('category_id', 'type', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'), \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->groupBy('category_id', 'type');

        if ($accountId) $categorySummaryQuery->where('bank_account_id', $accountId);
        if ($dateFrom) $categorySummaryQuery->where('transaction_date', '>=', $dateFrom);
        if ($dateTo) $categorySummaryQuery->where('transaction_date', '<=', $dateTo);

        $categoryTotals = $categorySummaryQuery->get()->groupBy('category_id');

        $categories = Category::all();
        foreach ($categories as $cat) {
            $catTotals = $categoryTotals->get($cat->id, collect());
            foreach ($catTotals as $totalData) {
                if ($totalData->count > 0) {
                    fputcsv($out, [$cat->name, $totalData->type === 'DEBIT' ? 'Masuk' : 'Keluar', $totalData->count, $totalData->total]);
                }
            }
        }
        
        // Also include unclassified transactions
        $unclassifiedTotals = $categoryTotals->get(null, collect());
        foreach ($unclassifiedTotals as $totalData) {
            if ($totalData->count > 0) {
                fputcsv($out, ['Belum Terkategori', $totalData->type === 'DEBIT' ? 'Masuk' : 'Keluar', $totalData->count, $totalData->total]);
            }
        }
        
        fputcsv($out, []);

        // Details
        fputcsv($out, ['DETAIL TRANSAKSI']);
        fputcsv($out, ['Tanggal', 'Deskripsi', 'Kategori', 'Tipe', 'Rekening', 'Jumlah']);

        $txQuery = Transaction::with(['category', 'bankAccount'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
        
        if ($accountId) $txQuery->where('bank_account_id', $accountId);
        if ($dateFrom) $txQuery->where('transaction_date', '>=', $dateFrom);
        if ($dateTo) $txQuery->where('transaction_date', '<=', $dateTo);

        $txQuery->chunk(500, function ($txs) use ($out) {
            foreach ($txs as $tx) {
                fputcsv($out, [
                    $tx->transaction_date->format('Y-m-d'),
                    $tx->description,
                    $tx->category->name ?? 'Unclassified',
                    $tx->type,
                    $tx->bankAccount->account_alias ?? $tx->bankAccount->bank_name ?? '-',
                    $tx->amount
                ]);
            }
        });

        fclose($out);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }

    public function recapExcel(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
        $accountId = $request->input('account_id');

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Generate Excel using PhpSpreadsheet
        $spreadsheet = new Spreadsheet();

        if ($accountId) {
            $accounts = BankAccount::where('id', $accountId)->get();
        } else {
            $accounts = BankAccount::orderBy('id', 'asc')->get();
        }

        // If no bank accounts exist in the system, create a fallback empty sheet
        if ($accounts->isEmpty()) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan');
            $sheet->setShowGridlines(true);
            $sheet->setCellValue('A1', 'Tidak ada data rekening bank yang terdaftar.');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        } else {
            $sheetIndex = 0;
            foreach ($accounts as $acc) {
                if ($sheetIndex === 0) {
                    $sheet = $spreadsheet->getActiveSheet();
                } else {
                    $sheet = $spreadsheet->createSheet();
                }

                // Clean and format sheet title (Max 31 characters, remove forbidden characters)
                $sheetTitle = str_replace(['\\', '/', '?', '*', ':', '[', ']'], '', $acc->account_alias ?: $acc->bank_name);
                $sheetTitle = substr($sheetTitle, 0, 31);
                if (empty($sheetTitle)) {
                    $sheetTitle = 'Rekening ' . ($sheetIndex + 1);
                }
                $sheet->setTitle($sheetTitle);
                $sheet->setShowGridlines(true);

                // Calculate opening balance specifically for this bank account
                $inBefore = Transaction::where('transaction_date', '<', $startDate)->where('bank_account_id', $acc->id);
                $outBefore = Transaction::where('transaction_date', '<', $startDate)->where('bank_account_id', $acc->id);
                $saldoAwal = $inBefore->where('type', 'DEBIT')->sum('amount') - $outBefore->where('type', 'CREDIT')->sum('amount');

                // Query transactions specifically for this bank account
                $query = Transaction::with(['category', 'bankAccount'])
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->where('bank_account_id', $acc->id);

                // Chronological order
                $transactions = $query->get()->sortBy(function ($tx) {
                    return $tx->transaction_date->format('Y-m-d') . '_' . $tx->id;
                })->values();

                // Header Title
                $accountName = "REKAP " . strtoupper($acc->account_alias ?: $acc->bank_name);
                $sheet->setCellValue('A1', $accountName);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $monthLabels = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                ];
                $periodLabel = "Period: " . ($monthLabels[(int)$month] ?? '') . " " . $year;
                $sheet->setCellValue('A2', $periodLabel);
                $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

                // Grid Header setup
                // Top total cells (row 4)
                $sheet->getStyle('D4:G4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');
                $sheet->getStyle('D4:G4')->getFont()->setBold(true);

                // Set up the double-header row 5 & 6
                $headers = [
                    'A5' => 'No.',
                    'B5' => 'Tgl',
                    'C5' => 'Keterangan',
                    'D5' => 'Debet',
                    'F5' => 'Kredit',
                    'G5' => 'Saldo'
                ];

                foreach ($headers as $cell => $val) {
                    $sheet->setCellValue($cell, $val);
                }
                $sheet->setCellValue('D6', 'Penjualan');
                $sheet->setCellValue('E6', 'Non Penjualan');

                // Merges
                $sheet->mergeCells('A5:A6');
                $sheet->mergeCells('B5:B6');
                $sheet->mergeCells('C5:C6');
                $sheet->mergeCells('D5:E5');
                $sheet->mergeCells('F5:F6');
                $sheet->mergeCells('G5:G6');

                // Alignments and styles for headers (row 5 and 6)
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];
                $sheet->getStyle('A5:G6')->applyFromArray($headerStyle);

                // Sidebar Summary Table (Starts at Column I and J)
                $sheet->setCellValue('I4', 'Saldo Awal');
                $sheet->setCellValue('J4', $saldoAwal);
                $sheet->getStyle('J4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('00B0F0'); // Cyan
                $sheet->getStyle('J4')->getFont()->setBold(true);

                $sheet->setCellValue('I5', 'Total Debet');
                $sheet->setCellValue('J5', '=D4+E4');
                $sheet->getStyle('J5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                $sheet->getStyle('J5')->getFont()->setBold(true);

                $sheet->setCellValue('I6', 'Total Kredit');
                $sheet->setCellValue('J6', '=F4');
                $sheet->getStyle('J6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                $sheet->getStyle('J6')->getFont()->setBold(true);

                $sheet->setCellValue('I7', 'Saldo Akhir');
                $sheet->setCellValue('J7', '=J4+J5-J6');
                $sheet->getStyle('J7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                $sheet->getStyle('J7')->getFont()->setBold(true);

                // Borders and styles for Sidebar Summary Table
                $sidebarStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ];
                $sheet->getStyle('I4:J7')->applyFromArray($sidebarStyle);
                // Align left for labels
                $sheet->getStyle('I4:I7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Populate Data rows
                $startRow = 7;
                $currentRow = $startRow;
                $globalIndex = 1;

                foreach ($transactions as $tx) {
                    // Regular Transaction Row
                    $sheet->setCellValue('A' . $currentRow, $globalIndex++);
                    $sheet->setCellValue('B' . $currentRow, $tx->transaction_date->format('d/m/Y'));
                    $sheet->setCellValue('C' . $currentRow, $this->getCleanDescription($tx));

                    // Classification into Penjualan vs Non Penjualan
                    $isNonPenjualan = false;
                    $descUpper = strtoupper($tx->description);
                    $catNameUpper = $tx->category ? strtoupper($tx->category->name) : '';

                    if (str_contains($descUpper, 'BUNGA') || 
                        str_contains($descUpper, 'INTEREST') || 
                        str_contains($descUpper, 'GIRO') || 
                        str_contains($descUpper, 'JASA GIRO') || 
                        str_contains($descUpper, 'REIMBURSE') || 
                        str_contains($descUpper, 'REIMBURS') || 
                        str_contains($descUpper, 'REFUND') ||
                        str_contains($catNameUpper, 'BUNGA') ||
                        str_contains($catNameUpper, 'PENDAPATAN LAINNYA') ||
                        str_contains($catNameUpper, 'LAIN-LAIN')) {
                        $isNonPenjualan = true;
                    }

                    if ($tx->type === 'DEBIT') {
                        if (!$isNonPenjualan) { // Penjualan (Column D)
                            $sheet->setCellValue('D' . $currentRow, $tx->amount);
                            $sheet->setCellValue('E' . $currentRow, '');
                        } else { // Non Penjualan (Column E)
                            $sheet->setCellValue('D' . $currentRow, '');
                            $sheet->setCellValue('E' . $currentRow, $tx->amount);
                        }
                        $sheet->setCellValue('F' . $currentRow, '');
                    } else { // CREDIT (Kredit, Column F)
                        $sheet->setCellValue('D' . $currentRow, '');
                        $sheet->setCellValue('E' . $currentRow, '');
                        $sheet->setCellValue('F' . $currentRow, $tx->amount);
                    }

                    // Running Balance Formula (referencing the Column G above and Debet/Kredit on current row)
                    if ($currentRow === $startRow) {
                        $sheet->setCellValue('G' . $currentRow, '=J4+D' . $currentRow . '+E' . $currentRow . '-F' . $currentRow);
                    } else {
                        $sheet->setCellValue('G' . $currentRow, '=G' . ($currentRow - 1) . '+D' . $currentRow . '+E' . $currentRow . '-F' . $currentRow);
                    }

                    $currentRow++;
                }

                // Total Row Formulas on Row 4
                $lastRow = $currentRow - 1;
                if ($lastRow >= $startRow) {
                    $sheet->setCellValue('D4', '=SUM(D7:D' . $lastRow . ')');
                    $sheet->setCellValue('E4', '=SUM(E7:E' . $lastRow . ')');
                    $sheet->setCellValue('F4', '=SUM(F7:F' . $lastRow . ')');
                    $sheet->setCellValue('G4', '=J4+D4+E4-F4'); // Saldo Akhir
                } else {
                    $sheet->setCellValue('D4', 0);
                    $sheet->setCellValue('E4', 0);
                    $sheet->setCellValue('F4', 0);
                    $sheet->setCellValue('G4', '=J4');
                }

                // Apply grid styling to cells
                $thinBorder = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ];

                // Format for column totals in row 4
                $sheet->getStyle('D4:G4')->applyFromArray($thinBorder);
                $sheet->getStyle('D4:G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($lastRow >= $startRow) {
                    $sheet->getStyle('A7:G' . $lastRow)->applyFromArray($thinBorder);
                    // Centering for No and Date
                    $sheet->getStyle('A7:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('B7:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Number Formats
                // Row 4 Column Totals format (decimals, e.g. 152.752.000,00)
                $sheet->getStyle('D4:G4')->getNumberFormat()->setFormatCode('#,##0.00');

                // Sidebar format (decimals, e.g. 271.563.580,00)
                $sheet->getStyle('J4:J7')->getNumberFormat()->setFormatCode('#,##0.00');

                // Data Rows Formats
                if ($lastRow >= $startRow) {
                    // Columns D and F: "Rp "* #,##0 (prefix Rp, no decimals)
                    $sheet->getStyle('D7:D' . $lastRow)->getNumberFormat()->setFormatCode('"Rp "* #,##0');
                    $sheet->getStyle('F7:F' . $lastRow)->getNumberFormat()->setFormatCode('"Rp "* #,##0');
                    // Column E: #,##0 without Rp prefix as shown in screenshot
                    $sheet->getStyle('E7:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                    // Column G running balance: #,##0.00 (no prefix Rp, with decimals)
                    $sheet->getStyle('G7:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // Explicit Column Widths to prevent A1/A2 titles from stretching Column A
                $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(7);   // "No." column narrow and perfect
                $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(13);  // "Tgl"
                $sheet->getColumnDimension('C')->setAutoSize(true);                 // "Keterangan" - auto-fit fits long text perfectly
                $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(18);  // "Penjualan"
                $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(18);  // "Non Penjualan"
                $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(18);  // "Kredit"
                $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(20);  // "Saldo"
                $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(4);   // Spacer column
                $sheet->getColumnDimension('I')->setAutoSize(true);                 // Sidebar Label
                $sheet->getColumnDimension('J')->setAutoSize(true);                 // Sidebar Value

                $sheetIndex++;
            }
        }

        // Write file and download
        $fileName = 'rekap_excel_' . $year . '_' . sprintf('%02d', $month) . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Clean and format description as: [Category Name] - [Cleaned Sender/Detail Name]
     */
    private function getCleanDescription($tx)
    {
        $categoryName = $tx->category ? $tx->category->name : 'Transaksi';
        $rawDesc = $tx->description;

        // 1. Remove the numeric amount from the description if it exists
        $amount = $tx->amount;
        $amountInt = (int)$amount;
        $amountFloat = (float)$amount;

        $removeTargets = [
            sprintf("%.2f", $amountFloat), 
            sprintf("%.0f", $amountFloat),
            (string)$amountInt,
            number_format($amountFloat, 2, '.', ''),
            number_format($amountFloat, 0, '.', '')
        ];

        $cleaned = $rawDesc;
        foreach ($removeTargets as $target) {
            if (!empty($target) && strlen($target) > 2) {
                $cleaned = str_replace($target, '', $cleaned);
            }
        }

        // 2. Remove standard bank boilerplate codes and noise
        $boilerplate = [
            'TRSF E-BANKING CR',
            'TRSF E-BANKING DB',
            'TRSF E-BANKING',
            'E-BANKING CR',
            'E-BANKING DB',
            'TRANSFER CR',
            'TRANSFER DB',
            'BI-FAST CR',
            'BI-FAST DB',
            'BI-FAST',
            'SWITCHING CR',
            'SWITCHING DB',
            'SWITCHING',
            'WS95081',
            'FTSCY',
            'LPD',
            'CR ',
            'DB ',
        ];

        foreach ($boilerplate as $noise) {
            $cleaned = str_ireplace($noise, '', $cleaned);
        }

        // 3. Remove date patterns and clean symbols
        $cleaned = preg_replace('/\b\d{4}\b/', '', $cleaned);
        $cleaned = preg_replace('/[0-9]{2}\/[0-9]{2}/', '', $cleaned);
        $cleaned = preg_replace('/[\/\-\:\.\,]+/', ' ', $cleaned);

        // 4. Clean up spaces and trim
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        if (strlen($cleaned) > 2) {
            return $categoryName . ' - ' . strtoupper($cleaned);
        }

        return $categoryName;
    }
}
