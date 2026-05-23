<script setup>
import { ref, computed, inject } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ transactions: Object, filters: Object, categories: Array });
const page = usePage();
const canManage = page.props.permissions?.canManageCashTransactions;
const addToast = inject('addToast');

const search = ref(props.filters?.search || '');
const type = ref(props.filters?.type || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const showForm = ref(false);
const form = useForm({
    type: 'CREDIT',
    amount: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    description: '',
    category_id: '',
});

const filteredCategories = computed(() => {
    return props.categories.filter(c => c.type === form.type);
});

function buildParams() {
    return {
        search: search.value || undefined,
        type: type.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    };
}

function applyFilters() {
    router.get('/cash-transactions', buildParams(), { preserveState: true });
}

function onDateUpdate(val) {
    dateFrom.value = val.date_from || '';
    dateTo.value = val.date_to || '';
    if (val.preset) {
        router.get('/cash-transactions', { ...buildParams(), preset: val.preset, date_from: undefined, date_to: undefined }, { preserveState: true });
    } else {
        applyFilters();
    }
}

function submit() {
    form.post('/cash-transactions', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
            addToast?.('Transaksi tunai berhasil ditambahkan', 'success');
        },
    });
}

// Delete
const showDeleteModal = ref(false);
const deleteTarget = ref(null);

function confirmDelete(tx) {
    deleteTarget.value = tx;
    showDeleteModal.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/cash-transactions/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => addToast?.('Transaksi tunai berhasil dihapus', 'success'),
        onFinish: () => { showDeleteModal.value = false; deleteTarget.value = null; },
    });
}

function formatCurrency(v) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v); }
function formatDate(d) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); }
</script>

<template>
    <Head title="Transaksi Tunai — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="page-title text-lg sm:text-2xl">Transaksi Tunai</h1>
                    <p class="text-xs sm:text-sm text-surface-600 mt-0.5">
                        {{ canManage ? 'Kelola transaksi kas tunai secara manual' : 'Riwayat transaksi kas tunai' }}
                    </p>
                </div>
                <button v-if="canManage" @click="showForm = !showForm" class="btn-primary text-sm gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    {{ showForm ? 'Tutup Form' : 'Tambah Transaksi' }}
                </button>
            </div>

            <!-- Add Form (Admin only) -->
            <Transition name="slide-up">
                <div v-if="showForm && canManage" class="glass-card p-6 border-l-4 border-rose-400/60">
                    <h3 class="text-sm font-semibold text-plum mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
                        Tambah Transaksi Tunai Baru
                    </h3>
                    <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="label-text">Tipe Transaksi</label>
                            <select v-model="form.type" class="input-field" required>
                                <option value="DEBIT">💰 Pendapatan (Uang Masuk)</option>
                                <option value="CREDIT">💸 Pengeluaran (Uang Keluar)</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-text">Nominal (Rp)</label>
                            <input v-model="form.amount" type="number" min="1" step="1" class="input-field" placeholder="cth: 500000" required />
                            <p v-if="form.errors.amount" class="text-red-500 text-xs mt-1">{{ form.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="label-text">Tanggal</label>
                            <input v-model="form.transaction_date" type="date" class="input-field" required />
                            <p v-if="form.errors.transaction_date" class="text-red-500 text-xs mt-1">{{ form.errors.transaction_date }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label-text">Deskripsi</label>
                            <textarea v-model="form.description" rows="2" class="input-field resize-none" placeholder="cth: Pembelian ATK kantor" required></textarea>
                            <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                        </div>
                        <div>
                            <label class="label-text">Kategori <span class="text-surface-400 font-normal">(opsional)</span></label>
                            <select v-model="form.category_id" class="input-field">
                                <option value="">— Tanpa Kategori —</option>
                                <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
                            <button type="submit" :disabled="form.processing" class="btn-primary gap-1.5">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Filters -->
            <div class="glass-card p-4 sm:p-5">
                <div class="space-y-2.5 mb-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Cari deskripsi..." class="filter-field flex-1 min-w-[140px]" />
                        <select v-model="type" @change="applyFilters" class="filter-field !w-auto min-w-[120px]">
                            <option value="">Semua Tipe</option>
                            <option value="DEBIT">Pendapatan</option>
                            <option value="CREDIT">Pengeluaran</option>
                        </select>
                        <button @click="applyFilters" class="btn-primary text-xs !py-1.5 !px-4">Cari</button>
                    </div>
                    <DateRangePicker :initial-from="filters?.date_from" :initial-to="filters?.date_to" @update="onDateUpdate" />
                </div>

                <!-- Summary -->
                <div v-if="transactions.total" class="mb-4 text-xs text-surface-500">
                    Menampilkan {{ transactions.from }}–{{ transactions.to }} dari {{ transactions.total }} transaksi tunai
                </div>

                <!-- Empty State -->
                <div v-if="!transactions.data.length" class="text-center py-12 text-surface-500">
                    <svg class="w-14 h-14 text-surface-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
                    <p class="font-medium text-surface-600">Belum ada transaksi tunai.</p>
                    <p v-if="canManage" class="text-xs mt-1">Klik tombol "Tambah Transaksi" untuk menambahkan transaksi tunai pertama.</p>
                    <p v-else class="text-xs mt-1">Admin belum menambahkan transaksi tunai.</p>
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
                                    <th class="text-right">Jumlah</th>
                                    <th v-if="canManage" class="text-center w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tx in transactions.data" :key="tx.id">
                                    <td class="whitespace-nowrap">{{ formatDate(tx.transaction_date) }}</td>
                                    <td class="max-w-xs truncate" :title="tx.description">{{ tx.description }}</td>
                                    <td>
                                        <span v-if="tx.category" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold shadow-sm transition-all hover:scale-[1.02]" :style="{ background: tx.category.color + '12', color: tx.category.color, border: '1px solid ' + tx.category.color + '30' }">
                                            <span class="w-1.5 h-1.5 rounded-full" :style="{ background: tx.category.color }"></span>
                                            {{ tx.category.name }}
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-50 text-slate-500 border border-slate-200/60 shadow-sm">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Tanpa Kategori
                                        </span>
                                    </td>
                                    <td :class="['text-right font-bold whitespace-nowrap', tx.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500']">
                                        {{ tx.type === 'DEBIT' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                                    </td>
                                    <td v-if="canManage" class="text-center">
                                        <button @click="confirmDelete(tx)" class="text-surface-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50/50 transition-colors" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
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
                                <span v-if="tx.category" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold" :style="{ background: tx.category.color + '12', color: tx.category.color }">
                                    {{ tx.category.name }}
                                </span>
                            </div>
                            <div v-if="canManage" class="pt-2 border-t border-rose-50/50 flex justify-end">
                                <button @click="confirmDelete(tx)" class="text-red-400 hover:text-red-600 font-semibold text-xs flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    Hapus
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

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Transaksi Tunai?"
            :message="`Transaksi '${deleteTarget?.description?.substring(0, 50)}...' senilai ${deleteTarget ? formatCurrency(deleteTarget.amount) : ''} akan dihapus permanen.`"
            confirmText="Ya, Hapus"
            variant="danger"
            @confirm="executeDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>

<style scoped>
.slide-up-enter-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-leave-active { transition: all 0.25s cubic-bezier(0.5, 0, 0.75, 0); }
.slide-up-enter-from { opacity: 0; transform: translateY(16px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
