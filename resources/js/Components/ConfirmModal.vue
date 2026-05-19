<script setup>
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: 'Apakah Anda yakin?' },
    confirmText: { type: String, default: 'Ya, Lanjutkan' },
    cancelText: { type: String, default: 'Batal' },
    variant: { type: String, default: 'danger' }, // danger | warning | info
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

function onBackdropClick() {
    if (!props.processing) emit('cancel');
}

const variantStyles = {
    danger: { icon: 'bg-red-50', iconColor: 'text-red-500', btn: 'bg-red-500 hover:bg-red-600 focus:ring-red-300' },
    warning: { icon: 'bg-amber-50', iconColor: 'text-amber-500', btn: 'bg-amber-500 hover:bg-amber-600 focus:ring-amber-300' },
    info: { icon: 'bg-blue-50', iconColor: 'text-blue-500', btn: 'bg-blue-500 hover:bg-blue-600 focus:ring-blue-300' },
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-[200] flex items-center justify-center p-4" @click.self="onBackdropClick">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-plum/25 backdrop-blur-sm" />

                <!-- Modal -->
                <div class="relative w-full max-w-sm sm:max-w-md bg-white rounded-2xl shadow-2xl border border-rose-100/60 overflow-hidden animate-modal-in mx-2">
                    <!-- Body -->
                    <div class="p-6 text-center">
                        <!-- Icon -->
                        <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4', variantStyles[variant]?.icon || variantStyles.danger.icon]">
                            <!-- Danger icon -->
                            <svg v-if="variant === 'danger'" :class="['w-7 h-7', variantStyles.danger.iconColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            <!-- Warning icon -->
                            <svg v-else-if="variant === 'warning'" :class="['w-7 h-7', variantStyles.warning.iconColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <!-- Info icon -->
                            <svg v-else :class="['w-7 h-7', variantStyles.info.iconColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                        </div>

                        <h3 class="text-lg font-display font-bold text-plum mb-2">{{ title }}</h3>
                        <p class="text-sm text-surface-600 leading-relaxed">{{ message }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 px-6 pb-6">
                        <button
                            @click="emit('cancel')"
                            :disabled="processing"
                            class="flex-1 px-4 py-2.5 text-sm font-semibold text-surface-700 bg-cream-200/70 hover:bg-cream-300 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-rose-200"
                        >{{ cancelText }}</button>
                        <button
                            @click="emit('confirm')"
                            :disabled="processing"
                            :class="['flex-1 px-4 py-2.5 text-sm font-semibold text-white rounded-xl transition-all focus:outline-none focus:ring-2', variantStyles[variant]?.btn || variantStyles.danger.btn]"
                        >
                            <svg v-if="processing" class="w-4 h-4 animate-spin mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span v-else>{{ confirmText }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active { transition: all 0.25s ease-out; }
.modal-leave-active { transition: all 0.15s ease-in; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .animate-modal-in { transform: scale(0.95) translateY(8px); }

@keyframes modalIn {
    from { transform: scale(0.95) translateY(8px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
.animate-modal-in { animation: modalIn 0.25s ease-out; }
</style>
