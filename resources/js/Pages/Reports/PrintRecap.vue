<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RoverAnimation from '@/Components/RoverAnimation.vue';

const props = defineProps({
    accounts: Array,
    transactions: { type: Array, default: null },
    income_breakdown: { type: Array, default: () => [] },
    expense_breakdown: { type: Array, default: () => [] },
    anomalies: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
    filters: Object,
});

const page = usePage();

// Form state
const selectedMonth = ref(props.filters?.month || '');
const selectedYear = ref(props.filters?.year || new Date().getFullYear().toString());
const selectedAccountId = ref(props.filters?.account_id || '');

const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: currentYear - 2019 }, (_, i) => (currentYear - i).toString());

const hasReport = computed(() => props.transactions !== null && props.summary !== null);

function showReport() {
    if (!selectedMonth.value || !selectedYear.value) return;
    router.get('/reports/print', {
        month: selectedMonth.value,
        year: selectedYear.value,
        account_id: selectedAccountId.value || undefined,
    }, { preserveState: false });
}

function goBack() {
    selectedMonth.value = '';
    selectedYear.value = new Date().getFullYear().toString();
    selectedAccountId.value = '';
    router.get('/reports/print', {}, { preserveState: false });
}

function printPage() {
    window.print();
}

const isExportingExcel = ref(false);

function downloadExcel() {
    if (!hasReport.value) return;
    isExportingExcel.value = true;
    const params = new URLSearchParams({
        month: selectedMonth.value,
        year: selectedYear.value,
    });
    if (selectedAccountId.value) params.set('account_id', selectedAccountId.value);
    const link = document.createElement('a');
    link.href = '/reports/recap/excel?' + params.toString();
    link.setAttribute('download', '');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => { isExportingExcel.value = false; }, 2000);
}

