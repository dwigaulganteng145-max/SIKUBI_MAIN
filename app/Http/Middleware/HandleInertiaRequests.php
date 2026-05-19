<?php

namespace App\Http\Middleware;

use App\Models\AnomalyFlag;
use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'permissions' => [
                'canImport' => $user?->isAdmin() ?? false,
                'canManageAccounts' => $user?->isAdmin() ?? false,
                'canManageSettings' => $user?->isAdmin() ?? false,
                'canDetectAnomalies' => $user?->isAdmin() ?? false,
                'canManageUsers' => $user?->isDirektur() ?? false,
                'canEditTransactions' => $user?->isAdmin() ?? false,
            ],
            'notifications' => fn () => $user ? $this->getNotifications($user) : ['items' => [], 'unread_count' => 0],
            'flash' => [
                'importResult' => fn () => $request->session()->get('importResult'),
                'detectResult' => fn () => $request->session()->get('detectResult'),
            ],
        ];
    }

    private function getNotifications($user): array
    {
        $items = [];
        // Only admin gets anomaly & import notifications
        if ($user->isAdmin()) {
            // 1. Unreviewed anomalies (HIGH first)
            $unreviewedAnomalies = AnomalyFlag::where('is_reviewed', false)
                ->where('is_dismissed', false)
                ->orderByDesc('detected_at')
                ->limit(5)
                ->with(['transaction:id,description,amount,type'])
                ->get();

            foreach ($unreviewedAnomalies as $flag) {
                $items[] = [
                    'id' => 'anomaly_' . $flag->id,
                    'type' => 'anomaly',
                    'severity' => $flag->severity,
                    'title' => $flag->severity === 'HIGH' ? 'Anomali Kritis Terdeteksi' : 'Anomali Terdeteksi',
                    'message' => mb_substr($flag->reason, 0, 80) . (mb_strlen($flag->reason) > 80 ? '...' : ''),
                    'url' => '/anomalies',
                    'time' => $flag->detected_at?->toISOString(),
                    'read' => false,
                ];
            }

            // 2. Recent imports (last 24h)
            $recentImports = ImportBatch::where('created_at', '>=', now()->subDay())
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            foreach ($recentImports as $batch) {
                $items[] = [
                    'id' => 'import_' . $batch->id,
                    'type' => 'import',
                    'severity' => 'INFO',
                    'title' => 'Import Data Selesai',
                    'message' => "{$batch->success_rows} transaksi berhasil diimport.",
                    'url' => '/import',
                    'time' => $batch->created_at?->toISOString(),
                    'read' => true,
                ];
            }
        }

        // Sort by time desc
        usort($items, fn($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        $unreadCount = collect($items)->where('read', false)->count();

        return [
            'items' => array_slice($items, 0, 8),
            'unread_count' => $unreadCount,
        ];
    }
}
