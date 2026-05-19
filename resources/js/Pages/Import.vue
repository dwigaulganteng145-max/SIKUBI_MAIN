<script setup>
import { ref, inject, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({ accounts: Array, batches: Array, trashedBatches: Array, pendingDuplicates: Array });

const page = usePage();
const addToast = inject('addToast');

const fileInputRef = ref(null);
const isDragging = ref(false);
const uploading = ref(false);
const selectedAccountId = ref('');
const selectedFiles = ref([]);
const uploadResult = ref(null);
const deletingBatchId = ref(null);
const isDuplicatesCollapsed = ref(false);

const flash = page.props.flash;
const selectedDuplicates = ref([]);

const selectAllDuplicates = computed({
    get: () => props.pendingDuplicates?.length > 0 && selectedDuplicates.value.length === props.pendingDuplicates.length,
    set: (val) => {
        if (val && props.pendingDuplicates) {
            selectedDuplicates.value = props.pendingDuplicates.map(d => d.id);
        } else {
            selectedDuplicates.value = [];
        }
    }
});

function resolveBatch(action) {
    if (!selectedDuplicates.value.length) return;
    router.post(`/import/duplicates/resolve-batch`, { ids: selectedDuplicates.value, action }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selectedDuplicates.value = [];
            addToast?.(`Transaksi duplikat berhasil diproses`, 'success');
            if (page.props.flash?.importResult) {
                uploadResult.value = page.props.flash.importResult;
            }
        }
    });
}

if (flash?.importResult) uploadResult.value = flash.importResult;

function triggerFileInput() { fileInputRef.value?.click(); }



function onDragEnter() { isDragging.value = true; }
function onDragOver() { isDragging.value = true; }
function onDragLeave(e) {
    if (e.currentTarget.contains(e.relatedTarget)) return;
    isDragging.value = false;
}
function onDrop(e) {
    isDragging.value = false;
    const files = Array.from(e.dataTransfer?.files || []);
    if (files.length) selectFiles(files);
}

function handleFileSelect(e) {
    const files = Array.from(e.target.files || []);
    if (files.length) selectFiles(files);
    // Reset input value so same files can be selected again if cleared
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function selectFiles(files) {
    const validFiles = files.filter(f => {
        const ext = f.name.toLowerCase();
        return ext.endsWith('.csv') || ext.endsWith('.txt') || ext.endsWith('.pdf');
    });

    if (validFiles.length < files.length) {
        addToast?.('Beberapa file diabaikan. Hanya file CSV (.csv), TXT (.txt), atau PDF (.pdf) yang diizinkan', 'error');
    }

    if (validFiles.length === 0) return;

    const newTotal = selectedFiles.value.length + validFiles.length;
    if (newTotal > 3) {
        addToast?.('Maksimal 3 file dapat diunggah sekaligus.', 'error');
        // Only take up to 3 files total
        const remainingSlots = 3 - selectedFiles.value.length;
        if (remainingSlots > 0) {
            selectedFiles.value = [...selectedFiles.value, ...validFiles.slice(0, remainingSlots)];
        }
    } else {
        selectedFiles.value = [...selectedFiles.value, ...validFiles];
    }
    
    uploadResult.value = null;
}

function clearFile(index = null) { 
    if (index !== null) {
        selectedFiles.value.splice(index, 1);
    } else {
        selectedFiles.value = [];
    }
    uploadResult.value = null; 
}

function formatSize(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
}

function uploadFile() {
    if (!selectedAccountId.value) { addToast?.('Pilih rekening bank terlebih dahulu', 'error'); return; }
    if (!selectedFiles.value.length) { addToast?.('Pilih minimal 1 file terlebih dahulu', 'error'); return; }

    uploading.value = true;
    uploadResult.value = null;

    const formData = new FormData();
    formData.append('account_id', selectedAccountId.value);
    selectedFiles.value.forEach(file => {
        formData.append('csv_files[]', file);
    });

    router.post('/import', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (p) => {
            const result = p.props.flash?.importResult;
            if (result) {
                uploadResult.value = result;
                selectedFiles.value = [];
                addToast?.(`Import selesai: ${result.success_rows} berhasil, ${result.duplicate_rows} duplikat`, 'success');
            }
        },
        onError: (errors) => {
            addToast?.(Object.values(errors).flat().join(', ') || 'Import gagal', 'error');
        },
        onFinish: () => { uploading.value = false; },
    });
}

// Delete modal
const showDeleteModal = ref(false);
const deleteTargetBatch = ref(null);
const showTrashed = ref(false);
const showForceDeleteModal = ref(false);
const forceDeleteTarget = ref(null);
const showMultipleForceDeleteModal = ref(false);
const selectedTrashed = ref([]);

