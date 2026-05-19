<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ categories: Array, accounts: Array, filters: Object, hasData: { type: Boolean, default: true } });
const page = usePage();
const canManage = page.props.permissions?.canManageSettings;
const showForm = ref(false);
const showHelp = ref(false);
const selectedAccountId = ref(props.filters?.account_id || '');
const form = useForm({ name: '', type: 'CREDIT', color: '#E8637A', icon: 'folder', bank_account_id: '' });

const showDeleteModal = ref(false);
const deleteTarget = ref(null);

function submit() {
    form.post('/settings/categories', { preserveScroll: true, onSuccess: () => { form.reset(); showForm.value = false; } });
}
function confirmDelete(cat) {
    deleteTarget.value = cat;
    showDeleteModal.value = true;
}
function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/settings/categories/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => { showDeleteModal.value = false; deleteTarget.value = null; },
    });
}
function approveCategory(cat) {
    router.patch(`/settings/categories/${cat.id}/approve`, {}, { preserveScroll: true });
}

// Bank account filter
watch(selectedAccountId, (val) => {
    form.bank_account_id = val;
    router.get('/settings/categories', {
        account_id: selectedAccountId.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}, { immediate: true });

function bankLabel(cat) {
    if (!cat.bank_account) return 'Global';
    return cat.bank_account.account_alias || cat.bank_account.bank_name;
}
</script>

<template>
    <Head title="Kategori — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="page-title">Kategori</h1>
                    <p class="text-sm text-surface-600 mt-1">Kelola kategori transaksi keuangan</p>
                </div>
                <div class="flex gap-2">
                    <select v-if="accounts?.length" v-model="selectedAccountId" class="input-field !w-auto !pr-8 text-sm !py-2">
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_alias || acc.bank_name }}</option>
                    </select>
                    <button @click="showHelp = !showHelp" class="btn-secondary" title="Bantuan">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                        Bantuan
                    </button>
                    <button v-if="canManage" @click="showForm = !showForm" class="btn-primary">+ Tambah</button>
                </div>
            </div>

            <!-- Help Panel -->
            <Transition name="slide-up">
                <div v-if="showHelp" class="glass-card p-5 border-l-4 border-blue-400">
                    <h3 class="font-semibold text-plum mb-3">📖 Panduan Kategori</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-surface-700">
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">💰 Pemasukan (Uang Masuk)</h4>
                            <p class="text-surface-600">Kategori untuk uang yang <strong>masuk</strong> ke rekening. Di mutasi BCA ditandai dengan <code class="bg-cream-200 px-1 rounded">CR</code>.</p>
                            <p class="text-surface-500 text-xs mt-1">Contoh: Transfer Masuk, Penjualan Langsung, Online Shop</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">💸 Pengeluaran (Uang Keluar)</h4>
                            <p class="text-surface-600">Kategori untuk uang yang <strong>keluar</strong> dari rekening. Di mutasi BCA ditandai dengan <code class="bg-cream-200 px-1 rounded">DB</code>.</p>
                            <p class="text-surface-500 text-xs mt-1">Contoh: Transfer Keluar, Biaya Operasional, Gaji</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">🏦 Rekening Bank</h4>
                            <p class="text-surface-600">Pilih rekening bank untuk membuat kategori khusus per bank. Biarkan kosong untuk kategori global.</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">🔗 Aturan & Transaksi</h4>
                            <p class="text-surface-600">Jumlah aturan klasifikasi dan transaksi yang termasuk kategori ini.</p>
                        </div>
                    </div>
                    <button @click="showHelp = false" class="mt-3 text-xs text-surface-500 hover:text-plum">Tutup bantuan ×</button>
                </div>
            </Transition>

            <!-- Add Form (Admin only) -->
            <Transition name="slide-up">
                <div v-if="showForm && canManage" class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-plum mb-4">Tambah Kategori Baru</h3>
                    <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><label class="label-text">Nama Kategori</label><input v-model="form.name" class="input-field" placeholder="cth: Pembelian Produk" required /></div>
                        <div><label class="label-text">Tipe</label>
                            <select v-model="form.type" class="input-field"><option value="DEBIT">Pemasukan (Uang Masuk)</option><option value="CREDIT">Pengeluaran (Uang Keluar)</option></select>
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-shrink-0"><label class="label-text">Warna</label><input v-model="form.color" type="color" class="input-field h-[42px] w-14" /></div>
                            <button type="submit" :disabled="form.processing" class="btn-primary flex-1">Simpan</button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- No Data State -->
            <div v-if="!hasData" class="glass-card p-12 text-center">
                <svg class="w-12 h-12 text-surface-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" /></svg>
                <p class="font-medium text-surface-600">Belum ada data transaksi.</p>
                <p class="text-xs mt-1 text-surface-500">Silakan import data mutasi bank terlebih dahulu melalui menu <strong>Import Data</strong> agar kategori dan aturan dapat ditampilkan.</p>
            </div>

            <!-- Table -->
            <div v-else-if="categories.length > 0" class="glass-card overflow-hidden">
                <div class="hidden sm:block table-container">
                    <table class="data-table">
                        <thead><tr><th>Nama</th><th>Tipe</th><th>Transaksi</th><th>Aturan</th><th v-if="canManage"></th></tr></thead>
                        <tbody>
                            <tr v-for="cat in categories" :key="cat.id" :class="cat.is_suggested ? 'bg-amber-50/30' : ''">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ background: cat.color }" />
                                        {{ cat.name }}
                                        <span v-if="cat.is_suggested" class="badge-yellow text-[10px]">Disarankan Sistem</span>
                                    </div>
                                </td>
                                <td><span :class="cat.type === 'DEBIT' ? 'badge-green' : 'badge-red'">{{ cat.type === 'DEBIT' ? 'Pemasukan' : 'Pengeluaran' }}</span></td>
                                <td>{{ cat.transactions_count }}</td>
                                <td>{{ cat.classification_rules_count }}</td>
                                <td v-if="canManage">
                                    <div class="flex items-center gap-1">
                                        <button v-if="cat.is_suggested" @click="approveCategory(cat)" class="text-emerald-500 hover:text-emerald-700 p-1 rounded-lg hover:bg-emerald-50 transition-colors" title="Setujui kategori">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        </button>
                                        <button @click="confirmDelete(cat)" class="text-surface-500 hover:text-red-500 p-1 rounded-lg hover:bg-red-50 transition-colors" title="Hapus kategori">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="sm:hidden p-4 space-y-3">
                    <div v-for="cat in categories" :key="cat.id" class="mobile-card flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" :style="{ background: cat.color }" /><span class="font-medium">{{ cat.name }}</span></div>
                        </div>
                        <span :class="cat.type === 'DEBIT' ? 'badge-green' : 'badge-red'">{{ cat.type === 'DEBIT' ? 'Pemasukan' : 'Pengeluaran' }}</span>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12 text-surface-500">
                <svg class="w-12 h-12 text-surface-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /></svg>
                <p class="font-medium text-surface-600">Tidak ada data kategori.</p>
                <p class="text-xs mt-1">Belum ada kategori yang ditambahkan untuk rekening ini.</p>
            </div>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Kategori?"
            :message="`Kategori '${deleteTarget?.name}' akan dihapus beserta semua aturan klasifikasi terkait.`"
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
