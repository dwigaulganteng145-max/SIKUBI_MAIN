<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, inject } from 'vue';

const props = defineProps({
    anomalies: Object,
    accounts: Array,
    filters: Object,
});

const addToast = inject('addToast');
const page = usePage();

// Review modal state
const showReviewModal = ref(false);
const reviewingFlag = ref(null);
const reviewNote = ref('');
const isDismissing = ref(false);

// Template notes
const noteTemplates = [
    'Sudah dikonfirmasi — transaksi valid.',
    'Perlu ditindaklanjuti oleh pimpinan.',
    'Transaksi sudah diverifikasi dengan bukti transfer.',
    'Nominal sesuai dengan invoice/PO.',
    'Transaksi rutin, bukan anomali.',
    'Perlu klarifikasi ke pihak pengirim/penerima.',
    'Dana sudah dikembalikan/refund.',
];

function setSeverity(severity) {
    router.get('/anomalies', { severity, type: props.filters?.type || 'ALL' }, { preserveState: true, preserveScroll: true });
}

function setType(type) {
    router.get('/anomalies', { severity: props.filters?.severity || 'ALL', type }, { preserveState: true, preserveScroll: true });
}

function runDetection() {
    router.post('/anomalies/detect', {}, {
        preserveScroll: true,
        onSuccess: () => addToast?.('Deteksi anomali selesai', 'success'),
    });
}

function openReview(flag, dismiss = false) {
    reviewingFlag.value = flag;
    isDismissing.value = dismiss;
    reviewNote.value = '';
    showReviewModal.value = true;
}

function submitReview() {
    if (!reviewingFlag.value) return;
    router.patch(`/anomalies/${reviewingFlag.value.id}`, {
        dismiss: isDismissing.value,
        review_note: reviewNote.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showReviewModal.value = false;
            reviewingFlag.value = null;
            addToast?.(isDismissing.value ? 'Anomali diabaikan' : 'Anomali telah ditinjau', 'success');
        },
    });
}

function selectTemplate(tpl) {
    reviewNote.value = tpl;
}

function formatCurrency(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }
function formatDate(d) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); }

function severityLabel(s) {
    return { HIGH: 'Tinggi', MEDIUM: 'Sedang', LOW: 'Rendah' }[s] || s;
}
function severityClass(s) {
    return { HIGH: 'badge-red', MEDIUM: 'badge-yellow', LOW: 'badge-blue' }[s] || 'badge-rose';
}

function typeLabel(method) {
    if (method?.startsWith('INCOME')) return 'Pemasukan';
    if (method?.startsWith('EXPENSE')) return 'Pengeluaran';
    return method;
}
function typeIcon(method) {
    if (method?.startsWith('INCOME')) return '💰';
    if (method?.startsWith('EXPENSE')) return '💸';
    return '⚠';
}
function subtypeLabel(method) {
    if (method === 'INCOME_INSTANT') return 'Instan ≥ 10jt';
    if (method === 'INCOME_ACCUMULATED') return 'Akumulasi ≥ 10jt';
    if (method === 'EXPENSE_MISMATCH') return 'Tidak Seimbang';
    return method;
}
</script>

