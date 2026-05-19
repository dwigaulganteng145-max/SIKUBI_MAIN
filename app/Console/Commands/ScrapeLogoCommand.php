<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ScrapeLogoCommand extends Command
{
    protected $signature = 'app:scrape-logo';
    protected $description = 'Scrape the official Bigenmi logo from bigenmi.co.id';

    public function handle(): int
    {
        $this->info('🔍 Fetching Bigenmi homepage...');

        try {
            $response = Http::timeout(15)->get('https://www.bigenmi.co.id/');

            if (!$response->successful()) {
                $this->error('Failed to fetch homepage: HTTP ' . $response->status());
                return self::FAILURE;
            }

            $html = $response->body();

            // Try to find logo image in header/navbar
            $logoPatterns = [
                '/src=["\']([^"\']*logo[^"\']*\.(?:png|jpg|svg|webp))["\']/',
                '/src=["\']([^"\']*brand[^"\']*\.(?:png|jpg|svg|webp))["\']/',
                '/class=["\'][^"\']*(?:navbar|header|logo)[^"\']*["\'][^>]*src=["\']([^"\']+)["\']/',
                '/<img[^>]+src=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*logo/',
            ];

            $logoUrl = null;
            foreach ($logoPatterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $logoUrl = $matches[1];
                    break;
                }
            }

            // Also try to find from OG or meta tags
            if (!$logoUrl) {
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                    $logoUrl = $matches[1];
                }
            }

            if (!$logoUrl) {
                $this->warn('Could not find logo URL. Using fallback...');
                // Try known paths
                $fallbacks = [
                    'https://www.bigenmi.co.id/assets/images/logo.png',
                    'https://www.bigenmi.co.id/images/logo.png',
                    'https://www.bigenmi.co.id/img/logo.png',
                ];
                foreach ($fallbacks as $url) {
                    $test = Http::timeout(5)->head($url);
                    if ($test->successful()) {
                        $logoUrl = $url;
                        break;
                    }
                }
            }

            if (!$logoUrl) {
                $this->error('No logo found. Please manually place the logo at public/images/bigenmi-logo.png');
                return self::FAILURE;
            }

            // Make absolute URL
            if (!str_starts_with($logoUrl, 'http')) {
                $logoUrl = 'https://www.bigenmi.co.id/' . ltrim($logoUrl, '/');
            }

            $this->info("📥 Downloading logo from: {$logoUrl}");

            $imageResponse = Http::timeout(10)->get($logoUrl);
            if (!$imageResponse->successful()) {
                $this->error('Failed to download logo image.');
                return self::FAILURE;
            }

            $destDir = public_path('images');
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $ext = pathinfo(parse_url($logoUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $destPath = $destDir . '/bigenmi-logo.' . $ext;
            file_put_contents($destPath, $imageResponse->body());

            $this->info("✅ Logo saved to: public/images/bigenmi-logo.{$ext}");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
