<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load(__DIR__ . '/rekap_test.xlsx');
$sheet = $spreadsheet->getSheetByName('Transaksi Tunai');

echo "Title: " . $sheet->getTitle() . "\n";
echo "Row 4:\n";
echo "  D4 (Debet): " . $sheet->getCell('D4')->getValue() . "\n";
echo "  E4 (Non Penjualan): " . $sheet->getCell('E4')->getValue() . "\n";
echo "  F4 (Kredit): " . $sheet->getCell('F4')->getValue() . "\n";
echo "  G4 (Saldo): " . $sheet->getCell('G4')->getValue() . "\n";

echo "Row 7:\n";
echo "  A7 (No): " . $sheet->getCell('A7')->getValue() . "\n";
echo "  B7 (Tgl): " . $sheet->getCell('B7')->getValue() . "\n";
echo "  C7 (Keterangan): " . $sheet->getCell('C7')->getValue() . "\n";
echo "  D7 (Debet): " . $sheet->getCell('D7')->getValue() . "\n";
echo "  E7 (Non Penjualan): " . $sheet->getCell('E7')->getValue() . "\n";
echo "  F7 (Kredit): " . $sheet->getCell('F7')->getValue() . "\n";
echo "  G7 (Saldo): " . $sheet->getCell('G7')->getValue() . "\n";

echo "Row 8:\n";
echo "  A8 (No): " . $sheet->getCell('A8')->getValue() . "\n";
echo "  B8 (Tgl): " . $sheet->getCell('B8')->getValue() . "\n";
echo "  C8 (Keterangan): " . $sheet->getCell('C8')->getValue() . "\n";
echo "  D8 (Debet): " . $sheet->getCell('D8')->getValue() . "\n";
echo "  E8 (Non Penjualan): " . $sheet->getCell('E8')->getValue() . "\n";
echo "  F8 (Kredit): " . $sheet->getCell('F8')->getValue() . "\n";
echo "  G8 (Saldo): " . $sheet->getCell('G8')->getValue() . "\n";