function confirmDeleteBatch(batch) {
    deleteTargetBatch.value = batch;
    showDeleteModal.value = true;
}

function executeDeleteBatch() {
    if (!deleteTargetBatch.value) return;
    deletingBatchId.value = deleteTargetBatch.value.id;
    router.delete(`/import/${deleteTargetBatch.value.id}`, {
        preserveScroll: true,
        onSuccess: (p) => {
            const result = p.props.flash?.importResult;
            if (result) {
                uploadResult.value = result;
                addToast?.(result.message || 'Batch dihapus', 'success');
            }
        },
        onError: () => addToast?.('Gagal menghapus batch', 'error'),
        onFinish: () => { deletingBatchId.value = null; showDeleteModal.value = false; deleteTargetBatch.value = null; },
    });
}

function restoreBatch(batch) {
    router.post(`/import/${batch.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: (p) => {
            const result = p.props.flash?.importResult;
            if (result) {
                uploadResult.value = result;
                addToast?.(result.message || 'Batch dipulihkan', 'success');
            }
        },
        onError: () => addToast?.('Gagal memulihkan batch', 'error'),
    });
}

// Force delete modal

function confirmForceDelete(batch) {
    forceDeleteTarget.value = batch;
    showForceDeleteModal.value = true;
}

function confirmMultipleForceDelete() {
    if (selectedTrashed.value.length === 0) return;
    showMultipleForceDeleteModal.value = true;
}

function executeForceDelete(batch = null) {
    const targetId = batch ? batch.id : forceDeleteTarget.value?.id;
    if (!targetId) return;
    
    router.delete(`/import/${targetId}/force`, {
        preserveScroll: true,
        onSuccess: (p) => {
            const result = p.props.flash?.importResult;
            if (result) {
                uploadResult.value = result;
                addToast?.(result.message || 'Batch dihapus permanen', 'success');
            }
        },
        onError: () => addToast?.('Gagal menghapus permanen', 'error'),
        onFinish: () => {
            showForceDeleteModal.value = false;
            forceDeleteTarget.value = null;
            // Remove deleted item from selection
            selectedTrashed.value = selectedTrashed.value.filter(id => id !== targetId);
            if (props.trashedBatches?.length === 0) {
                showTrashed.value = false;
            }
        },
    });
}

function executeMultipleForceDelete() {
    if (selectedTrashed.value.length === 0) return;

    router.post(route('import.forceDestroyBatch'), {
        ids: selectedTrashed.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showMultipleForceDeleteModal.value = false;
            selectedTrashed.value = [];
            if (props.trashedBatches?.length === 0) {
                showTrashed.value = false;
            }
        },
    });
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatCurrency(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}
</script>

<template>
    <Head title="Import Data — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div>
                <h1 class="page-title">Import Data</h1>
                <p class="text-sm text-surface-600 mt-1">Upload dan proses file mutasi bank</p>
            </div>

            <div class="glass-card p-6 sm:p-8">
                <!-- Bank Account -->
                <div class="mb-6 max-w-md">
                    <label class="label-text" for="import-account">Rekening Tujuan</label>
                    <select id="import-account" v-model="selectedAccountId" class="input-field">
                        <option value="" disabled>Pilih rekening bank...</option>
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                            {{ acc.account_alias || acc.bank_name }} — {{ acc.account_number }}
                        </option>
                    </select>
                </div>

                <!-- Dropzone -->
                <div
                    :class="[
                        'border-2 border-dashed rounded-2xl p-8 sm:p-12 text-center transition-all duration-300 cursor-pointer',
                        isDragging ? 'border-rose-gold bg-rose-50/60 shadow-glow scale-[1.01]' : 'border-surface-300/60 hover:border-rose-300 hover:bg-cream-200/30',
                        uploading ? 'pointer-events-none opacity-60' : '',
                    ]"
                    @dragenter.prevent="onDragEnter"
                    @dragover.prevent="onDragOver"
                    @dragleave.prevent="onDragLeave"
                    @drop.prevent="onDrop"
                    @click="triggerFileInput"
                >
                    <input ref="fileInputRef" type="file" accept=".csv,.txt,.pdf" multiple class="hidden" @change="handleFileSelect" />

                    <div v-if="selectedFiles.length === 0" class="flex flex-col items-center gap-4">
                        <div :class="['w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-300', isDragging ? 'bg-rose-100 scale-110' : 'bg-cream-200']">
                            <svg :class="['w-8 h-8', isDragging ? 'text-rose-gold' : 'text-surface-500']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-plum font-medium">{{ isDragging ? '🎯 Lepas file di sini!' : 'Drag & drop file atau klik untuk browse' }}</p>
                            <p class="text-sm text-surface-500 mt-1">BCA & BRI Mutasi Rekening (.csv / .txt / .pdf) — Max 3 File</p>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center w-full max-w-md mx-auto" @click.stop>
                        <div class="w-full space-y-2 mb-4">
                            <div v-for="(file, index) in selectedFiles" :key="index" class="flex items-center justify-between bg-white border border-surface-200 rounded-xl p-3 shadow-sm">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 text-left">
                                        <p class="text-plum font-semibold text-sm truncate">{{ file.name }}</p>
                                        <p class="text-xs text-surface-500">{{ formatSize(file.size) }}</p>
                                    </div>
                                </div>
                                <button @click.stop="clearFile(index)" class="p-2 text-surface-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Hapus file ini">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button v-if="selectedFiles.length < 3" @click.stop="triggerFileInput" class="btn-secondary text-sm">Tambah File</button>
                            <button @click.stop="clearFile()" class="btn-secondary text-sm text-red-600 hover:bg-red-50 hover:border-red-200">Reset</button>
                            <button @click.stop="uploadFile" :disabled="!selectedAccountId || uploading" class="btn-primary text-sm">
                                <svg v-if="uploading" class="w-4 h-4 animate-spin mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ uploading ? 'Memproses...' : `Upload & Import (${selectedFiles.length})` }}
                            </button>
                        </div>
                        <p v-if="!selectedAccountId" class="text-xs text-amber-600 mt-2">⚠ Pilih rekening bank di atas terlebih dahulu</p>
                    </div>
                </div>
            </div>

            <!-- Processing Spinner -->
            <Transition name="slide-up">
                <div v-if="uploading && !uploadResult" class="glass-card p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-rose-gold animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </div>
                        <div>
                            <p class="text-plum font-medium">Memproses data mutasi...</p>
                            <p class="text-sm text-surface-500">Parsing format bank, klasifikasi otomatis, dan deduplikasi</p>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Upload Result -->
            <Transition name="slide-up">
                <div v-if="uploadResult" class="glass-card p-6">
                    <!-- Header -->
                    <div class="flex items-center gap-3 mb-4">
                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', uploadResult.status === 'COMPLETED' ? 'bg-emerald-50' : uploadResult.status === 'DELETED' ? 'bg-blue-50' : 'bg-red-50']">
                            <svg v-if="uploadResult.status === 'COMPLETED'" class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <svg v-else-if="uploadResult.status === 'DELETED'" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                            <svg v-else class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-plum font-medium">{{ uploadResult.status === 'COMPLETED' ? 'Import Berhasil' : uploadResult.status === 'DELETED' ? 'Batch Dihapus' : 'Import Gagal' }}</p>
                            <p class="text-sm text-surface-500">{{ uploadResult.message || `Format: ${uploadResult.bank_format}${uploadResult.periode ? ' · Periode: ' + uploadResult.periode : ''}` }}</p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div v-if="uploadResult.status !== 'DELETED'" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <div class="bg-cream-200/50 rounded-xl p-3 text-center">
                            <p class="text-lg font-bold text-plum">{{ uploadResult.total_rows }}</p>
                            <p class="text-xs text-surface-500">Total Baris</p>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-3 text-center">
                            <p class="text-lg font-bold text-emerald-600">{{ uploadResult.success_rows }}</p>
                            <p class="text-xs text-surface-500">Berhasil</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-3 text-center">
                            <p class="text-lg font-bold text-amber-600">{{ uploadResult.duplicate_rows }}</p>
                            <p class="text-xs text-surface-500">Duplikat</p>
                        </div>
                        <div class="bg-red-50 rounded-xl p-3 text-center">
                            <p class="text-lg font-bold text-red-500">{{ uploadResult.failed_rows }}</p>
                            <p class="text-xs text-surface-500">Gagal</p>
                        </div>
                    </div>

                    <!-- Failed Details -->
                    <div v-if="uploadResult.failed_details && uploadResult.failed_details.length > 0" class="mt-4">
                        <h4 class="text-sm font-semibold text-red-600 mb-2">⚠ Baris yang Gagal Diproses:</h4>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <div v-for="fail in uploadResult.failed_details" :key="fail.row" class="bg-red-50/60 border border-red-100 rounded-lg p-3 text-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="inline-block bg-red-100 text-red-700 text-xs font-medium px-2 py-0.5 rounded mr-2">Baris {{ fail.row }}</span>
                                        <span class="text-red-600">{{ fail.reason }}</span>
                                    </div>
                                </div>
                                <p v-if="fail.line" class="text-xs text-surface-500 mt-1 font-mono truncate">{{ fail.line }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Pending Duplicates -->
            <div v-if="pendingDuplicates && pendingDuplicates.length > 0" class="glass-card p-6 mb-6 border-amber-200 shadow-glow">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <h3 class="section-title text-amber-700 cursor-pointer select-none" @click="isDuplicatesCollapsed = !isDuplicatesCollapsed">
                            Duplikat Tertunda ({{ pendingDuplicates.length }})
                        </h3>
                        <button @click="isDuplicatesCollapsed = !isDuplicatesCollapsed" class="p-1 text-amber-600 hover:bg-amber-100 rounded-lg transition-colors" title="Minimize/Expand">
                            <svg :class="['w-5 h-5 transition-transform duration-300', isDuplicatesCollapsed ? '-rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>
                    
                    <div v-if="selectedDuplicates.length > 0 && !isDuplicatesCollapsed" class="flex gap-2 animate-fade-in">
                        <button @click="resolveBatch('IMPORT')" class="px-4 py-1.5 bg-emerald-500 text-white hover:bg-emerald-600 font-semibold text-xs rounded-lg shadow-soft transition-all">
                            Import {{ selectedDuplicates.length }} Terpilih
                        </button>
                        <button @click="resolveBatch('DISMISS')" class="px-4 py-1.5 bg-red-500 text-white hover:bg-red-600 font-semibold text-xs rounded-lg shadow-soft transition-all">
                            Abaikan {{ selectedDuplicates.length }} Terpilih
                        </button>
                    </div>
                </div>
                
                <div v-show="!isDuplicatesCollapsed">
                    <p class="text-sm text-surface-600 mb-4">Transaksi ini terdeteksi sama persis dengan yang sudah ada di sistem. Anda dapat memaksanya masuk atau mengabaikannya.</p>
                    
                    <div class="table-container max-h-[400px] overflow-y-auto mb-2">
                        <table class="data-table w-full text-left">
                            <thead class="sticky top-0 bg-white shadow-sm z-10">
                                <tr>
                                    <th class="w-10 text-center px-4 py-3"><input type="checkbox" v-model="selectAllDuplicates" class="rounded border-surface-300 text-plum focus:ring-plum/20 transition-all cursor-pointer" /></th>
                                    <th class="px-4 py-3 whitespace-nowrap">Tanggal</th>
                                    <th class="px-4 py-3">Deskripsi</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Nominal</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Rekening</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="dup in pendingDuplicates" :key="dup.id" :class="{'bg-rose-50/30': selectedDuplicates.includes(dup.id)}">
                                    <td class="w-10 text-center px-4 py-3"><input type="checkbox" :value="dup.id" v-model="selectedDuplicates" class="rounded border-surface-300 text-plum focus:ring-plum/20 transition-all cursor-pointer" /></td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(dup.transaction_date) }}</td>
                                    <td class="px-4 py-3 font-medium text-plum">{{ dup.description }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span :class="dup.type === 'DEBIT' ? 'text-emerald-600' : 'text-red-500'">
                                            {{ dup.type === 'DEBIT' ? '+' : '-' }}{{ formatCurrency(dup.amount) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">{{ dup.bank_account?.account_alias || dup.bank_account?.bank_name }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Import History -->
            <div v-if="batches && batches.length > 0" class="glass-card p-4 sm:p-6">
                <h3 class="section-title text-sm sm:text-base mb-3 sm:mb-4">Riwayat Import</h3>

                <!-- Desktop -->
                <div class="hidden sm:block table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Rekening</th>
                                <th>Tanggal</th>
                                <th class="text-center">Berhasil</th>
                                <th class="text-center">Duplikat</th>
                                <th class="text-center">Gagal</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in batches" :key="b.id">
                                <td class="font-medium text-plum">{{ b.file_name }}</td>
                                <td>{{ b.bank_account?.account_alias || b.bank_account?.bank_name || '-' }}</td>
                                <td class="text-sm">{{ formatDate(b.imported_at) }}</td>
                                <td class="text-center"><span class="text-emerald-600 font-semibold">{{ b.success_rows ?? 0 }}</span></td>
                                <td class="text-center"><span class="text-amber-600">{{ b.duplicate_rows ?? 0 }}</span></td>
                                <td class="text-center"><span class="text-red-500">{{ b.failed_rows ?? 0 }}</span></td>
                                <td>
                                    <span :class="['inline-block text-xs font-medium px-2 py-0.5 rounded-full', b.status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">
                                        {{ b.status === 'COMPLETED' ? 'Selesai' : 'Gagal' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button
                                        @click="confirmDeleteBatch(b)"
                                        :disabled="deletingBatchId === b.id"
                                        class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded-lg transition-colors"
                                        title="Hapus batch & transaksi"
                                    >
                                        <svg v-if="deletingBatchId === b.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile -->
                <div class="sm:hidden space-y-3">
                    <div v-for="b in batches" :key="b.id" class="mobile-card">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-medium text-plum text-sm">{{ b.file_name }}</p>
                            <button @click="confirmDeleteBatch(b)" :disabled="deletingBatchId === b.id" class="text-red-500 hover:text-red-700 p-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                            </button>
                        </div>
                        <p class="text-xs text-surface-500">{{ formatDate(b.imported_at) }}</p>
                        <div class="flex gap-3 mt-2 text-xs">
                            <span class="text-emerald-600">✓ {{ b.success_rows ?? 0 }}</span>
                            <span class="text-amber-600">⊘ {{ b.duplicate_rows ?? 0 }}</span>
                            <span class="text-red-500">✗ {{ b.failed_rows ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Trashed Batches Section -->
            <div v-if="trashedBatches?.length" class="glass-card p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button @click="showTrashed = !showTrashed" class="flex items-center gap-2 text-xs sm:text-sm font-semibold text-surface-600 hover:text-plum transition-colors">
                        <svg :class="['w-3.5 h-3.5 sm:w-4 sm:h-4 transition-transform', showTrashed ? 'rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                        <span>🗑️ Riwayat Dihapus ({{ trashedBatches.length }})</span>
                    </button>
                    <button 
                        v-if="showTrashed && selectedTrashed.length > 0"
                        @click="confirmMultipleForceDelete"
                        class="text-[10px] sm:text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg border border-red-200 transition-colors flex items-center gap-1"
                    >
                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        Hapus Terpilih ({{ selectedTrashed.length }})
                    </button>
                </div>
                <Transition name="expand">
                    <div v-show="showTrashed" class="mt-4 space-y-2">
                        <div v-for="b in trashedBatches" :key="b.id" class="p-3 rounded-xl bg-red-50/50 border border-red-100 space-y-2">
                            <div class="flex items-start gap-2">
                                <input type="checkbox" v-model="selectedTrashed" :value="b.id" class="w-4 h-4 mt-0.5 text-rose-500 border-rose-300 rounded focus:ring-rose-500 bg-white flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-surface-700 truncate">{{ b.file_name }}</p>
                                    <p class="text-[10px] text-surface-500">{{ b.success_rows }} transaksi · Dihapus {{ formatDate(b.deleted_at) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 pl-6">
                                <button @click="restoreBatch(b)" class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition-colors">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                    Pulihkan
                                </button>
                                <button @click="confirmForceDelete(b)" class="flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg border border-red-200 transition-colors">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    Hapus Permanen
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Import Batch?"
            :message="`File '${deleteTargetBatch?.file_name}' beserta ${deleteTargetBatch?.success_rows || 0} transaksi akan dihapus. Data dapat dipulihkan nanti.`"
            confirmText="Ya, Hapus"
            variant="warning"
            @confirm="executeDeleteBatch"
            @cancel="showDeleteModal = false"
        />

        <ConfirmModal
            :show="showForceDeleteModal"
            title="Hapus Permanen?"
            :message="`File '${forceDeleteTarget?.file_name}' beserta ${forceDeleteTarget?.success_rows || 0} transaksi akan dihapus PERMANEN. Tindakan ini TIDAK dapat dibatalkan.`"
            confirmText="Ya, Hapus Permanen"
            variant="danger"
            @confirm="executeForceDelete"
            @cancel="showForceDeleteModal = false"
        />

        <!-- Multiple Force Delete Confirmation Modal -->
        <ConfirmModal
            :show="showMultipleForceDeleteModal"
            title="Hapus Permanen Terpilih?"
            :message="`Anda akan menghapus secara permanen ${selectedTrashed.length} file import beserta semua transaksinya. Tindakan ini TIDAK dapat dibatalkan.`"
            confirmText="Ya, Hapus Semua"
            variant="danger"
            @confirm="executeMultipleForceDelete"
            @cancel="showMultipleForceDeleteModal = false"
        />
    </AppLayout>
</template>

<style scoped>
.slide-up-enter-active { transition: all 0.3s ease-out; }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(16px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
