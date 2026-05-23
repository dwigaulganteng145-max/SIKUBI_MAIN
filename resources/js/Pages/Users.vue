<script setup>
import { ref, inject } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

defineProps({ users: Array });
const addToast = inject('addToast');
const showForm = ref(false);
const showPassword = ref(false);
const showConfirm = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    can_import: true,
    can_manage_accounts: true,
    can_manage_settings: true,
    can_detect_anomalies: true,
    can_edit_transactions: true,
    can_manage_cash_transactions: true,
});

// Edit modal state
const showEditModal = ref(false);
const editShowPassword = ref(false);
const editShowConfirm = ref(false);
const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    can_import: true,
    can_manage_accounts: true,
    can_manage_settings: true,
    can_detect_anomalies: true,
    can_edit_transactions: true,
    can_manage_cash_transactions: true,
});
const editingUser = ref(null);

// Delete modal state
const showDeleteModal = ref(false);
const deleteTarget = ref(null);

function submit() {
    form.post('/users', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
            addToast?.('Akun admin berhasil dibuat', 'success');
        },
    });
}

function openEdit(user) {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.can_import = !!user.can_import;
    editForm.can_manage_accounts = !!user.can_manage_accounts;
    editForm.can_manage_settings = !!user.can_manage_settings;
    editForm.can_detect_anomalies = !!user.can_detect_anomalies;
    editForm.can_edit_transactions = !!user.can_edit_transactions;
    editForm.can_manage_cash_transactions = !!user.can_manage_cash_transactions;
    editShowPassword.value = false;
    editShowConfirm.value = false;
    showEditModal.value = true;
}

function submitEdit() {
    if (!editingUser.value) return;
    editForm.put(`/users/${editingUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingUser.value = null;
            editForm.reset();
            addToast?.('Data admin berhasil diperbarui', 'success');
        },
    });
}

function confirmDelete(user) {
    deleteTarget.value = user;
    showDeleteModal.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;
    router.delete(`/users/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { addToast?.('Akun berhasil dihapus', 'success'); },
        onFinish: () => { showDeleteModal.value = false; deleteTarget.value = null; },
    });
}

