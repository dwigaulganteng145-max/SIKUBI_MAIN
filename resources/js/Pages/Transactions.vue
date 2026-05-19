<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DateRangePicker from '@/Components/DateRangePicker.vue';

const props = defineProps({ transactions: Object, filters: Object, categories: Array, accounts: Array });
const page = usePage();
const isAdmin = page.props.auth?.user?.role === 'ADMIN_KEUANGAN';

const search = ref(props.filters?.search || '');
const type = ref(props.filters?.type || '');
const categoryId = ref(props.filters?.category_id || '');
const accountId = ref(props.filters?.account_id || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

function buildParams() {
    return {
        search: search.value || undefined,
        type: type.value || undefined,
        category_id: categoryId.value || undefined,
        account_id: accountId.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    };
}

function applyFilters() {
    router.get('/transactions', buildParams(), { preserveState: true });
}

function onDateUpdate(val) {
    dateFrom.value = val.date_from || '';
    dateTo.value = val.date_to || '';
    if (val.preset) {
        // For presets, let the backend resolve dates
        router.get('/transactions', { ...buildParams(), preset: val.preset, date_from: undefined, date_to: undefined }, { preserveState: true });
    } else {
        applyFilters();
    }
}

const isExporting = ref(false);

function exportCsv() {
    isExporting.value = true;
    const params = new URLSearchParams(buildParams());
    const url = '/transactions/export?' + params.toString();
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', '');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => { isExporting.value = false; }, 2000);
}

function formatCurrency(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }
function formatDate(d) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); }

const showEditModal = ref(false);
const editingTx = ref(null);
const editForm = ref({
    id: null,
    description: '',
    category_id: '',
    type: ''
});

function openEditModal(tx) {
    editingTx.value = tx;
    editForm.value = {
        id: tx.id,
        description: tx.description,
        category_id: tx.category_id || '',
        type: tx.type
    };
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editingTx.value = null;
}

const isSaving = ref(false);

function submitEdit() {
    isSaving.value = true;
    router.patch(`/transactions/${editForm.value.id}`, {
        description: editForm.value.description,
        category_id: editForm.value.category_id || null
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
            isSaving.value = false;
        },
        onError: () => {
            isSaving.value = false;
        }
    });
}

function getCategoriesByType(type) {
    return props.categories.filter(c => c.type === type);
}
</script>

