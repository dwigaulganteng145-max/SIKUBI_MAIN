<script setup>
import { ref, inject } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

defineProps({ accounts: Array });
const addToast = inject('addToast');
const showForm = ref(false);
const form = useForm({ bank_name: '', account_number: '', account_alias: '', currency: 'IDR' });

const showDeleteModal = ref(false);
const deleteTarget = ref(null);

function submit() {
    form.post('/accounts', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); showForm.value = false; addToast?.('Rekening berhasil ditambahkan', 'success'); },
    });
}

function confirmDelete(acc) {
    deleteTarget.value = acc;
    showDeleteModal.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/accounts/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => addToast?.('Rekening berhasil dihapus', 'success'),
        onFinish: () => { showDeleteModal.value = false; deleteTarget.value = null; },
    });
}

const showEditModal = ref(false);
const editForm = useForm({
    id: null,
    bank_name: '',
    account_number: '',
    account_alias: '',
    currency: 'IDR'
});

function openEditModal(acc) {
    editForm.id = acc.id;
    editForm.bank_name = acc.bank_name;
    editForm.account_number = acc.account_number;
    editForm.account_alias = acc.account_alias || '';
    editForm.currency = acc.currency || 'IDR';
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editForm.reset();
}

function submitEdit() {
    editForm.put(`/accounts/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
            addToast?.('Rekening berhasil diperbarui', 'success');
        }
    });
}
</script>

<template>
    <Head title="Rekening Bank — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="page-title">Rekening Bank</h1>
                    <p class="text-sm text-surface-600 mt-1">Kelola rekening bank yang terhubung</p>
                </div>
                <button @click="showForm = !showForm" class="btn-primary">+ Tambah</button>
            </div>
            <Transition name="slide-up">
                <div v-if="showForm" class="glass-card p-6">
                    <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label class="label-text">Nama Bank</label><input v-model="form.bank_name" class="input-field" placeholder="BCA" required /></div>
                        <div><label class="label-text">No. Rekening</label><input v-model="form.account_number" class="input-field" placeholder="1234567890" required /></div>
                        <div><label class="label-text">Alias</label><input v-model="form.account_alias" class="input-field" placeholder="BCA Utama" /></div>
                        <div class="flex items-end"><button type="submit" :disabled="form.processing" class="btn-primary w-full">Simpan</button></div>
                    </form>
                </div>
            </Transition>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="acc in accounts" :key="acc.id" class="glass-card-hover p-5">
                    <div class="flex items-start justify-between">
                        <div class="w-10 h-10 rounded-xl bg-gradient-rose flex items-center justify-center text-white font-bold text-sm">
                            {{ acc.bank_name.substring(0, 2) }}
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="openEditModal(acc)" class="text-surface-500 hover:text-plum transition-colors p-1 rounded-lg hover:bg-rose-50" title="Ubah rekening">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                            </button>
                            <button @click="confirmDelete(acc)" class="text-surface-500 hover:text-red-500 transition-colors p-1 rounded-lg hover:bg-red-50" title="Hapus rekening">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            </button>
                        </div>
                    </div>
                    <h3 class="text-lg font-semibold text-plum mt-3">{{ acc.account_alias || acc.bank_name }}</h3>
                    <p class="text-sm text-surface-500">{{ acc.bank_name }} · {{ acc.account_number }}</p>
                    <p class="text-xs text-surface-500 mt-2">{{ acc.transactions_count || 0 }} transaksi</p>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Rekening Bank?"
            :message="`Rekening '${deleteTarget?.account_alias || deleteTarget?.bank_name}' (${deleteTarget?.account_number}) akan dihapus beserta semua transaksi terkait.`"
            confirmText="Ya, Hapus"
            variant="danger"
            @confirm="executeDelete"
            @cancel="showDeleteModal = false"
        />

        <!-- Premium Edit Account Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
            <div class="fixed inset-0 bg-surface-900/60 backdrop-blur-sm transition-opacity" @click="closeEditModal"></div>
            
            <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all border border-rose-50/50 animate-scale-up">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-rose-50/80">
                    <div>
                        <h3 class="text-lg font-bold text-plum">Ubah Rekening Bank</h3>
                        <p class="text-xs text-surface-500 mt-0.5">Ubah nama, nomor, dan alias rekening bank</p>
                    </div>
                    <button @click="closeEditModal" class="text-surface-400 hover:text-plum p-1.5 rounded-lg hover:bg-rose-50/50 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="submitEdit" class="space-y-4 pt-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-600 mb-1">Nama Bank</label>
                        <input v-model="editForm.bank_name" class="input-field !rounded-xl !py-2" placeholder="BCA" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-600 mb-1">No. Rekening</label>
                        <input v-model="editForm.account_number" class="input-field !rounded-xl !py-2" placeholder="1234567890" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-surface-600 mb-1">Alias Rekening</label>
                        <input v-model="editForm.account_alias" class="input-field !rounded-xl !py-2" placeholder="BCA Utama" />
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-rose-50/80">
                        <button type="button" @click="closeEditModal" class="btn-secondary text-xs !py-2 !px-4 !rounded-xl">
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="editForm.processing" 
                            class="btn-primary text-xs !py-2 !px-4 !rounded-xl gap-1.5 flex items-center justify-center"
                        >
                            <svg v-if="editForm.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.slide-up-enter-active { transition: all 0.3s ease-out; }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(16px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