function getLastDay() {
    const d = new Date(parseInt(selectedYear.value), parseInt(selectedMonth.value), 0);
    return `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatCurrency(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}
</script>

<template>
    <Head title="Cetak Laporan — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <!-- Mode: Selector -->
            <div v-if="!hasReport" class="flex flex-col items-center justify-center min-h-[65vh]">
                <!-- Rover -->
                <div class="mb-6 no-print">
                    <RoverAnimation :size="220" />
                </div>

                <div class="glass-card p-6 sm:p-8 max-w-lg w-full no-print">
                    <div class="text-center mb-6">
                        <h1 class="page-title text-xl sm:text-2xl">Cetak Laporan Keuangan</h1>
                        <p class="text-xs sm:text-sm text-surface-600 mt-1">Pilih bulan dan tahun untuk melihat rekap transaksi</p>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label-text">Bulan</label>
                                <select v-model="selectedMonth" class="input-field" id="month-selector">
                                    <option value="" disabled>Pilih Bulan</option>
                                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label-text">Tahun</label>
                                <select v-model="selectedYear" class="input-field" id="year-selector">
                                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="accounts?.length">
                            <label class="label-text">Rekening (Opsional)</label>
                            <select v-model="selectedAccountId" class="input-field" id="account-selector">
                                <option value="">Semua Rekening</option>
                                <option value="cash">💵 Transaksi Tunai</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_alias || acc.bank_name }}</option>
                            </select>
                        </div>

                        <button
                            @click="showReport"
                            :disabled="!selectedMonth || !selectedYear"
                            class="btn-primary w-full !py-3 text-sm"
                            id="btn-show-report"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            Tampilkan Laporan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mode: Report Preview -->
            <div v-else>
                <!-- Action buttons (hidden on print) -->
                <div class="flex flex-wrap items-center justify-between gap-3 no-print mb-4">
                    <div class="flex items-center gap-2">
                        <button @click="goBack" class="btn-secondary text-xs gap-1.5" id="btn-back">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                            Kembali
                        </button>

                        <button @click="downloadExcel" :disabled="isExportingExcel" class="btn-secondary text-xs gap-1.5 !text-emerald-700 hover:!text-emerald-800" id="btn-excel">
                            <svg v-if="isExportingExcel" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 !text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            {{ isExportingExcel ? 'Excel...' : 'Excel' }}
                        </button>
                    </div>
                    <button @click="printPage" class="btn-primary text-xs gap-1.5" id="btn-print">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m0 0a48.03 48.03 0 0110.5 0m-10.5 0V5.625c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125v3.026" />
                        </svg>
                        Cetak Halaman Ini
                    </button>
                </div>

                <!-- Printable Content -->
                <div class="print-content" id="report-content">
                    <!-- Report Header -->
                    <div class="report-header">
                        <div class="header-top-row">
                            <img src="/images/bigenmi-logo.png" alt="Bigenmi" class="report-logo" />
                            <div class="report-company">
                                <h2>SIKUBI</h2>
                                <p>Sistem Keuangan PT Bigenmi Gemilang Indonesia</p>
                            </div>
                        </div>
                        <h1 class="report-title">
                            Laporan Pendapatan &amp; Pengeluaran<br />
                            <span class="report-subtitle">Periode Bulan {{ summary.month_label }}</span>
                        </h1>
                    </div>

                    <!-- Dashboard Metrics (Page 1) -->
                    <div class="recap-cards-grid">
                        <div class="recap-card income-card">
                            <div class="card-title">TOTAL PENDAPATAN</div>
                            <div class="card-value">{{ formatCurrency(summary.total_debit) }}</div>
                            <div class="card-subtitle">{{ summary.total_debit_count }} Transaksi Masuk</div>
                        </div>
                        <div class="recap-card expense-card">
                            <div class="card-title">TOTAL PENGELUARAN</div>
                            <div class="card-value">{{ formatCurrency(summary.total_credit) }}</div>
                            <div class="card-subtitle">{{ summary.total_credit_count }} Transaksi Keluar</div>
                        </div>
                        <div class="recap-card balance-card" :class="summary.balance >= 0 ? 'balance-positive' : 'balance-negative'">
                            <div class="card-title">SISA SALDO BERSIH (NET)</div>
                            <div class="card-value">{{ formatCurrency(summary.balance) }}</div>
                            <div class="card-subtitle">Arus Kas Bersih Bulan Ini</div>
                        </div>
                    </div>

                    <!-- Category Share Progress Columns (The Visual Graphics Equivalent) -->
                    <div class="breakdown-section">
                        <h3 class="section-title">Visualisasi Kontribusi Kategori &amp; Kegiatan</h3>
                        <div class="breakdown-columns">
                            <!-- Income breakdown -->
                            <div class="breakdown-col">
                                <h4 class="col-title title-income">Pendapatan Berdasarkan Kategori</h4>
                                <div v-if="!income_breakdown.length" class="empty-breakdown">Tidak ada data pendapatan.</div>
                                <div v-else class="breakdown-list">
                                    <div v-for="item in income_breakdown" :key="item.name" class="breakdown-item">
                                        <div class="item-header">
                                            <span class="item-name">{{ item.name }}</span>
                                            <span class="item-values">
                                                <span class="item-percent">{{ Math.round((item.amount / (summary.total_debit || 1)) * 100) }}%</span>
                                                <span class="item-amount">({{ formatCurrency(item.amount) }})</span>
                                            </span>
                                        </div>
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar-fill fill-income" :style="{ width: Math.round((item.amount / (summary.total_debit || 1)) * 100) + '%', backgroundColor: item.color }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Expense breakdown -->
                            <div class="breakdown-col">
                                <h4 class="col-title title-expense">Pengeluaran Berdasarkan Kategori</h4>
                                <div v-if="!expense_breakdown.length" class="empty-breakdown">Tidak ada data pengeluaran.</div>
                                <div v-else class="breakdown-list">
                                    <div v-for="item in expense_breakdown" :key="item.name" class="breakdown-item">
                                        <div class="item-header">
                                            <span class="item-name">{{ item.name }}</span>
                                            <span class="item-values">
                                                <span class="item-percent">{{ Math.round((item.amount / (summary.total_credit || 1)) * 100) }}%</span>
                                                <span class="item-amount">({{ formatCurrency(item.amount) }})</span>
                                            </span>
                                        </div>
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar-fill fill-expense" :style="{ width: Math.round((item.amount / (summary.total_credit || 1)) * 100) + '%', backgroundColor: item.color }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Anomaly Detection Report -->
                    <div class="anomaly-report-section">
                        <h3 class="section-title">Hasil Deteksi &amp; Analisis Anomali Sistem</h3>
                        
                        <div v-if="!anomalies.length" class="anomaly-safe-card">
                            <span class="safe-icon">✓</span>
                            <div class="safe-text-wrapper">
                                <strong>Sistem Keuangan Aman (100% Bersih)</strong>
                                <p>Tidak ditemukan adanya transaksi anomali, transfer mencurigakan, atau lonjakan nominal yang tidak wajar pada periode bulan ini.</p>
                            </div>
                        </div>
                        
                        <div v-else class="anomaly-danger-section">
                            <div class="anomaly-warning-card">
                                <span class="warning-icon">⚠</span>
                                <div class="warning-text-wrapper">
                                    <strong>Ditemukan {{ anomalies.length }} Bendera Anomali Keuangan (Telah Ditinjau &amp; Kroscek oleh Admin)</strong>
                                    <p>Terdapat beberapa mutasi yang memerlukan peninjauan khusus dan telah ditinjau serta diverifikasi langsung oleh Admin Keuangan.</p>
                                </div>
                            </div>
                            
                            <table class="anomaly-print-table">
                                <thead>
                                    <tr>
                                        <th style="width: 12%;">Tanggal</th>
                                        <th style="width: 28%;">Transaksi &amp; Rekening</th>
                                        <th style="width: 30%;">Analisis Deteksi Sistem</th>
                                        <th style="width: 30%;">Status &amp; Keterangan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="flag in anomalies" :key="flag.id">
                                        <td class="td-date">{{ flag.date }}</td>
                                        <td>
                                            <div class="font-bold text-plum">{{ flag.description }}</div>
                                            <div class="text-xs text-surface-500 mt-0.5">Rekening: {{ flag.account }}</div>
                                            <div class="text-xs font-extrabold text-rose-gold mt-0.5">Nominal: {{ formatCurrency(flag.amount) }}</div>
                                        </td>
                                        <td>
                                            <span class="anomaly-badge badge-high mb-1 inline-block">HIGH THREAT</span>
                                            <div class="text-xs text-rose-950 font-medium leading-relaxed">{{ flag.reason }}</div>
                                        </td>
                                        <td>
                                            <div v-if="flag.is_reviewed" class="flex flex-col gap-1">
                                                <span class="status-badge badge-verified inline-block w-fit">✓ TERVERIFIKASI AMAN</span>
                                                <div class="text-xs font-semibold text-emerald-900 leading-normal" v-if="flag.review_note">
                                                    Alasan: <span class="italic font-normal">"{{ flag.review_note }}"</span>
                                                </div>
                                                <div class="text-[10px] text-surface-500 italic" v-else>Disetujui tanpa catatan khusus.</div>
                                            </div>
                                            <div v-else class="flex flex-col gap-1">
                                                <span class="status-badge badge-pending inline-block w-fit">🚨 BELUM DITINJAU</span>
                                                <div class="text-[10px] text-rose-800 italic">Memerlukan verifikasi nota oleh Admin Keuangan.</div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- Footer -->
                    <div class="report-footer">
                        <p>&copy; {{ new Date().getFullYear() }} SIKUBI — Sistem Keuangan PT Bigenmi Gemilang Indonesia</p>
                    </div>
                </div>

                <!-- Rover at bottom (hidden on print) -->
                <div class="flex justify-center mt-6 no-print">
                    <RoverAnimation :size="140" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ═══════════════════════════════════════
   REPORT STYLES (screen + print)
   ═══════════════════════════════════════ */

.print-content {
    background: white;
    border: 1px solid rgba(232, 99, 122, 0.12);
    border-radius: 16px;
    padding: 2rem 2.5rem;
    box-shadow: 0 2px 8px rgba(232, 99, 122, 0.05);
}

/* Report Header */
.report-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #4E2844;
}

.header-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.report-logo {
    height: 38px;
    width: auto;
    object-fit: contain;
}

.report-company {
    text-align: right;
}

.report-company h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #4E2844;
    letter-spacing: 1px;
    margin: 0;
    line-height: 1.2;
}

.report-company p {
    font-size: 0.75rem;
    color: #8B5E6B;
    margin: 2px 0 0 0;
    font-weight: 500;
}

.report-title {
    text-align: center;
    font-size: 1.25rem;
    font-weight: 700;
    color: #4E2844;
    line-height: 1.4;
    margin-top: 1rem;
}

.report-subtitle {
    font-size: 0.95rem;
    font-weight: 500;
    color: #8B5E6B;
    display: inline-block;
    margin-top: 4px;
}

/* Transaction Table */
.report-table-wrapper {
    overflow-x: auto;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    border: 1px solid #FFD0D6;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    border: 1px solid #FFD0D6;
}

.report-table thead {
    background: #4E2844;
    color: white;
}

.report-table th {
    padding: 10px 14px;
    font-weight: 600;
    text-align: left;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border: 1px solid #FFD0D6;
}

.th-no { width: 50px; text-align: center; }
.th-date { width: 110px; }
.th-type { width: 110px; text-align: center; }
.th-amount { width: 150px; text-align: right; }

.report-table td {
    padding: 9px 14px;
    border: 1px solid #FFD0D6;
    color: #4A2035;
}

.report-table tbody tr:hover {
    background: rgba(232, 99, 122, 0.03);
}

.td-no { text-align: center; color: #8B5E6B; }
.td-date { white-space: nowrap; }
.td-desc { max-width: 300px; }
.td-type { text-align: center; font-weight: 600; font-size: 0.78rem; }
.td-amount { text-align: right; font-weight: 700; white-space: nowrap; }

.type-income { color: #059669; }
.type-expense { color: #DC2626; }
.amount-income { color: #059669; }
.amount-expense { color: #DC2626; }
.amount-balance { font-weight: 800; color: #4E2844; }

.empty-row {
    text-align: center;
    padding: 2rem !important;
    color: #8B5E6B;
    font-style: italic;
}

/* Recap Cards Grid */
.recap-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.recap-card {
    border-radius: 12px;
    padding: 1.2rem;
    border: 1px solid #FFD0D6;
    background: linear-gradient(135deg, #ffffff, rgba(255, 154, 134, 0.02));
    box-shadow: 0 1px 3px rgba(255, 154, 134, 0.05);
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.recap-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(232, 99, 122, 0.08);
    border-color: #E8637A;
}

.income-card {
    border-left: 4px solid #059669;
}

.expense-card {
    border-left: 4px solid #DC2626;
}

.balance-card {
    border-left: 4px solid #4E2844;
}

.balance-positive {
    background: linear-gradient(135deg, #ffffff, rgba(5, 150, 105, 0.03));
    border-left-color: #059669;
}

.balance-negative {
    background: linear-gradient(135deg, #ffffff, rgba(220, 38, 38, 0.03));
    border-left-color: #DC2626;
}

.card-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: #8B5E6B;
    letter-spacing: 0.8px;
    margin-bottom: 0.4rem;
}

.card-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #4A2035;
    line-height: 1.2;
    white-space: nowrap;
}

.card-subtitle {
    font-size: 0.72rem;
    color: #8B5E6B;
    margin-top: 0.3rem;
    font-weight: 500;
}

/* Category Breakdown Progress Columns */
.breakdown-section {
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #4E2844;
    margin-bottom: 1rem;
    border-left: 3px solid #4E2844;
    padding-left: 8px;
    line-height: 1.2;
}

.breakdown-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
}

.breakdown-col {
    background: rgba(232, 99, 122, 0.02);
    border: 1px solid rgba(232, 99, 122, 0.12);
    border-radius: 12px;
    padding: 1.2rem;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.breakdown-col:hover {
    background: rgba(232, 99, 122, 0.04);
    border-color: rgba(232, 99, 122, 0.25);
    box-shadow: 0 6px 18px rgba(232, 99, 122, 0.04);
    transform: translateY(-2px);
}

.col-title {
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 1rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px dashed rgba(232, 99, 122, 0.12);
}

.title-income { color: #059669; }
.title-expense { color: #DC2626; }

.empty-breakdown {
    text-align: center;
    padding: 1.5rem 0;
    font-size: 0.8rem;
    color: #8B5E6B;
    font-style: italic;
}

.breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.breakdown-item {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.item-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    font-weight: 600;
}

.item-name {
    color: #4A2035;
}

.item-values {
    color: #8B5E6B;
}

.item-percent {
    font-weight: 700;
    color: #4A2035;
    margin-right: 4px;
}

.progress-bar-bg {
    height: 8px;
    background-color: rgba(232, 99, 122, 0.08);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Anomaly Section */
.anomaly-report-section {
    margin-bottom: 2rem;
}

.anomaly-safe-card {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(5, 150, 105, 0.03);
    border: 1px solid rgba(5, 150, 105, 0.15);
    border-radius: 12px;
    padding: 1rem 1.2rem;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.anomaly-safe-card:hover {
    transform: translateY(-2px);
    background: rgba(5, 150, 105, 0.05);
    border-color: rgba(5, 150, 105, 0.25);
    box-shadow: 0 6px 18px rgba(5, 150, 105, 0.06);
}

.safe-icon {
    font-size: 1.35rem;
    color: #059669;
    font-weight: 800;
}

.safe-text-wrapper strong {
    font-size: 0.85rem;
    color: #059669;
    display: block;
}

.safe-text-wrapper p {
    font-size: 0.75rem;
    color: #047857;
    margin-top: 2px;
}

.anomaly-warning-card {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(220, 38, 38, 0.03);
    border: 1px solid rgba(220, 38, 38, 0.15);
    border-radius: 12px;
    padding: 1rem 1.2rem;
    margin-bottom: 1rem;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.anomaly-warning-card:hover {
    transform: translateY(-2px);
    background: rgba(220, 38, 38, 0.05);
    border-color: rgba(220, 38, 38, 0.25);
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.06);
}

.warning-icon {
    font-size: 1.35rem;
    color: #DC2626;
    font-weight: 800;
}

.warning-text-wrapper strong {
    font-size: 0.85rem;
    color: #DC2626;
    display: block;
}

.warning-text-wrapper p {
    font-size: 0.75rem;
    color: #B91C1C;
    margin-top: 2px;
}

.anomaly-print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    border: 1px solid #FFD0D6;
    border-radius: 8px;
    overflow: hidden;
}

.anomaly-print-table th {
    padding: 8px 12px;
    background: rgba(220, 38, 38, 0.05);
    color: #B91C1C;
    font-weight: 700;
    text-align: left;
    border: 1px solid #FFD0D6;
}

.anomaly-print-table td {
    padding: 8px 12px;
    border: 1px solid #FFD0D6;
    color: #4A2035;
}

.anomaly-print-table tbody tr {
    transition: background-color 0.25s ease;
}

.anomaly-print-table tbody tr:hover {
    background-color: rgba(232, 99, 122, 0.03);
}

.anomaly-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 800;
    text-align: center;
}

.badge-high {
    background-color: rgba(220, 38, 38, 0.1);
    color: #DC2626;
}

.badge-medium {
    background-color: rgba(217, 119, 6, 0.1);
    color: #D97706;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-verified {
    background-color: rgba(5, 150, 105, 0.1);
    color: #059669;
}

.badge-pending {
    background-color: rgba(220, 38, 38, 0.1);
    color: #DC2626;
}

.page-break-title {
    margin-top: 2.5rem;
}

/* Footer */
.report-footer {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid #FFD0D6;
    color: #8B5E6B;
    font-size: 0.75rem;
    margin-top: 2rem;
}

/* ═══════════════════════════════════════
   PRINT STYLES
   ═══════════════════════════════════════ */

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 12mm 10mm 12mm;
    }

    /* Hide layout chrome elements */
    aside,
    header,
    .no-print {
        display: none !important;
    }

    /* Reset layout parent heights and overflows to print the entire page */
    html,
    body,
    #app,
    #app > div,
    .flex-1,
    main {
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #ffffff !important;
    }

    .print-content {
        display: block !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
        background: transparent !important;
        width: 100% !important;
    }

    .report-table-wrapper {
        border: 1px solid #333;
        overflow: visible !important;
    }

    .report-table {
        page-break-inside: auto;
        border: 1px solid #333333 !important;
        border-collapse: collapse !important;
    }

    .report-table thead {
        display: table-header-group !important;
        background: #333333 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .report-table th {
        color: white !important;
        border: 1px solid #333333 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .report-table td {
        border: 1px solid #333333 !important;
    }

    .report-table tr {
        page-break-inside: avoid !important;
        page-break-after: auto !important;
    }

    .detailed-ledger-section {
        page-break-before: always !important;
    }

    .progress-bar-bg {
        background-color: #f3f4f6 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .progress-bar-fill {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .anomaly-safe-card {
        background: #f0fdf4 !important;
        border: 1px solid #bbf7d0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .anomaly-warning-card {
        background: #fef2f2 !important;
        border: 1px solid #fecaca !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .anomaly-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .badge-high {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
    }

    .badge-medium {
        background-color: #fef3c7 !important;
        color: #d97706 !important;
    }

    .status-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .badge-verified {
        background-color: #d1fae5 !important;
        color: #059669 !important;
    }

    .badge-pending {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
    }

    .type-income, .amount-income {
        color: #059669 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .type-expense, .amount-expense {
        color: #DC2626 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .report-footer {
        page-break-inside: avoid;
    }
}
</style>

<style>
@media print {
    /* Eliminate browser default headers and footers (date, title, URL) */
    @page {
        size: A4 portrait;
        margin: 0 !important;
    }

    /* Globally hide layout chrome, sidebars, headers, and navigation bars */
    aside,
    header,
    nav,
    footer,
    .no-print {
        display: none !important;
    }

    /* Reset overflow and heights on root wrappers to avoid print clipping */
    html,
    body,
    #app,
    #app > div,
    .flex-1,
    main {
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #ffffff !important;
    }

    /* Re-inject custom page margins on the print container */
    .print-content {
        margin: 0 !important;
        padding: 12mm 15mm 12mm 15mm !important;
        box-sizing: border-box !important;
        width: 100% !important;
    }
}
</style>
