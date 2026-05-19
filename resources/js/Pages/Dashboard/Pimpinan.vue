<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CashFlowChart from '@/Components/Charts/CashFlowChart.vue';
import CategoryDonutChart from '@/Components/Charts/CategoryDonutChart.vue';
import DateRangePicker from '@/Components/DateRangePicker.vue';

const props = defineProps({
    summary: Object, cashflow: Object, breakdown: Array,
    recentTransactions: Array, accounts: Array, filters: Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const selectedAccountId = ref(props.filters?.account_id || '');
const selectedGranularity = ref(props.filters?.granularity || 'daily');
const granularities = [
    { label: 'Harian', value: 'daily' },
    { label: 'Bulanan', value: 'monthly' },
    { label: 'Tahunan', value: 'yearly' },
];

const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Pagi' : h < 15 ? 'Siang' : h < 18 ? 'Sore' : 'Malam';
});

// Widget collapse state
const STORAGE_KEY = 'sikubi_pimpinan_widgets';
function loadCollapsed() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch { return {}; }
}
const collapsed = ref(loadCollapsed());
function toggle(id) { collapsed.value[id] = !collapsed.value[id]; localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsed.value)); }

// Date filtering
const dateFilters = ref({ preset: props.filters?.preset || null, date_from: props.filters?.date_from || null, date_to: props.filters?.date_to || null });
function reloadData() {
    router.get('/dashboard', {
        account_id: selectedAccountId.value || undefined,
        granularity: selectedGranularity.value,
        ...dateFilters.value,
    }, { preserveState: true, preserveScroll: true });
}
function onDateUpdate(val) { dateFilters.value = val; reloadData(); }
watch([selectedAccountId, selectedGranularity], () => reloadData());

const isExporting = ref(false);
const recapUrl = computed(() => {
    const params = new URLSearchParams();
    if (selectedAccountId.value) params.set('account_id', selectedAccountId.value);
    if (dateFilters.value.date_from) params.set('date_from', dateFilters.value.date_from);
    if (dateFilters.value.date_to) params.set('date_to', dateFilters.value.date_to);
    const qs = params.toString();
    return '/reports/recap' + (qs ? '?' + qs : '');
});

function downloadRecap() {
    isExporting.value = true;
    const link = document.createElement('a');
    link.href = recapUrl.value;
    link.setAttribute('download', '');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => { isExporting.value = false; }, 2000);
}

function formatCurrency(val) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val); }
function formatDate(d) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); }
</script>