function formatDate(d) {
    if (!d) return 'Belum pernah';
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getInitials(name) {
    return name ? name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() : 'A';
}
</script>

<template>
    <Head title="Kelola Admin — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="page-title text-lg sm:text-2xl font-medium">Kelola Admin Keuangan</h1>
                    <p class="text-xs sm:text-sm text-surface-600 mt-0.5">Pantau profil, info masuk, serta atur hak akses operasional para Admin</p>
                </div>
                <button @click="showForm = !showForm" class="btn-primary gap-1.5 text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    {{ showForm ? 'Tutup Form' : 'Tambah Admin' }}
                </button>
            </div>

            <!-- Add Form -->
            <Transition name="slide-up">
                <div v-if="showForm" class="glass-card p-6 border-l-4 border-rose-400/60">
                    <h3 class="text-sm font-semibold text-plum mb-4">Buat Akun Admin Baru & Hak Akses</h3>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="label-text">Nama Lengkap</label>
                                <input v-model="form.name" class="input-field" placeholder="cth: Siti Nurhaliza" required />
                                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="label-text">Email</label>
                                <input v-model="form.email" type="email" class="input-field" placeholder="cth: admin2@bigenmi.co.id" required />
                                <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <label class="label-text">Password</label>
                                <div class="relative">
                                    <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input-field !pr-10" placeholder="Min. 8 karakter" required />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                        <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                    </button>
                                </div>
                                <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                            </div>
                            <div>
                                <label class="label-text">Konfirmasi Password</label>
                                <div class="relative">
                                    <input v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" class="input-field !pr-10" placeholder="Ketik ulang password" required />
                                    <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                        <svg v-if="!showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Hak Akses Checkboxes -->
                        <div class="bg-rose-50/20 p-4 rounded-xl border border-rose-100/50">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-plum mb-3">Hak Akses Fitur (Permissions)</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input v-model="form.can_import" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                    <div>
                                        <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Import Mutasi Bank (CSV)</p>
                                        <p class="text-[11px] text-surface-500">Bisa upload file mutasi CSV rekening bank.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input v-model="form.can_manage_accounts" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                    <div>
                                        <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Kelola Rekening Bank</p>
                                        <p class="text-[11px] text-surface-500">Bisa menambah/edit/hapus rekening bank.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input v-model="form.can_manage_settings" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                    <div>
                                        <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Kelola Kategori & Aturan</p>
                                        <p class="text-[11px] text-surface-500">Bisa mengedit kategori dan aturan klasifikasi.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input v-model="form.can_detect_anomalies" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                    <div>
                                        <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Jalankan Deteksi Anomali</p>
                                        <p class="text-[11px] text-surface-500">Bisa memicu pencarian transaksi anomali.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input v-model="form.can_edit_transactions" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                    <div>
                                        <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Edit & Klasifikasi Transaksi</p>
                                        <p class="text-[11px] text-surface-500">Bisa mengedit deskripsi & kategori transaksi.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input v-model="form.can_manage_cash_transactions" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                    <div>
                                        <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Entri Transaksi Tunai</p>
                                        <p class="text-[11px] text-surface-500">Bisa menambah & menghapus transaksi tunai.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="submit" :disabled="form.processing" class="btn-primary gap-1.5 text-sm">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Buat Akun & Hak Akses
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Premium Card Grid Layout -->
            <div v-if="users?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="u in users" :key="u.id" class="glass-card p-5 border border-rose-50/50 hover:shadow-card-hover transition-all duration-300 flex flex-col justify-between group">
                    <!-- Top Info Section -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-rose flex items-center justify-center font-bold text-white shadow-soft text-sm select-none">
                                {{ getInitials(u.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-plum truncate text-base leading-tight">{{ u.name }}</h3>
                                <p class="text-xs text-surface-500 truncate mt-0.5">{{ u.email }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-gold border border-rose-100 shadow-sm self-start">
                                Admin
                            </span>
                        </div>

                        <!-- Login Info -->
                        <div class="bg-cream-100/35 p-3 rounded-xl space-y-1.5 text-[11px] text-surface-600 border border-cream-200/25">
                            <div class="flex justify-between">
                                <span class="text-surface-400">Login Terakhir</span>
                                <span class="font-medium text-plum">{{ formatDate(u.last_login_at) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-surface-400">Dibuat</span>
                                <span class="font-medium text-plum">{{ formatDate(u.created_at) }}</span>
                            </div>
                        </div>

                        <!-- Hak Akses Badges -->
                        <div class="space-y-2">
                            <span class="block text-[10px] font-bold text-plum/70 uppercase tracking-wider">Hak Akses Aktif:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-semibold transition-all', u.can_import ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-200/60 line-through']">
                                    Import CSV
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-semibold transition-all', u.can_manage_accounts ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-200/60 line-through']">
                                    Rekening Bank
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-semibold transition-all', u.can_manage_settings ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-200/60 line-through']">
                                    Kategori & Aturan
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-semibold transition-all', u.can_detect_anomalies ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-200/60 line-through']">
                                    Deteksi Anomali
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-semibold transition-all', u.can_edit_transactions ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-200/60 line-through']">
                                    Edit Transaksi
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-semibold transition-all', u.can_manage_cash_transactions ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-200/60 line-through']">
                                    Transaksi Tunai
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-4 mt-4 border-t border-rose-50/50">
                        <button @click="openEdit(u)" class="text-xs font-semibold text-plum hover:text-rose-gold transition-colors py-1.5 px-3 rounded-lg hover:bg-rose-50/40 border border-rose-100/30 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                            Edit
                        </button>
                        <button @click="confirmDelete(u)" class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors py-1.5 px-3 rounded-lg hover:bg-red-50/30 flex items-center gap-1 border border-red-100/20">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            Hapus
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="glass-card p-12 text-center">
                <svg class="w-12 h-12 text-surface-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                <p class="text-surface-500">Belum ada akun Admin Keuangan. Klik tombol "Tambah Admin" di atas untuk membuat akun admin pertama.</p>
            </div>
        </div>

        <!-- Edit Modal (Teleported to body) -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showEditModal" class="fixed inset-0 bg-plum/30 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showEditModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6 animate-scale-up max-h-[90vh] overflow-y-auto border border-rose-50/50">
                        <div class="flex items-center justify-between pb-3 border-b border-rose-100/50 mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-plum">Edit Data & Hak Akses Admin</h3>
                                <p class="text-xs text-surface-500 mt-0.5">Sesuaikan info login dan hak akses operasional <strong>{{ editingUser?.name }}</strong></p>
                            </div>
                            <button @click="showEditModal = false" class="text-surface-400 hover:text-plum p-1 rounded-lg hover:bg-rose-50 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitEdit" class="space-y-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="label-text">Nama Lengkap</label>
                                    <input v-model="editForm.name" class="input-field" required />
                                    <p v-if="editForm.errors.name" class="text-red-500 text-xs mt-1">{{ editForm.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="label-text">Email</label>
                                    <input v-model="editForm.email" type="email" class="input-field" required />
                                    <p v-if="editForm.errors.email" class="text-red-500 text-xs mt-1">{{ editForm.errors.email }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label-text">Password Baru <span class="text-surface-400 font-normal">(kosongkan jika tidak ingin mengubah)</span></label>
                                    <div class="relative">
                                        <input v-model="editForm.password" :type="editShowPassword ? 'text' : 'password'" class="input-field !pr-10" placeholder="Min. 8 karakter" />
                                        <button type="button" @click="editShowPassword = !editShowPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                            <svg v-if="!editShowPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                        </button>
                                    </div>
                                    <p v-if="editForm.errors.password" class="text-red-500 text-xs mt-1">{{ editForm.errors.password }}</p>
                                </div>
                                <div v-if="editForm.password" class="sm:col-span-2">
                                    <label class="label-text">Konfirmasi Password Baru</label>
                                    <div class="relative">
                                        <input v-model="editForm.password_confirmation" :type="editShowConfirm ? 'text' : 'password'" class="input-field !pr-10" placeholder="Ketik ulang password baru" />
                                        <button type="button" @click="editShowConfirm = !editShowConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                            <svg v-if="!editShowConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hak Akses Edit Section -->
                            <div class="bg-rose-50/20 p-4 rounded-xl border border-rose-100/50">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-plum mb-3">Hak Akses Fitur (Permissions)</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input v-model="editForm.can_import" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                        <div>
                                            <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Import Mutasi Bank (CSV)</p>
                                            <p class="text-[11px] text-surface-500">Mengupload file CSV mutasi bank.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input v-model="editForm.can_manage_accounts" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                        <div>
                                            <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Kelola Rekening Bank</p>
                                            <p class="text-[11px] text-surface-500">Menambah/edit/hapus rekening bank.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input v-model="editForm.can_manage_settings" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                        <div>
                                            <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Kelola Kategori & Aturan</p>
                                            <p class="text-[11px] text-surface-500">Mengedit kategori dan aturan klasifikasi.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input v-model="editForm.can_detect_anomalies" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                        <div>
                                            <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Jalankan Deteksi Anomali</p>
                                            <p class="text-[11px] text-surface-500">Memicu sistem melakukan deteksi anomali.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input v-model="editForm.can_edit_transactions" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                        <div>
                                            <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Edit & Klasifikasi Transaksi</p>
                                            <p class="text-[11px] text-surface-500">Mengubah rincian data transaksi.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input v-model="editForm.can_manage_cash_transactions" type="checkbox" class="rounded border-rose-300 text-rose-500 focus:ring-rose-500 mt-1" />
                                        <div>
                                            <p class="text-sm font-semibold text-plum group-hover:text-rose-600 transition-colors">Entri Transaksi Tunai</p>
                                            <p class="text-[11px] text-surface-500">Menambah/hapus data transaksi kas tunai.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="flex gap-2 justify-end pt-2 border-t border-rose-100/50">
                                <button type="button" @click="showEditModal = false" class="btn-ghost text-xs py-2 px-4 !rounded-xl">Batal</button>
                                <button type="submit" :disabled="editForm.processing" class="btn-primary text-xs py-2 px-4 !rounded-xl gap-1.5 flex items-center justify-center">
                                    <svg v-if="editForm.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Akun Admin?"
            :message="`Akun '${deleteTarget?.name}' (${deleteTarget?.email}) akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirmText="Ya, Hapus"
            cancelText="Batal"
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
.fade-enter-active { transition: opacity 0.25s ease-out; }
.fade-leave-active { transition: opacity 0.15s ease-in; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
