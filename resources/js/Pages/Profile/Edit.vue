<script setup>
import { ref, inject, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const user = page.props.auth?.user;
const addToast = inject('addToast');

const showCurrent = ref(true);
const showPassword = ref(false);
const showConfirm = ref(false);

const profileForm = useForm({
    name: user?.name || '',
    email: user?.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const roleLabel = user?.role === 'DIREKTUR' ? 'Pimpinan' : 'Admin Keuangan';

const rolePermissions = computed(() => {
    if (user?.role === 'DIREKTUR') {
        return [
            { icon: '📊', label: 'Lihat Dashboard & Laporan' },
            { icon: '📋', label: 'Lihat Transaksi' },
            { icon: '👥', label: 'Kelola Akun Admin' },
            { icon: '🏷️', label: 'Lihat Kategori & Aturan' },
        ];
    }
    return [
        { icon: '📊', label: 'Lihat Dashboard & Laporan' },
        { icon: '📥', label: 'Import Data Mutasi Bank' },
        { icon: '📋', label: 'Kelola Transaksi' },
        { icon: '🏦', label: 'Kelola Rekening Bank' },
        { icon: '⚠️', label: 'Deteksi Anomali' },
        { icon: '🏷️', label: 'Kelola Kategori & Aturan' },
    ];
});

function updateProfile() {
    profileForm.patch('/profile', {
        preserveScroll: true,
        onSuccess: () => addToast?.('Profil berhasil diperbarui', 'success'),
    });
}

function updatePassword() {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            addToast?.('Password berhasil diubah', 'success');
        },
    });
}

function formatDate(d) {
    if (!d) return 'Belum pernah';
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function memberSince(d) {
    if (!d) return '-';
    const created = new Date(d);
    const now = new Date();
    const diffMs = now - created;
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (days < 1) return 'Hari ini';
    if (days < 30) return `${days} hari`;
    if (days < 365) return `${Math.floor(days / 30)} bulan`;
    return `${Math.floor(days / 365)} tahun ${Math.floor((days % 365) / 30)} bulan`;
}
</script>

<template>
    <Head title="Profil Saya — SIKUBI" />
    <AppLayout>
        <div class="space-y-6 animate-fade-in">
            <div>
                <h1 class="page-title">Profil Saya</h1>
                <p class="text-sm text-surface-600 mt-1">Kelola informasi akun Anda</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- LEFT: Forms (2 cols) -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Profile Card -->
                    <div class="glass-card p-6 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-rose flex items-center justify-center text-xl font-bold text-white shadow-soft flex-shrink-0">
                            {{ (user?.name?.[0] || 'U').toUpperCase() }}
                        </div>
                        <div>
                            <p class="text-lg font-display font-bold text-plum">{{ user?.name }}</p>
                            <p class="text-sm text-surface-500">{{ user?.email }}</p>
                            <span class="badge-blue text-[10px] mt-1 inline-block">{{ roleLabel }}</span>
                        </div>
                    </div>

                    <!-- Update Name & Email -->
                    <div class="glass-card p-6">
                        <h3 class="text-sm font-semibold text-plum mb-4">Informasi Akun</h3>
                        <form @submit.prevent="updateProfile" class="space-y-4">
                            <div>
                                <label class="label-text">Nama Lengkap</label>
                                <input v-model="profileForm.name" class="input-field" required />
                                <p v-if="profileForm.errors.name" class="text-red-500 text-xs mt-1">{{ profileForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="label-text">Email</label>
                                <input v-model="profileForm.email" type="email" class="input-field" required />
                                <p v-if="profileForm.errors.email" class="text-red-500 text-xs mt-1">{{ profileForm.errors.email }}</p>
                            </div>
                            <button type="submit" :disabled="profileForm.processing" class="btn-primary text-sm">
                                <svg v-if="profileForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Simpan Perubahan
                            </button>
                        </form>
                    </div>

                    <!-- Update Password -->
                    <div class="glass-card p-6">
                        <h3 class="text-sm font-semibold text-plum mb-4">Ubah Password</h3>
                        <form @submit.prevent="updatePassword" class="space-y-4">
                            <div>
                                <label class="label-text">Password Saat Ini</label>
                                <div class="relative">
                                    <input v-model="passwordForm.current_password" :type="showCurrent ? 'text' : 'password'" class="input-field !pr-10" required />
                                    <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                        <svg v-if="!showCurrent" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.current_password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.current_password }}</p>
                            </div>
                            <div>
                                <label class="label-text">Password Baru</label>
                                <div class="relative">
                                    <input v-model="passwordForm.password" :type="showPassword ? 'text' : 'password'" class="input-field !pr-10" placeholder="Min. 8 karakter" required />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                        <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                            </div>
                            <div>
                                <label class="label-text">Konfirmasi Password Baru</label>
                                <div class="relative">
                                    <input v-model="passwordForm.password_confirmation" :type="showConfirm ? 'text' : 'password'" class="input-field !pr-10" placeholder="Ketik ulang password baru" required />
                                    <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-500 hover:text-plum transition-colors" tabindex="-1">
                                        <svg v-if="!showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" :disabled="passwordForm.processing" class="btn-primary text-sm">
                                <svg v-if="passwordForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Ubah Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT: Info sidebar (1 col) -->
                <div class="space-y-6">
                    <!-- Account Stats -->
                    <div class="glass-card p-5">
                        <h3 class="text-sm font-semibold text-plum mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                            Info Akun
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2 border-b border-rose-100/40">
                                <span class="text-xs text-surface-500">Role</span>
                                <span class="text-xs font-semibold text-plum">{{ roleLabel }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-rose-100/40">
                                <span class="text-xs text-surface-500">Bergabung</span>
                                <span class="text-xs font-semibold text-plum">{{ memberSince(user?.created_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-rose-100/40">
                                <span class="text-xs text-surface-500">Terdaftar Sejak</span>
                                <span class="text-xs text-surface-600">{{ formatDate(user?.created_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-xs text-surface-500">Login Terakhir</span>
                                <span class="text-xs text-surface-600">{{ formatDate(user?.last_login_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Role Permissions -->
                    <div class="glass-card p-5">
                        <h3 class="text-sm font-semibold text-plum mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                            Hak Akses Anda
                        </h3>
                        <ul class="space-y-2">
                            <li v-for="(perm, i) in rolePermissions" :key="i" class="flex items-center gap-2.5 py-1.5 text-xs text-surface-600">
                                <span class="text-sm">{{ perm.icon }}</span>
                                <span>{{ perm.label }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Security Tips -->
                    <div class="glass-card p-5 border-l-4 border-l-amber-400">
                        <h3 class="text-sm font-semibold text-plum mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                            Tips Keamanan
                        </h3>
                        <ul class="space-y-2 text-xs text-surface-600">
                            <li class="flex items-start gap-2">
                                <span class="text-surface-400 mt-0.5">•</span>
                                <span>Gunakan password minimal <strong>8 karakter</strong> dengan kombinasi huruf besar, kecil, dan angka.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-surface-400 mt-0.5">•</span>
                                <span>Jangan gunakan password yang sama dengan akun lain.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-surface-400 mt-0.5">•</span>
                                <span>Ganti password secara berkala untuk keamanan.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-surface-400 mt-0.5">•</span>
                                <span>Selalu <strong>logout</strong> saat menggunakan perangkat bersama.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
