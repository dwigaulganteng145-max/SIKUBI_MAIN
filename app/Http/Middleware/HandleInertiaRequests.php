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
                'canImport' => $user?->isAdmin() && ($user->can_import ?? true),
                'canManageAccounts' => $user?->isAdmin() && ($user->can_manage_accounts ?? true),
                'canManageSettings' => $user?->isAdmin() && ($user->can_manage_settings ?? true),
                'canDetectAnomalies' => $user?->isAdmin() && ($user->can_detect_anomalies ?? true),
                'canManageUsers' => $user?->isDirektur() ?? false,
                'canEditTransactions' => $user?->isAdmin() && ($user->can_edit_transactions ?? true),
                'canManageCashTransactions' => $user?->isAdmin() && ($user->can_manage_cash_transactions ?? true),
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
        // 1. Unreviewed anomalies (both Admin & Pimpinan can see this!)
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
                'url' => $user->isDirektur() ? '/anomalies/check' : '/anomalies',
                'time' => $flag->detected_at?->toISOString(),
                'read' => false,
            ];
        }

        // 2. Recent imports (last 24h) - only Admin
        if ($user->isAdmin()) {
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