<template>
    <Head title="Deteksi Anomali — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="page-title text-lg sm:text-2xl">Deteksi Anomali</h1>
                    <p class="text-xs sm:text-sm text-surface-600 mt-0.5">Identifikasi transaksi pemasukan & pengeluaran mencurigakan</p>
                </div>
                <button @click="runDetection" class="btn-primary text-xs !py-1.5 !px-4 sm:!py-2.5 sm:!px-5">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    Jalankan Deteksi
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Type filter -->
                <div class="flex items-center bg-cream-200/60 p-1 rounded-xl">
                    <button @click="setType('ALL')" :class="['px-2.5 sm:px-4 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', filters?.type === 'ALL' ? 'bg-white text-plum shadow-soft' : 'text-surface-600 hover:text-plum']">Semua</button>
                    <button @click="setType('INCOME')" :class="['px-2.5 sm:px-4 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', filters?.type?.startsWith('INCOME') ? 'bg-white text-emerald-600 shadow-soft' : 'text-surface-600 hover:text-plum']">💰 Pemasukan</button>
                    <button @click="setType('EXPENSE')" :class="['px-2.5 sm:px-4 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', filters?.type?.startsWith('EXPENSE') ? 'bg-white text-red-500 shadow-soft' : 'text-surface-600 hover:text-plum']">💸 Pengeluaran</button>
                </div>

                <!-- Severity filter -->
                <div class="flex items-center bg-cream-200/60 p-1 rounded-xl">
                    <button @click="setSeverity('ALL')" :class="['px-2.5 sm:px-4 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', filters?.severity === 'ALL' ? 'bg-white text-plum shadow-soft' : 'text-surface-600 hover:text-plum']">Semua</button>
                    <button @click="setSeverity('HIGH')" :class="['px-2.5 sm:px-4 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', filters?.severity === 'HIGH' ? 'bg-white text-red-500 shadow-soft' : 'text-surface-600 hover:text-plum']">Tinggi</button>
                    <button @click="setSeverity('MEDIUM')" :class="['px-2.5 sm:px-4 py-1 sm:py-1.5 text-[10px] sm:text-xs font-semibold rounded-lg transition-all', filters?.severity === 'MEDIUM' ? 'bg-white text-amber-500 shadow-soft' : 'text-surface-600 hover:text-plum']">Sedang</button>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="glass-card p-4 border-l-4 border-l-emerald-400">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg">💰</span>
                        <h3 class="text-sm font-semibold text-plum">Anomali Pemasukan</h3>
                    </div>
                    <p class="text-xs text-surface-600">Transaksi dari 1 akun atau lebih ke Bigenmi yang mencapai <strong>Rp 10 juta</strong> (instan atau akumulasi).</p>
                </div>
                <div class="glass-card p-4 border-l-4 border-l-red-400">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg">💸</span>
                        <h3 class="text-sm font-semibold text-plum">Anomali Pengeluaran</h3>
                    </div>
                    <p class="text-xs text-surface-600">Transaksi dari Bigenmi ke 1 akun atau lebih yang <strong>tidak sesuai</strong> dengan jumlah pemasukan dari akun tersebut.</p>
                </div>
            </div>

            <!-- Anomaly List -->
            <div class="space-y-3">
                <div v-for="flag in anomalies.data" :key="flag.id" class="glass-card p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <!-- Type & Severity badges -->
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span :class="[
                                    'badge text-[10px]',
                                    flag.detection_method?.startsWith('INCOME')
                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        : 'bg-red-50 text-red-700 border border-red-200'
                                ]">
                                    {{ typeIcon(flag.detection_method) }} {{ typeLabel(flag.detection_method) }}
                                </span>
                                <span class="badge text-[10px] bg-surface-100 text-surface-600 border border-surface-200">
                                    {{ subtypeLabel(flag.detection_method) }}
                                </span>
                                <span :class="severityClass(flag.severity)" class="text-[10px]">{{ severityLabel(flag.severity) }}</span>
                            </div>

                            <!-- Transaction info -->
                            <p class="text-sm font-semibold text-plum truncate">{{ flag.transaction?.description }}</p>
                            <p class="text-xs text-surface-500 mt-0.5">
                                {{ formatDate(flag.transaction?.transaction_date) }}
                                · {{ flag.transaction?.bank_account?.account_alias || flag.transaction?.bank_account?.bank_name }}
                            </p>
                            <p :class="['text-base font-bold mt-1', flag.transaction?.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500']">
                                {{ formatCurrency(flag.transaction?.amount || 0) }}
                            </p>

                            <!-- Reason -->
                            <p class="text-xs text-surface-600 mt-2 bg-cream-200/50 rounded-lg p-2.5 leading-relaxed">{{ flag.reason }}</p>

                            <!-- Review note (if reviewed) -->
                            <div v-if="flag.is_reviewed && flag.review_note" class="mt-2 text-xs bg-blue-50 border border-blue-200 rounded-lg p-2.5 text-blue-700">
                                📝 <strong>Catatan:</strong> {{ flag.review_note }}
                            </div>
                        </div>

                        <!-- Actions -->
                        <div v-if="!flag.is_reviewed" class="flex gap-2 flex-shrink-0">
                            <button @click="openReview(flag, false)" class="btn-secondary text-xs !py-1.5 !px-3">
                                ✍ Tinjau
                            </button>
                            <button @click="openReview(flag, true)" class="btn-ghost text-xs !py-1.5 !px-3">
                                Abaikan
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-2 flex-shrink-0">
                            <span v-if="flag.is_dismissed" class="badge text-[10px] bg-surface-100 text-surface-500 border border-surface-200">⚠ Diabaikan</span>
                            <span v-else class="badge-green text-[10px]">✓ Ditinjau</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!anomalies.data?.length" class="glass-card p-12 text-center">
                    <svg class="w-12 h-12 text-surface-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    <p class="text-surface-500 font-medium">Belum ada anomali terdeteksi.</p>
                    <p class="text-xs text-surface-400 mt-1">Klik "Jalankan Deteksi" untuk memulai analisis pemasukan & pengeluaran.</p>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showReviewModal" class="fixed inset-0 bg-plum/30 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showReviewModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 animate-fade-in">
                        <h3 class="text-lg font-display font-bold text-plum mb-1">
                            {{ isDismissing ? '⚠ Abaikan Anomali' : '✍ Tinjau Anomali' }}
                        </h3>
                        <p class="text-xs text-surface-500 mb-4">
                            {{ isDismissing ? 'Berikan catatan mengapa anomali ini diabaikan.' : 'Berikan catatan singkat hasil tinjauan Anda.' }}
                        </p>

                        <!-- Transaction Preview -->
                        <div v-if="reviewingFlag" class="bg-cream-100 rounded-xl p-3 mb-4">
                            <p class="text-sm font-semibold text-plum truncate">{{ reviewingFlag.transaction?.description }}</p>
                            <p :class="['text-sm font-bold mt-0.5', reviewingFlag.transaction?.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500']">
                                {{ formatCurrency(reviewingFlag.transaction?.amount || 0) }}
                            </p>
                        </div>

                        <!-- Template Notes -->
                        <div class="mb-3">
                            <p class="text-xs font-medium text-surface-500 mb-2">Template catatan:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="(tpl, i) in noteTemplates" :key="i"
                                    @click="selectTemplate(tpl)"
                                    :class="['text-[10px] px-2.5 py-1 rounded-lg border transition-all', reviewNote === tpl ? 'bg-rose-50 border-rose-300 text-plum font-semibold' : 'bg-cream-100 border-surface-200 text-surface-600 hover:border-rose-300']"
                                >{{ tpl }}</button>
                            </div>
                        </div>

                        <!-- Custom Note Input -->
                        <div class="mb-4">
                            <label class="label-text">Catatan (opsional)</label>
                            <textarea
                                v-model="reviewNote"
                                class="input-field !min-h-[80px] resize-none"
                                placeholder="Tulis catatan tinjauan..."
                            ></textarea>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 justify-end">
                            <button @click="showReviewModal = false" class="btn-ghost text-sm">Batal</button>
                            <button
                                @click="submitReview"
                                :class="isDismissing ? 'btn-secondary' : 'btn-primary'"
                                class="text-sm"
                            >
                                {{ isDismissing ? 'Abaikan' : 'Simpan Tinjauan' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active { transition: opacity 0.2s ease; }
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
