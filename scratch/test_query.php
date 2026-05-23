<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BankAccount;
use App\Models\Transaction;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$month = 5;
$year = 2026;
$startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
$endDate = $startDate->copy()->endOfMonth();

$spreadsheet = new Spreadsheet();
$sheetsToExport = [];

// Simulate full recapExcel sheets selection (All Accounts)
$accounts = BankAccount::orderBy('id', 'asc')->get();
foreach ($accounts as $acc) {
    $sheetsToExport[] = [
        'type' => 'bank',
        'id' => $acc->id,
        'name' => $acc->account_alias ?: $acc->bank_name,
        'title' => $acc->account_alias ?: $acc->bank_name,
    ];
}
$sheetsToExport[] = [
    'type' => 'cash',
    'name' => 'Transaksi Tunai',
    'title' => 'Transaksi Tunai',
];

$sheetIndex = 0;
foreach ($sheetsToExport as $item) {
    if ($sheetIndex === 0) {
        $sheet = $spreadsheet->getActiveSheet();
    } else {
        $sheet = $spreadsheet->createSheet();
    }

    $sheetTitle = str_replace(['\\', '/', '?', '*', ':', '[', ']'], '', $item['title']);
    $sheetTitle = substr($sheetTitle, 0, 31);
    $sheet->setTitle($sheetTitle);
    $sheet->setShowGridlines(true);

    if ($item['type'] === 'cash') {
        $inBefore = Transaction::where('transaction_date', '<', $startDate)->whereNull('bank_account_id')->where('source', 'CASH_MANUAL');
        $outBefore = Transaction::where('transaction_date', '<', $startDate)->whereNull('bank_account_id')->where('source', 'CASH_MANUAL');
        $saldoAwal = $inBefore->where('type', 'DEBIT')->sum('amount') - $outBefore->where('type', 'CREDIT')->sum('amount');
    } else {
        $inBefore = Transaction::where('transaction_date', '<', $startDate)->where('bank_account_id', $item['id']);
        $outBefore = Transaction::where('transaction_date', '<', $startDate)->where('bank_account_id', $item['id']);
        $saldoAwal = $inBefore->where('type', 'DEBIT')->sum('amount') - $outBefore->where('type', 'CREDIT')->sum('amount');
    }

    if ($item['type'] === 'cash') {
        $query = Transaction::with(['category', 'bankAccount'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNull('bank_account_id')
            ->where('source', 'CASH_MANUAL');
    } else {
        $query = Transaction::with(['category', 'bankAccount'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('bank_account_id', $item['id']);
    }

    $transactions = $query->get()->sortBy(function ($tx) {
        return $tx->transaction_date->format('Y-m-d') . '_' . $tx->id;
    })->values();

    $accountName = "REKAP " . strtoupper($item['name']);
    $sheet->setCellValue('A1', $accountName);
    $sheet->setCellValue('A2', "Period: Mei 2026");

    // Sidebar Summary Table
    $sheet->setCellValue('I4', 'Saldo Awal');
    $sheet->setCellValue('J4', $saldoAwal);
    $sheet->setCellValue('I5', 'Total Debet');
    $sheet->setCellValue('J5', '=D4+E4');
    $sheet->setCellValue('I6', 'Total Kredit');
    $sheet->setCellValue('J6', '=F4');
    $sheet->setCellValue('I7', 'Saldo Akhir');
    $sheet->setCellValue('J7', '=J4+J5-J6');

    // Populate Data rows
    $startRow = 7;
    $currentRow = $startRow;
    $globalIndex = 1;

    foreach ($transactions as $tx) {
        $sheet->setCellValue('A' . $currentRow, $globalIndex++);
        $sheet->setCellValue('B' . $currentRow, $tx->transaction_date->format('d/m/Y'));
        
        // Clean description
        $categoryName = $tx->category ? $tx->category->name : 'Transaksi';
        $sheet->setCellValue('C' . $currentRow, $categoryName . ' - ' . strtoupper($tx->description));

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
            $catNameUpper !== '' && (
                str_contains($catNameUpper, 'BUNGA') ||
                str_contains($catNameUpper, 'PENDAPATAN LAINNYA') ||
                str_contains($catNameUpper, 'LAIN-LAIN')
            )) {
            $isNonPenjualan = true;
        }

        if ($tx->type === 'DEBIT') {
            if (!$isNonPenjualan) {
                $sheet->setCellValue('D' . $currentRow, $tx->amount);
                $sheet->setCellValue('E' . $currentRow, '');
            } else {
                $sheet->setCellValue('D' . $currentRow, '');
                $sheet->setCellValue('E' . $currentRow, $tx->amount);
            }
            $sheet->setCellValue('F' . $currentRow, '');
        } else {
            $sheet->setCellValue('D' . $currentRow, '');
            $sheet->setCellValue('E' . $currentRow, '');
            $sheet->setCellValue('F' . $currentRow, $tx->amount);
        }

        if ($currentRow === $startRow) {
            $sheet->setCellValue('G' . $currentRow, '=J4+D' . $currentRow . '+E' . $currentRow . '-F' . $currentRow);
        } else {
            $sheet->setCellValue('G' . $currentRow, '=G' . ($currentRow - 1) . '+D' . $currentRow . '+E' . $currentRow . '-F' . $currentRow);
        }

        $currentRow++;
    }

    $lastRow = $currentRow - 1;
    if ($lastRow >= $startRow) {
        $sheet->setCellValue('D4', '=SUM(D7:D' . $lastRow . ')');
        $sheet->setCellValue('E4', '=SUM(E7:E' . $lastRow . ')');
        $sheet->setCellValue('F4', '=SUM(F7:F' . $lastRow . ')');
        $sheet->setCellValue('G4', '=J4+D4+E4-F4');
    } else {
        $sheet->setCellValue('D4', 0);
        $sheet->setCellValue('E4', 0);
        $sheet->setCellValue('F4', 0);
        $sheet->setCellValue('G4', '=J4');
    }

    echo "Sheet processed: " . $item['title'] . " | Rows populated to: " . $lastRow . " | Tx Count: " . count($transactions) . "\n";
    $sheetIndex++;
}

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__ . '/rekap_test.xlsx');
echo "File saved successfully to scratch/rekap_test.xlsx!\n";