<template>
    <Head title="Transaksi — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="page-title text-lg sm:text-2xl">Transaksi</h1>
                    <p class="text-xs sm:text-sm text-surface-600 mt-0.5">Riwayat seluruh transaksi keuangan</p>
                </div>
                <button v-if="isAdmin" @click="exportCsv" :disabled="isExporting" class="btn-secondary text-xs gap-1.5 w-full sm:w-auto justify-center sm:justify-start">
                    <svg v-if="isExporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" /></svg>
                    {{ isExporting ? 'Memproses...' : 'Export CSV' }}
                </button>
            </div>

            <div class="glass-card p-4 sm:p-5">
                <!-- Filters -->
                <div class="space-y-2.5 mb-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Cari deskripsi..." class="filter-field flex-1 min-w-[140px]" />
                        <select v-model="type" @change="applyFilters" class="filter-field !w-auto min-w-[120px]">
                            <option value="">Semua Tipe</option>
                            <option value="DEBIT">Pemasukan</option>
                            <option value="CREDIT">Pengeluaran</option>
                        </select>
                        <select v-if="categories?.length" v-model="categoryId" @change="applyFilters" class="filter-field !w-auto min-w-[130px]">
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <select v-if="accounts?.length" v-model="accountId" @change="applyFilters" class="filter-field !w-auto min-w-[130px]">
                            <option value="">Semua Rekening</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_alias || acc.bank_name }}</option>
                        </select>
                        <button @click="applyFilters" class="btn-primary text-xs !py-1.5 !px-4">Cari</button>
                    </div>
                    <DateRangePicker :initial-from="filters?.date_from" :initial-to="filters?.date_to" @update="onDateUpdate" />
                </div>

                <!-- Summary -->
                <div v-if="transactions.total" class="mb-4 text-xs text-surface-500">
                    Menampilkan {{ transactions.from }}–{{ transactions.to }} dari {{ transactions.total }} transaksi
                </div>

                <!-- Not Found State -->
                <div v-if="!transactions.data.length" class="text-center py-12 text-surface-500">
                    <svg class="w-12 h-12 text-surface-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    <p class="font-medium text-surface-600">Tidak ada transaksi yang ditemukan.</p>
                    <p class="text-xs mt-1">Coba sesuaikan filter pencarian atau rentang tanggal.</p>
                </div>

                <template v-else>
                    <!-- Desktop Table -->
                    <div class="hidden sm:block table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Deskripsi</th>
                                    <th>Kategori</th>
                                    <th>Rekening</th>
                                    <th class="text-right">Jumlah</th>
                                    <th v-if="isAdmin" class="text-center w-20">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tx in transactions.data" :key="tx.id">
                                    <td class="whitespace-nowrap">{{ formatDate(tx.transaction_date) }}</td>
                                    <td class="max-w-xs truncate" :title="tx.description">{{ tx.description }}</td>
                                    <td>
                                        <!-- Gorgeous Category Badge with Soft Colored dot indicator -->
                                        <span v-if="tx.category" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold shadow-sm transition-all hover:scale-[1.02]" :style="{ background: tx.category.color + '12', color: tx.category.color, border: '1px solid ' + tx.category.color + '30' }">
                                            <span class="w-1.5 h-1.5 rounded-full" :style="{ background: tx.category.color }"></span>
                                            {{ tx.category.name }}
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200/60 shadow-sm italic">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            Belum Terkategori
                                        </span>
                                    </td>
                                    <td class="text-xs">{{ tx.bank_account?.account_alias || tx.bank_account?.bank_name }}</td>
                                    <td :class="['text-right font-bold whitespace-nowrap', tx.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500']">
                                        {{ tx.type === 'DEBIT' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                    </td>
                                    <td v-if="isAdmin" class="text-center whitespace-nowrap">
                                        <button @click="openEditModal(tx)" class="text-plum hover:text-rose-600 transition-colors p-1.5 rounded-lg hover:bg-rose-50/50" title="Edit Transaksi">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="sm:hidden space-y-3">
                        <div v-for="tx in transactions.data" :key="tx.id" class="mobile-card space-y-2.5">
                            <div class="flex justify-between items-start">
                                <p class="text-sm font-semibold text-plum truncate flex-1" :title="tx.description">{{ tx.description }}</p>
                                <p :class="['text-sm font-bold ml-2', tx.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500']">
                                    {{ tx.type === 'DEBIT' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                </p>
                            </div>
                            <div class="flex justify-between items-center text-xs text-surface-500">
                                <span>{{ formatDate(tx.transaction_date) }}</span>
                                <span>Rekening: {{ tx.bank_account?.account_alias || tx.bank_account?.bank_name }}</span>
                            </div>
                            
                            <div class="pt-2 border-t border-rose-50/50 flex justify-between items-center text-xs">
                                <!-- Mobile Category Badge -->
                                <div>
                                    <span v-if="tx.category" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold" :style="{ background: tx.category.color + '12', color: tx.category.color, border: '1px solid ' + tx.category.color + '30' }">
                                        <span class="w-1 h-1 rounded-full" :style="{ background: tx.category.color }"></span>
                                        {{ tx.category.name }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-600 border border-amber-200/60 italic">
                                        <span class="w-1 h-1 rounded-full bg-amber-400"></span>
                                        Belum Terkategori
                                    </span>
                                </div>

                                <!-- Mobile Edit Action -->
                                <button v-if="isAdmin" @click="openEditModal(tx)" class="text-plum hover:text-rose-600 font-semibold text-xs flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    Edit
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="transactions.last_page > 1" class="flex justify-center gap-2 mt-6">
                        <template v-for="link in transactions.links" :key="link.label">
                            <button v-if="link.url"
                                @click="router.get(link.url)"
                                :class="['px-3 py-1.5 text-sm rounded-lg transition-colors', link.active ? 'bg-gradient-rose text-white' : 'text-surface-600 hover:bg-rose-50']"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Premium Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
            <div class="fixed inset-0 bg-surface-900/60 backdrop-blur-sm transition-opacity" @click="closeEditModal"></div>
            
            <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all border border-rose-50/50 animate-scale-up">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-rose-50/80">
                    <div>
                        <h3 class="text-lg font-bold text-plum">Ubah Transaksi</h3>
                        <p class="text-xs text-surface-500 mt-0.5">Edit rincian data transaksi untuk menjaga akurasi laporan</p>
                    </div>
                    <button @click="closeEditModal" class="text-surface-400 hover:text-plum p-1.5 rounded-lg hover:bg-rose-50/50 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="submitEdit" class="space-y-4 pt-4">
                    <!-- Description Input -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-600">Deskripsi Transaksi</label>
                        <textarea 
                            v-model="editForm.description" 
                            rows="3" 
                            required
                            placeholder="Masukkan keterangan deskripsi transaksi..." 
                            class="filter-field w-full resize-none !rounded-xl !py-2.5"
                        ></textarea>
                    </div>

                    <!-- Category Select -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-600">Kategori</label>
                        <div class="relative">
                            <select 
                                v-model="editForm.category_id" 
                                class="filter-field w-full !rounded-xl !py-2.5 appearance-none pr-10"
                            >
                                <option value="">-- Belum Terkategori (Unclassified) --</option>
                                <option 
                                    v-for="cat in getCategoriesByType(editForm.type)" 
                                    :key="cat.id" 
                                    :value="cat.id"
                                >
                                    {{ cat.name }} ({{ editForm.type === 'DEBIT' ? 'Pemasukan' : 'Pengeluaran' }})
                                </option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-surface-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Meta Info Alert -->
                    <div class="rounded-xl bg-rose-50/30 p-3 border border-rose-100/50 flex items-start gap-2.5 text-xs text-plum/90">
                        <svg class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold">Info Klasifikasi:</span> Menyimpan perubahan ini akan menandai metode klasifikasi transaksi sebagai <strong class="text-rose-600 font-bold">Manual</strong> secara permanen demi akurasi sistem yang disesuaikan oleh Admin.
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-rose-50/80">
                        <button type="button" @click="closeEditModal" class="btn-secondary text-xs !py-2 !px-4 !rounded-xl">
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="isSaving" 
                            class="btn-primary text-xs !py-2 !px-4 !rounded-xl gap-1.5 flex items-center justify-center"
                        >
                            <svg v-if="isSaving" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
