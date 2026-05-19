<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import * as echarts from 'echarts/core';
import { PieChart } from 'echarts/charts';
import { TooltipComponent, LegendComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([PieChart, TooltipComponent, LegendComponent, CanvasRenderer]);

const props = defineProps({ data: Array });
const chartRef = ref(null);
let chart = null;

function formatRp(val) {
    if (val >= 1e9) return 'Rp ' + (val / 1e9).toFixed(1) + 'M';
    if (val >= 1e6) return 'Rp ' + (val / 1e6).toFixed(1) + 'jt';
    return 'Rp ' + val?.toLocaleString('id-ID');
}

const PALETTE = [
    '#E8637A', '#C49A4A', '#F4899A', '#34d399', '#60a5fa',
    '#f59e0b', '#a78bfa', '#f472b6', '#2dd4bf', '#fb923c',
];

function getContainerWidth() {
    return chartRef.value?.clientWidth || 400;
}

function buildOption() {
    const d = (props.data || []).map((item, i) => ({
        ...item,
        color: item.name === 'Belum Diklasifikasi' ? '#CBD5E1' : (item.color === '#E8637A' ? PALETTE[i % PALETTE.length] : (item.color || PALETTE[i % PALETTE.length])),
    }));
    const total = d.reduce((s, i) => s + (i.value || 0), 0);
    const hasMultiple = d.length > 1;
    const w = getContainerWidth();
    const isMobile = w < 480;

    return {
        tooltip: {
            trigger: 'item',
            backgroundColor: '#fff',
            borderColor: '#FFD0D6',
            borderWidth: 1,
            textStyle: { color: '#2C1929', fontSize: 12 },
            formatter: (p) => `<b>${p.name}</b><br/>${formatRp(p.value)} (${p.percent}%)`,
            confine: true,
        },
        legend: isMobile ? {
            orient: 'horizontal',
            bottom: 0,
            left: 'center',
            textStyle: { color: '#635850', fontSize: 10 },
            itemWidth: 8, itemHeight: 8, itemGap: 6,
            formatter: (name) => name.length > 14 ? name.substring(0, 14) + '…' : name,
        } : {
            orient: 'vertical',
            right: 10,
            top: 'center',
            textStyle: { color: '#635850', fontSize: 11 },
            itemWidth: 10, itemHeight: 10, itemGap: 8,
            formatter: (name) => name.length > 20 ? name.substring(0, 20) + '…' : name,
        },
        animationDuration: 800,
        animationEasing: 'cubicInOut',
        series: [{
            type: 'pie',
            radius: isMobile ? ['38%', '62%'] : ['45%', '70%'],
            center: isMobile ? ['50%', '42%'] : ['38%', '50%'],
            avoidLabelOverlap: true,
            label: {
                show: true,
                position: 'center',
                formatter: () => `{total|${formatRp(total)}}\n{sub|Total}`,
                rich: {
                    total: { fontSize: isMobile ? 13 : 15, fontWeight: 'bold', color: '#2C1929', lineHeight: isMobile ? 18 : 22 },
                    sub: { fontSize: isMobile ? 10 : 11, color: '#8A7E70', lineHeight: isMobile ? 14 : 18 },
                },
            },
            emphasis: {
                label: { show: true, fontSize: isMobile ? 13 : 15, fontWeight: 'bold' },
                itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.1)' },
            },
            itemStyle: {
                borderRadius: hasMultiple ? 6 : 0,
                borderColor: '#fff',
                borderWidth: hasMultiple ? 2 : 0,
            },
            data: d.map(item => ({
                value: item.value,
                name: item.name,
                itemStyle: { color: item.color },
            })),
        }],
    };
}

let resizeObserver = null;

function initChart() {
    if (!chartRef.value) return;
    if (chart) chart.dispose();
    chart = echarts.init(chartRef.value);
    chart.setOption(buildOption());
}

onMounted(() => {
    nextTick(() => {
        initChart();

        if (chartRef.value) {
            resizeObserver = new ResizeObserver(() => {
                requestAnimationFrame(() => {
                    if (chartRef.value && chartRef.value.clientWidth > 0 && chart) {
                        chart.setOption(buildOption(), true);
                        chart.resize();
                    }
                });
            });
            resizeObserver.observe(chartRef.value);
        }
    });
});

watch(() => props.data, () => {
    if (chart) {
        chart.setOption(buildOption(), true);
    } else {
        nextTick(initChart);
    }
}, { deep: true });

onBeforeUnmount(() => {
    if (resizeObserver) resizeObserver.disconnect();
    chart?.dispose();
    chart = null;
});
</script>

<template>
    <div ref="chartRef" class="w-full h-full min-h-[240px]"></div>
</template>
