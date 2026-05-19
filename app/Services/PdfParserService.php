<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class PdfParserService
{
    /**
     * Extract text content from a PDF bank statement.
     * Uses smalot/pdfparser with fallback decryption via qpdf
     * for encrypted PDFs (e.g., Mandiri "Missing catalog" error).
     */
    public function extractText(string $filePath): string
    {
        // Method 1: Direct smalot/pdfparser (works for non-encrypted PDFs)
        try {
            $config = new \Smalot\PdfParser\Config();
            $config->setIgnoreEncryption(true);
            $config->setRetainImageContent(false);

            $parser = new Parser([], $config);
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            if (!empty(trim($text))) {
                Log::info('PDF text extracted via smalot/pdfparser', [
                    'length' => strlen($text),
                    'file' => basename($filePath),
                ]);
                return $text;
            }
        } catch (\Exception $e) {
            Log::warning('smalot/pdfparser failed: ' . $e->getMessage() . '. Trying qpdf decryption fallback...');
        }

        // Method 2: Decrypt with qpdf (bundled binary), then parse
        try {
            $text = $this->extractViaQpdf($filePath);
            if (!empty(trim($text))) {
                Log::info('PDF text extracted via qpdf decryption', [
                    'length' => strlen($text),
                    'file' => basename($filePath),
                ]);
                return $text;
            }
        } catch (\Exception $e) {
            Log::warning('qpdf decryption failed: ' . $e->getMessage());
        }

        throw new \RuntimeException(
            'Gagal membaca file PDF. File mungkin terenkripsi dengan password, rusak, atau berisi gambar (scan). '
            . 'Pastikan PDF berisi teks yang dapat di-copy, bukan hasil scan.'
        );
    }

    /**
     * Decrypt an encrypted PDF using qpdf (bundled binary), then parse with smalot.
     * qpdf can handle PDFs with empty owner passwords (common for bank e-statements).
     */
    private function extractViaQpdf(string $filePath): string
    {
        $qpdfBin = $this->findQpdfBinary();
        if (!$qpdfBin) {
            throw new \RuntimeException('qpdf binary not found. Cannot decrypt PDF.');
        }

        $decryptedPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sikubi_decrypted_' . uniqid() . '.pdf';

        try {
            $escapedBin = escapeshellarg($qpdfBin);
            $escapedIn = escapeshellarg($filePath);
            $escapedOut = escapeshellarg($decryptedPath);

            // qpdf --decrypt removes encryption from the PDF
            // --password="" handles PDFs with empty owner password
            $cmd = "{$escapedBin} --decrypt --password= {$escapedIn} {$escapedOut} 2>&1";

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            $result = implode("\n", $output);

            if ($returnCode !== 0 || !file_exists($decryptedPath) || filesize($decryptedPath) === 0) {
                // Try without password flag (some PDFs have no password at all)
                $cmd2 = "{$escapedBin} --decrypt {$escapedIn} {$escapedOut} 2>&1";
                $output2 = [];
                $returnCode2 = 0;
                exec($cmd2, $output2, $returnCode2);
                $result2 = implode("\n", $output2);

                if ($returnCode2 !== 0 || !file_exists($decryptedPath) || filesize($decryptedPath) === 0) {
                    throw new \RuntimeException('qpdf decryption failed: ' . $result . ' | ' . $result2);
                }
            }

            // Parse the decrypted PDF with smalot/pdfparser
            $config = new \Smalot\PdfParser\Config();
            $config->setRetainImageContent(false);
            $parser = new Parser([], $config);
            $pdf = $parser->parseFile($decryptedPath);
            $text = $pdf->getText();

            return $text;
        } finally {
            // Cleanup temp file
            @unlink($decryptedPath);
        }
    }

    /**
     * Find the qpdf binary. Checks project bin/ folder first, then system PATH.
     */
    private function findQpdfBinary(): ?string
    {
        // Check project bin/ directory first
        $projectBin = base_path('bin' . DIRECTORY_SEPARATOR . 'qpdf.exe');
        if (file_exists($projectBin)) {
            return $projectBin;
        }

        // Check for Linux/Mac binary
        $projectBinUnix = base_path('bin' . DIRECTORY_SEPARATOR . 'qpdf');
        if (file_exists($projectBinUnix)) {
            return $projectBinUnix;
        }

        // Check system PATH
        $output = [];
        $returnCode = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            exec('where qpdf.exe 2>nul', $output, $returnCode);
        } else {
            exec('which qpdf 2>/dev/null', $output, $returnCode);
        }

        if ($returnCode === 0 && !empty($output[0]) && file_exists(trim($output[0]))) {
            return trim($output[0]);
        }

        return null;
    }

    /**
     * Convert raw PDF text into structured lines suitable for the adaptive parser.
     */
    public function convertToLines(string $rawText): array
    {
        $lines = explode("\n", $rawText);
        $cleaned = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $cleaned[] = $trimmed;
            }
        }

        return $cleaned;
    }
}
