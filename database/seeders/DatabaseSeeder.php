<?php

namespace Database\Seeders;

use App\Models\AnomalyFlag;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding SIKUBI database...');

        // ── Users ──
        $users = [
            ['name' => 'Direktur Bigenmi', 'email' => 'direktur@bigenmi.co.id', 'password' => 'Bigenmi@2026', 'role' => 'DIREKTUR'],
            ['name' => 'Admin Keuangan', 'email' => 'admin@bigenmi.co.id', 'password' => 'Admin@2026', 'role' => 'ADMIN_KEUANGAN'],
        ];
        foreach ($users as $u) {
            User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make($u['password']),
                'role' => $u['role'],
            ]);
            $this->command->info("  ✓ User: {$u['email']} ({$u['role']}) — password: {$u['password']}");
        }

        // ── Categories ──
        $categories = [
            // DEBIT = Cash IN (Pemasukan) — BCA: CR
            ['name' => 'Penjualan Langsung', 'type' => 'DEBIT', 'color' => '#10b981', 'icon' => 'cash', 'sort_order' => 1],
            ['name' => 'Online Shop', 'type' => 'DEBIT', 'color' => '#06b6d4', 'icon' => 'cart', 'sort_order' => 2],
            ['name' => 'Transfer Masuk', 'type' => 'DEBIT', 'color' => '#3b82f6', 'icon' => 'arrow-down', 'sort_order' => 3],
            ['name' => 'Penagihan Piutang', 'type' => 'DEBIT', 'color' => '#8b5cf6', 'icon' => 'receipt', 'sort_order' => 4],
            ['name' => 'Bunga Bank', 'type' => 'DEBIT', 'color' => '#f59e0b', 'icon' => 'percent', 'sort_order' => 5],
            ['name' => 'Pendapatan Lainnya', 'type' => 'DEBIT', 'color' => '#64748b', 'icon' => 'plus', 'sort_order' => 6],
            // CREDIT = Cash OUT (Pengeluaran) — BCA: DB
            ['name' => 'Pembelian Produk', 'type' => 'CREDIT', 'color' => '#ef4444', 'icon' => 'box', 'sort_order' => 10],
            ['name' => 'Biaya Operasional', 'type' => 'CREDIT', 'color' => '#f97316', 'icon' => 'wrench', 'sort_order' => 11],
            ['name' => 'Gaji & THR', 'type' => 'CREDIT', 'color' => '#ec4899', 'icon' => 'users', 'sort_order' => 12],
            ['name' => 'Transfer Keluar', 'type' => 'CREDIT', 'color' => '#6366f1', 'icon' => 'arrow-up', 'sort_order' => 13],
            ['name' => 'Admin Bank', 'type' => 'CREDIT', 'color' => '#B76E79', 'icon' => 'file', 'sort_order' => 14],
            ['name' => 'Reward', 'type' => 'CREDIT', 'color' => '#14b8a6', 'icon' => 'gift', 'sort_order' => 15],
            ['name' => 'Withdrawal (WD)', 'type' => 'CREDIT', 'color' => '#a855f7', 'icon' => 'banknotes', 'sort_order' => 16],
            ['name' => 'Pajak', 'type' => 'CREDIT', 'color' => '#dc2626', 'icon' => 'document', 'sort_order' => 17],
            ['name' => 'Logistik', 'type' => 'CREDIT', 'color' => '#84cc16', 'icon' => 'truck', 'sort_order' => 18],
            ['name' => 'Online Shop', 'type' => 'CREDIT', 'color' => '#06b6d4', 'icon' => 'cart', 'sort_order' => 20],
            ['name' => 'Pengeluaran Lainnya', 'type' => 'CREDIT', 'color' => '#78716c', 'icon' => 'dots', 'sort_order' => 19],
        ];
        $categoryMap = [];
        foreach ($categories as $cat) {
            $created = Category::create($cat);
            $categoryMap[$cat['name']] = $created->id;
        }
        $this->command->info('  ✓ Created ' . count($categories) . ' categories');

        // ── Classification Rules ──
        // Order = priority. Specific rules FIRST, generic transfer rules LAST.
        $rules = [
            // Bank fees (HIGHEST PRIORITY — must match BEFORE generic transfer)
            ['cat' => 'Admin Bank', 'pattern' => 'BIAYA ADM'],
            ['cat' => 'Admin Bank', 'pattern' => 'BIAYA TXN'],
            ['cat' => 'Admin Bank', 'pattern' => 'ADMIN FEE'],
            ['cat' => 'Admin Bank', 'pattern' => 'BIAYA TRANSFER'],
            ['cat' => 'Admin Bank', 'pattern' => 'BIAYA SKN'],
            ['cat' => 'Admin Bank', 'pattern' => 'BIAYA RTGS'],
            // Marketplace / E-commerce
            ['cat' => 'Online Shop', 'pattern' => 'SHOPEEPAY'],
            ['cat' => 'Online Shop', 'pattern' => 'SHOPEE'],
            ['cat' => 'Online Shop', 'pattern' => 'TOKOPEDIA'],
            ['cat' => 'Online Shop', 'pattern' => 'LAZADA'],
            ['cat' => 'Online Shop', 'pattern' => 'BUKALAPAK'],
            ['cat' => 'Online Shop', 'pattern' => 'TIKTOK SHOP'],
            ['cat' => 'Online Shop', 'pattern' => 'BLIBLI'],
            ['cat' => 'Online Shop', 'pattern' => 'AIRPAY'],
            // Biaya Operasional
            ['cat' => 'Biaya Operasional', 'pattern' => 'PLN'],
            ['cat' => 'Biaya Operasional', 'pattern' => 'LISTRIK'],
            ['cat' => 'Biaya Operasional', 'pattern' => 'SEWA'],
            ['cat' => 'Biaya Operasional', 'pattern' => 'AIR PDAM'],
            ['cat' => 'Biaya Operasional', 'pattern' => 'TELKOM'],
            ['cat' => 'Biaya Operasional', 'pattern' => 'INTERNET'],
            // Gaji & THR
            ['cat' => 'Gaji & THR', 'pattern' => 'GAJI'],
            ['cat' => 'Gaji & THR', 'pattern' => 'PAYROLL'],
            ['cat' => 'Gaji & THR', 'pattern' => 'THR'],
            ['cat' => 'Gaji & THR', 'pattern' => 'SALARY'],
            // Penjualan Langsung
            ['cat' => 'Penjualan Langsung', 'pattern' => 'SETOR TUNAI'],
            ['cat' => 'Penjualan Langsung', 'pattern' => 'SETORAN TUNAI'],
            // Penagihan Piutang
            ['cat' => 'Penagihan Piutang', 'pattern' => 'PELUNASAN'],
            ['cat' => 'Penagihan Piutang', 'pattern' => 'PIUTANG'],
            // Bunga Bank
            ['cat' => 'Bunga Bank', 'pattern' => 'BUNGA'],
            ['cat' => 'Bunga Bank', 'pattern' => 'INTEREST'],
            // Pembelian Produk
            ['cat' => 'Pembelian Produk', 'pattern' => 'PEMBELIAN'],
            ['cat' => 'Pembelian Produk', 'pattern' => 'SUPPLIER'],
            ['cat' => 'Pembelian Produk', 'pattern' => 'BEAUTY SUPPLY'],
            ['cat' => 'Pembelian Produk', 'pattern' => 'SKINCARE'],
            ['cat' => 'Pembelian Produk', 'pattern' => 'KOSMETIK'],
            ['cat' => 'Pembelian Produk', 'pattern' => 'PO-'],
            // Reward
            ['cat' => 'Reward', 'pattern' => 'CASHBACK'],
            ['cat' => 'Reward', 'pattern' => 'REWARD'],
            // Withdrawal
            ['cat' => 'Withdrawal (WD)', 'pattern' => 'TARIKAN ATM'],
            ['cat' => 'Withdrawal (WD)', 'pattern' => 'WD ATM'],
            ['cat' => 'Withdrawal (WD)', 'pattern' => 'WITHDRAWAL'],
            ['cat' => 'Withdrawal (WD)', 'pattern' => 'TARIK TUNAI'],
            // Pajak
            ['cat' => 'Pajak', 'pattern' => 'PAJAK'],
            ['cat' => 'Pajak', 'pattern' => 'PPN'],
            ['cat' => 'Pajak', 'pattern' => 'PPH'],
            // Logistik
            ['cat' => 'Logistik', 'pattern' => 'JNE'],
            ['cat' => 'Logistik', 'pattern' => 'J&T'],
            ['cat' => 'Logistik', 'pattern' => 'SICEPAT'],
            ['cat' => 'Logistik', 'pattern' => 'ONGKIR'],
            ['cat' => 'Logistik', 'pattern' => 'ANTERAJA'],
            // LOWEST PRIORITY: Generic transfer (catch-all)
            ['cat' => 'Transfer Masuk', 'pattern' => 'KR OTOMATIS'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'TRSF E-BANKING CR'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'BI-FAST CR'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'SWITCHING CR'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'TRSF MASUK'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'TRSF CR'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'FLEKSI CR'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'OB CR'],
            ['cat' => 'Transfer Masuk', 'pattern' => 'KLIRING CR'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'TRSF E-BANKING DB'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'BI-FAST DB'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'SWITCHING DB'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'TRSF DB'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'TRF KE'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'FLEKSI DB'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'OB DB'],
            ['cat' => 'Transfer Keluar', 'pattern' => 'KLIRING DB'],
        ];

        $ruleCount = 0;
        foreach ($rules as $pri => $r) {
            if (isset($categoryMap[$r['cat']])) {
                ClassificationRule::create([
                    'category_id' => $categoryMap[$r['cat']],
                    'pattern' => $r['pattern'],
                    'match_type' => 'CONTAINS',
                    'priority' => $pri + 1, // ordered priority
                ]);
                $ruleCount++;
            }
        }
        $this->command->info("  ✓ Created {$ruleCount} classification rules");

        // ── Bank Accounts ──
        BankAccount::create(['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_alias' => 'BCA Utama']);
        BankAccount::create(['bank_name' => 'Mandiri', 'account_number' => '0987654321', 'account_alias' => 'Mandiri Operasional']);
        $this->command->info('  ✓ Created 2 bank accounts');

        // No demo transactions — user will import their own CSV
        $this->command->info('');
        $this->command->info('✅ Seeding complete! (No demo transactions — import your own CSV)');
        $this->command->info('📋 Login credentials:');
        $this->command->info('   Direktur  : direktur@bigenmi.co.id / Bigenmi@2026');
        $this->command->info('   Admin     : admin@bigenmi.co.id / Admin@2026');
    }
}
