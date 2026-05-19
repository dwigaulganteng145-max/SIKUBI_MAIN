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
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

// Edit modal state
const showEditModal = ref(false);
const editShowPassword = ref(false);
const editShowConfirm = ref(false);
const editForm = useForm({ name: '', email: '', password: '', password_confirmation: '' });
const editingUser = ref(null);

// Delete modal state
const showDeleteModal = ref(false);
const deleteTarget = ref(null);

function submit() {
    form.post('/users', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); showForm.value = false; addToast?.('Akun admin berhasil dibuat', 'success'); },
    });
}

function openEdit(user) {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.password_confirmation = '';
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
</script>

<template>
    <Head title="Kelola Admin — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="page-title">Kelola Admin Keuangan</h1>
                    <p class="text-sm text-surface-600 mt-1">Buat dan kelola akun Admin Keuangan</p>
                </div>
                <button @click="showForm = !showForm" class="btn-primary">+ Tambah Admin</button>
            </div>

            <!-- Add Form -->
            <Transition name="slide-up">
                <div v-if="showForm" class="glass-card p-6">
                    <h3 class="text-sm font-semibold text-plum mb-4">Buat Akun Admin Baru</h3>
                    <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <div class="sm:col-span-2">
                            <button type="submit" :disabled="form.processing" class="btn-primary">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Buat Akun
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- User List -->
            <div class="glass-card overflow-hidden">
                <div class="hidden sm:block table-container">
                    <table class="data-table">
                        <thead><tr><th>Nama</th><th>Email</th><th>Terakhir Login</th><th>Dibuat</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td class="font-semibold text-plum">{{ u.name }}</td>
                                <td class="text-sm">{{ u.email }}</td>
                                <td class="text-xs text-surface-500">{{ formatDate(u.last_login_at) }}</td>
                                <td class="text-xs text-surface-500">{{ formatDate(u.created_at) }}</td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <button @click="openEdit(u)" class="text-surface-500 hover:text-plum p-1 rounded-lg hover:bg-rose-50 transition-colors" title="Edit akun">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                        </button>
                                        <button @click="confirmDelete(u)" class="text-surface-500 hover:text-red-500 p-1 rounded-lg hover:bg-red-50 transition-colors" title="Hapus akun">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Mobile -->
                <div class="sm:hidden p-4 space-y-3">
                    <div v-for="u in users" :key="u.id" class="mobile-card">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-semibold text-plum">{{ u.name }}</p>
                            <div class="flex items-center gap-1">
                                <button @click="openEdit(u)" class="text-surface-500 hover:text-plum p-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg></button>
                                <button @click="confirmDelete(u)" class="text-surface-500 hover:text-red-500 p-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg></button>
                            </div>
                        </div>
                        <p class="text-xs text-surface-500">{{ u.email }}</p>
                        <p class="text-[10px] text-surface-500 mt-1">Login terakhir: {{ formatDate(u.last_login_at) }}</p>
                    </div>
                </div>
                <div v-if="!users?.length" class="p-12 text-center">
                    <svg class="w-12 h-12 text-surface-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    <p class="text-surface-500">Belum ada akun Admin Keuangan. Klik "+ Tambah Admin" untuk membuat.</p>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showEditModal" class="fixed inset-0 bg-plum/30 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showEditModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 animate-fade-in">
                        <h3 class="text-lg font-display font-bold text-plum mb-1">Edit Admin</h3>
                        <p class="text-xs text-surface-500 mb-4">Perbarui data akun <strong>{{ editingUser?.name }}</strong></p>

                        <form @submit.prevent="submitEdit" class="space-y-4">
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
                            <div>
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
                            <div v-if="editForm.password">
                                <label class="label-text">Konfirmasi Password Baru</label>
                                <div class="relative">
                                    <input v-model="editForm.password_confirmation" :type="editShowConfirm ? 'text' : 'password'" class="input-field !pr-10" placeholder="Ketik ulang password baru" />
                                    <button type="button" @click="editShowConfirm = !editShowConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                        <svg v-if="!editShowConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-2 justify-end pt-2">
                                <button type="button" @click="showEditModal = false" class="btn-ghost text-sm">Batal</button>
                                <button type="submit" :disabled="editForm.processing" class="btn-primary text-sm">
                                    <svg v-if="editForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
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
.slide-up-enter-active { transition: all 0.3s ease-out; }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(16px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-8px); }
.fade-enter-active { transition: opacity 0.2s ease; }
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
