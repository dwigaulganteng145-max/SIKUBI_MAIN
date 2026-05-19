<script setup>
import { ref, computed, watch } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const emit = defineEmits(['update']);
const props = defineProps({
    initialFrom: { type: String, default: null },
    initialTo: { type: String, default: null },
    initialPreset: { type: String, default: null },
});

const activePreset = ref(props.initialPreset || '');
const dateRange = ref(
    props.initialFrom && props.initialTo
        ? [new Date(props.initialFrom), new Date(props.initialTo)]
        : null
);

const presets = [
    { key: 'this_month', label: 'Bulan Ini' },
    { key: 'last_month', label: 'Bulan Lalu' },
    { key: 'last_3_months', label: '3 Bulan' },
    { key: 'this_year', label: 'Tahun Ini' },
];

const activeLabel = computed(() => {
    if (activePreset.value) {
        if (activePreset.value === 'this_month') {
            return new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        }
        if (activePreset.value === 'last_month') {
            const d = new Date();
            d.setMonth(d.getMonth() - 1);
            return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        }
        return presets.find(p => p.key === activePreset.value)?.label || 'Pilih Periode';
    }
    if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
        const f = new Date(dateRange.value[0]).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        const t = new Date(dateRange.value[1]).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        return `${f} – ${t}`;
    }
    return 'Semua Periode';
});

function selectPreset(key) {
    activePreset.value = key;
    dateRange.value = null;
    emit('update', { preset: key, date_from: null, date_to: null });
}

function onDateChange(val) {
    if (val && val[0] && val[1]) {
        activePreset.value = '';
        const from = formatDate(val[0]);
        const to = formatDate(val[1]);
        emit('update', { preset: null, date_from: from, date_to: to });
    }
}

function clearFilter() {
    activePreset.value = '';
    dateRange.value = null;
    emit('update', { preset: null, date_from: null, date_to: null });
}

function formatDate(d) {
    const dt = new Date(d);
    return dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
}
</script>

<template>
    <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0 sm:overflow-visible">
        <!-- Preset Buttons -->
        <div class="flex items-center gap-1 bg-cream-200/60 rounded-xl p-1 flex-shrink-0">
            <button
                v-for="p in presets" :key="p.key"
                :class="[
                    'px-2 sm:px-2.5 py-1 sm:py-1.5 text-[10px] sm:text-[11px] font-semibold rounded-lg transition-all whitespace-nowrap',
                    activePreset === p.key
                        ? 'bg-white text-plum shadow-soft'
                        : 'text-surface-600 hover:text-plum'
                ]"
                @click="selectPreset(p.key)"
            >{{ p.label }}</button>
            <button
                v-if="activePreset || (dateRange && dateRange[0])"
                class="px-1.5 sm:px-2 py-1 sm:py-1.5 text-[10px] sm:text-[11px] font-semibold rounded-lg text-red-500 hover:bg-red-50 transition-all"
                @click="clearFilter"
                title="Hapus filter"
            >
                <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <!-- Custom Date Range Picker -->
        <div class="relative">
            <VueDatePicker
                v-model="dateRange"
                range
                :enable-time-picker="false"
                format="dd/MM/yyyy"
                :teleport="true"
                @update:model-value="onDateChange"
            >
                <template #trigger>
                    <button class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white border border-surface-200/80 rounded-lg text-xs font-medium text-plum hover:border-rose-gold transition-all shadow-sm">
                        <svg class="w-4 h-4 text-rose-gold flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <span class="whitespace-nowrap">{{ activeLabel }}</span>
                    </button>
                </template>
            </VueDatePicker>
        </div>
    </div>
</template>

<style>
/* Override VueDatePicker theme to match Bigenmi palette */
.dp__theme_light {
    --dp-primary-color: #E8637A;
    --dp-primary-text-color: #fff;
    --dp-secondary-color: #FFD0D6;
    --dp-border-color: #EDE4DB;
    --dp-menu-border-color: #FFD0D6;
    --dp-hover-color: #FFF5F6;
    --dp-hover-text-color: #2C1929;
    --dp-background-color: #fff;
    --dp-text-color: #2C1929;
    --dp-border-radius: 12px;
    --dp-font-family: 'Outfit', system-ui, sans-serif;
    --dp-font-size: 0.8rem;
}
</style>
