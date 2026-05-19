<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login — SIKUBI" />

    <div class="min-h-screen bg-gradient-cream flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Background decorative elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-rose-200/20 blur-3xl animate-float" />
            <div class="absolute -bottom-48 -left-24 w-80 h-80 rounded-full bg-champagne-200/30 blur-3xl animate-float" style="animation-delay: 2s" />
            <div class="absolute top-1/3 right-1/4 w-64 h-64 rounded-full bg-rose-100/20 blur-2xl animate-float" style="animation-delay: 4s" />
        </div>

        <!-- Login Card -->
        <div class="w-full max-w-md relative animate-scale-in">
            <!-- Logo Section -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-white border border-rose-200 shadow-lg mb-4 p-3">
                    <img src="/images/bigenmi-logo.png" alt="Bigenmi" class="w-full h-full object-contain" />
                </div>
                <h1 class="text-3xl font-sans font-semibold text-plum tracking-tight">SIKUBI</h1>
                <p class="text-sm text-surface-600 mt-1 font-body">Sistem Keuangan Bigenmi</p>
                <div class="w-12 h-0.5 bg-gradient-rose mx-auto mt-3 rounded-full" />
            </div>

            <!-- Form Card -->
            <div class="glass-card p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Error Alert -->
                    <Transition name="slide-up">
                        <div v-if="form.errors.email" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            {{ form.errors.email }}
                        </div>
                    </Transition>

                    <!-- Email -->
                    <div>
                        <label class="label-text" for="login-email">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-surface-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <input
                                id="login-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="nama@bigenmi.co.id"
                                class="input-field !pl-11"
                            />
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="label-text" for="login-password">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-surface-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </div>
                            <input
                                id="login-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="input-field !pl-11 !pr-11"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-surface-500 hover:text-plum transition-colors"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn-primary w-full py-3 text-base"
                    >
                        <svg v-if="form.processing" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Masuk...' : 'Masuk' }}
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center mt-6">
                <p class="text-xs text-surface-500">PT Bigenmi Gemilang Indonesia</p>
                <p class="text-[10px] text-surface-500/60 mt-0.5">© 2026 · Internal Financial System</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.slide-up-enter-active { transition: all 0.3s ease-out; }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(8px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
