<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ rules: Array, categories: Array, accounts: Array, filters: Object, hasData: { type: Boolean, default: true } });
const page = usePage();
const canManage = page.props.permissions?.canManageSettings;
const showForm = ref(false);
const showHelp = ref(false);
const selectedAccountId = ref(props.filters?.account_id || '');
const form = useForm({ category_id: '', pattern: '', match_type: 'CONTAINS', priority: 10, bank_account_id: '' });

const showDeleteModal = ref(false);
const deleteTarget = ref(null);

function submit() {
    form.post('/settings/rules', { preserveScroll: true, onSuccess: () => { form.reset(); showForm.value = false; } });
}
function confirmDelete(rule) {
    deleteTarget.value = rule;
    showDeleteModal.value = true;
}
function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/settings/rules/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => { showDeleteModal.value = false; deleteTarget.value = null; },
    });
}

const matchTypeLabels = {
    CONTAINS: 'Contains',
    EXACT: 'Exact',
    REGEX: 'Regex',
};

// Bank account filter
watch(selectedAccountId, (val) => {
    form.bank_account_id = val;
    router.get('/settings/rules', {
        account_id: selectedAccountId.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}, { immediate: true });

function bankLabel(rule) {
    if (!rule.bank_account) return 'Global';
    return rule.bank_account.account_alias || rule.bank_account.bank_name;
}
</script>

<template>
    <Head title="Aturan Klasifikasi — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="page-title">Aturan Klasifikasi</h1>
                    <p class="text-sm text-surface-600 mt-1">Basis pengetahuan untuk klasifikasi otomatis transaksi</p>
                </div>
                <div class="flex gap-2">
                    <select v-if="accounts?.length" v-model="selectedAccountId" class="input-field !w-auto !pr-8 text-sm !py-2">
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_alias || acc.bank_name }}</option>
                    </select>
                    <button @click="showHelp = !showHelp" class="btn-secondary" title="Bantuan">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                        Bantuan
                    </button>
                    <button v-if="canManage" @click="showForm = !showForm" class="btn-primary">+ Tambah Aturan</button>
                </div>
            </div>

            <!-- Help Panel -->
            <Transition name="slide-up">
                <div v-if="showHelp" class="glass-card p-5 border-l-4 border-blue-400">
                    <h3 class="font-semibold text-plum mb-3">📖 Panduan Aturan Klasifikasi</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-surface-700">
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">🔤 Kata Kunci (Pattern)</h4>
                            <p class="text-surface-600">Kata atau frasa yang dicari di dalam deskripsi transaksi bank. Contoh: <code class="bg-cream-200 px-1 rounded">SHOPEE</code>, <code class="bg-cream-200 px-1 rounded">BIAYA ADM</code></p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">🎯 Tipe Pencocokan</h4>
                            <ul class="space-y-1.5 text-surface-600">
                                <li><span class="font-medium text-plum">Contains</span> — Mengandung kata. <em class="text-surface-500">Cth: "Makan" cocok dengan "Makan Malam"</em></li>
                                <li><span class="font-medium text-plum">Exact</span> — Sama persis 100%. <em class="text-surface-500">Cth: "Gaji" hanya cocok jika deskripsi persis "Gaji" saja.</em></li>
                                <li><span class="font-medium text-plum">Regex</span> — Pola tingkat lanjut. <em class="text-surface-500">Cth: <code class="bg-cream-200 px-1 rounded">(GAJI|THR)</code> cocok dengan "GAJI" atau "THR".</em></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">🏦 Rekening Bank</h4>
                            <p class="text-surface-600">Pilih rekening bank untuk aturan khusus per bank. Biarkan kosong untuk aturan global.</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-plum mb-1.5">⚡ Prioritas</h4>
                            <p class="text-surface-600">Angka lebih kecil = diproses lebih dulu. Jika ada dua aturan cocok, yang prioritas lebih kecil menang.</p>
                        </div>
                    </div>
                    <button @click="showHelp = false" class="mt-3 text-xs text-surface-500 hover:text-plum">Tutup bantuan ×</button>
                </div>
            </Transition>

            <!-- Add Form (Admin only) -->
            <Transition name="slide-up">
                <div v-if="showForm && canManage" class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-plum mb-4">Tambah Aturan Baru</h3>
                    <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="label-text">Kategori</label>
                            <select v-model="form.category_id" class="input-field" required>
                                <option value="" disabled>Pilih kategori...</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.type === 'DEBIT' ? 'Pemasukan' : 'Pengeluaran' }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-text">Kata Kunci</label>
                            <input v-model="form.pattern" class="input-field" placeholder="cth: SHOPEE" required />
                            <p class="text-[10px] text-surface-500 mt-1">Kata yang dicari di deskripsi transaksi</p>
                        </div>
                        <div>
                            <label class="label-text">Tipe Pencocokan</label>
                            <select v-model="form.match_type" class="input-field">
                                <option value="CONTAINS">Contains</option>
                                <option value="EXACT">Exact</option>
                                <option value="REGEX">Regex</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" :disabled="form.processing" class="btn-primary w-full">Simpan</button>
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
            <div v-else-if="rules.length > 0" class="glass-card overflow-hidden">
                <div class="hidden sm:block table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kata Kunci</th>
                                <th>Tipe Pencocokan</th>
                                <th>Kategori</th>
                                <th>Jumlah Cocok</th>
                                <th>Prioritas</th>
                                <th v-if="canManage"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rules" :key="r.id">
                                <td class="font-mono text-sm">{{ r.pattern }}</td>
                                <td><span class="badge-blue">{{ matchTypeLabels[r.match_type] || r.match_type }}</span></td>
                                <td>
                                    <span v-if="r.category" class="badge" :style="{ background: r.category.color + '15', color: r.category.color, border: '1px solid ' + r.category.color + '40' }">{{ r.category.name }}</span>
                                </td>
                                <td>{{ r.hit_count }}×</td>
                                <td>{{ r.priority }}</td>
                                <td v-if="canManage">
                                    <button @click="confirmDelete(r)" class="text-surface-500 hover:text-red-500 p-1 rounded-lg hover:bg-red-50 transition-colors" title="Hapus aturan">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="sm:hidden p-4 space-y-3">
                    <div v-for="r in rules" :key="r.id" class="mobile-card">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-mono text-sm font-semibold">{{ r.pattern }}</p>
                            <button v-if="canManage" @click="confirmDelete(r)" class="text-surface-500 hover:text-red-500 p-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg></button>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <span class="badge-blue text-[10px]">{{ matchTypeLabels[r.match_type] || r.match_type }}</span>
                            <span v-if="r.category" class="badge text-[10px]" :style="{ background: r.category.color + '15', color: r.category.color }">{{ r.category.name }}</span>
                        </div>
                        <p class="text-[10px] text-surface-500 mt-1">Cocok: {{ r.hit_count }}× · Prioritas: {{ r.priority }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12 text-surface-500">
                <svg class="w-12 h-12 text-surface-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                <p class="font-medium text-surface-600">Tidak ada data aturan.</p>
                <p class="text-xs mt-1">Belum ada aturan yang ditambahkan untuk rekening ini.</p>
            </div>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Aturan?"
            :message="`Aturan '${deleteTarget?.pattern}' akan dihapus dari sistem klasifikasi.`"
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