<template>
    <Head title="Dashboard Pimpinan — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <!-- Welcome -->
            <div class="glass-card p-4 sm:p-6 bg-gradient-to-r from-rose-50 via-white to-champagne-50 border-rose-200/40">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-lg sm:text-2xl font-display font-bold text-plum truncate">
                            Selamat {{ greeting }}, {{ user?.name?.split(' ')[0] || 'Pimpinan' }} 👋
                        </h1>
                        <p class="text-xs sm:text-sm text-surface-600 mt-0.5 truncate">Ringkasan keuangan PT Bigenmi Gemilang Indonesia</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="downloadRecap"
                           :disabled="isExporting"
                           class="btn-secondary !py-1.5 sm:!py-2 text-xs flex items-center gap-1.5">
                            <svg v-if="isExporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" /></svg>
                            <span class="hidden sm:inline">{{ isExporting ? 'Memproses...' : 'Download Rekap' }}</span>
                            <span class="sm:hidden">{{ isExporting ? '...' : 'Rekap' }}</span>
                        </button>
                        <select v-model="selectedAccountId" class="filter-field !w-auto !pr-8 max-w-full sm:max-w-[200px] flex-shrink-0">
                            <option value="">Semua Rekening</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_alias || acc.bank_name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <DateRangePicker :initial-from="filters?.date_from" :initial-to="filters?.date_to" :initial-preset="filters?.preset" @update="onDateUpdate" />
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="glass-card p-4 sm:p-5 accent-left-emerald group hover:shadow-card-hover transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] sm:text-xs font-semibold text-surface-500 uppercase tracking-wider">Pemasukan</span>
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-50 flex items-center justify-center"><svg class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941" /></svg></div>
                    </div>
                    <p class="stat-value text-emerald-600 text-base sm:text-xl truncate">{{ formatCurrency(summary?.totalDebit || 0) }}</p>
                    <p class="text-[10px] sm:text-xs text-surface-500 mt-1">{{ summary?.debitCount || 0 }} transaksi</p>
                </div>
                <div class="glass-card p-4 sm:p-5 accent-left-red group hover:shadow-card-hover transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] sm:text-xs font-semibold text-surface-500 uppercase tracking-wider">Pengeluaran</span>
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-red-50 flex items-center justify-center"><svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.306-4.307a11.95 11.95 0 015.814 5.519l2.74 1.22m0 0l-5.94 2.281m5.94-2.28l-2.28-5.941" /></svg></div>
                    </div>
                    <p class="stat-value text-red-500 text-base sm:text-xl truncate">{{ formatCurrency(summary?.totalCredit || 0) }}</p>
                    <p class="text-[10px] sm:text-xs text-surface-500 mt-1">{{ summary?.creditCount || 0 }} transaksi</p>
                </div>
                <div class="glass-card p-4 sm:p-5 accent-left-rose group hover:shadow-card-hover transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] sm:text-xs font-semibold text-surface-500 uppercase tracking-wider">Arus Kas Bersih</span>
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-rose-50 flex items-center justify-center"><svg class="w-4 h-4 sm:w-5 sm:h-5 text-rose-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                    </div>
                    <p :class="['stat-value text-base sm:text-xl truncate', (summary?.netCashFlow || 0) >= 0 ? 'text-emerald-600' : 'text-red-500']">{{ formatCurrency(summary?.netCashFlow || 0) }}</p>
                    <p class="text-[10px] sm:text-xs text-surface-500 mt-1">{{ summary?.transactionCount || 0 }} total transaksi</p>
                </div>
                <div class="glass-card p-4 sm:p-5 accent-left-amber group hover:shadow-card-hover transition-all duration-300">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] sm:text-xs font-semibold text-surface-500 uppercase tracking-wider">Anomali</span>
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-amber-50 flex items-center justify-center"><svg class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg></div>
                    </div>
                    <p class="stat-value text-amber-600 text-base sm:text-xl">{{ summary?.anomalyCount || 0 }}</p>
                    <p class="text-[10px] sm:text-xs text-surface-500 mt-1">{{ summary?.unclassifiedCount || 0 }} belum terklasifikasi</p>
                </div>
            </div>

            <!-- Cashflow Chart -->
            <div class="glass-card overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between px-4 sm:px-6 py-3 border-b border-rose-100/40 gap-2">
                    <h3 class="section-title text-sm sm:text-base">Arus Kas</h3>
                    <div class="flex items-center gap-1 bg-cream-200/60 rounded-xl p-1">
                        <button v-for="g in granularities" :key="g.value"
                            :class="['px-2 sm:px-3 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', selectedGranularity === g.value ? 'bg-white text-plum shadow-soft' : 'text-surface-600 hover:text-plum']"
                            @click="selectedGranularity = g.value"
                        >{{ g.label }}</button>
                    </div>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="h-[280px] sm:h-[350px]"><CashFlowChart :data="cashflow" /></div>
                </div>
            </div>

            <!-- Bottom Grid (collapsible) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <div class="glass-card overflow-hidden">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-rose-100/40">
                        <h3 class="section-title">Komposisi Transaksi</h3>
                        <div class="flex items-center gap-2">
                            <span class="badge-rose hidden sm:inline-flex">Periode aktif</span>
                            <button @click="toggle('donut')" class="p-1 text-surface-400 hover:text-plum rounded transition-colors ml-1">
                                <svg :class="['w-4 h-4 transition-transform', collapsed.donut ? '-rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                        </div>
                    </div>
                    <Transition name="expand">
                        <div v-show="!collapsed.donut" class="p-4 sm:p-6">
                            <div class="h-[320px] sm:h-[300px]"><CategoryDonutChart :data="breakdown" /></div>
                        </div>
                    </Transition>
                </div>
                <div class="glass-card overflow-hidden">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-rose-100/40">
                        <h3 class="section-title">Transaksi Terbaru</h3>
                        <div class="flex items-center gap-2">
                            <Link href="/transactions" class="text-xs text-rose-gold hover:text-rose-600 transition-colors font-semibold">Lihat semua →</Link>
                            <button @click="toggle('recent_tx')" class="p-1 text-surface-400 hover:text-plum rounded transition-colors ml-1">
                                <svg :class="['w-4 h-4 transition-transform', collapsed.recent_tx ? '-rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                        </div>
                    </div>
                    <Transition name="expand">
                        <div v-show="!collapsed.recent_tx" class="space-y-2 max-h-[340px] overflow-y-auto p-4 sm:p-6">
                            <div v-for="tx in recentTransactions" :key="tx.id" class="flex items-center gap-3 p-3 rounded-xl hover:bg-cream-200/50 transition-colors">
                                <div :class="['w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0', tx.type === 'DEBIT' ? 'bg-emerald-50' : 'bg-red-50']">
                                    <svg v-if="tx.type === 'DEBIT'" class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" /></svg>
                                    <svg v-else class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-plum truncate">{{ tx.description }}</p>
                                    <p class="text-xs text-surface-500">{{ formatDate(tx.transaction_date) }}<span v-if="tx.category" class="ml-1">· {{ tx.category.name }}</span></p>
                                </div>
                                <p :class="['text-sm font-bold whitespace-nowrap', tx.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500']">
                                    {{ tx.type === 'DEBIT' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                </p>
                            </div>
                            <div v-if="!recentTransactions?.length" class="text-center py-8 text-surface-500 text-sm">Belum ada transaksi.</div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
