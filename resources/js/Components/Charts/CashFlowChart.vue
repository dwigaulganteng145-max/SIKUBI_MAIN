<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import * as echarts from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, LineChart, GridComponent, TooltipComponent, LegendComponent, CanvasRenderer]);

const props = defineProps({ data: Object });
const chartRef = ref(null);
let chart = null;

function formatRp(val) {
    if (val >= 1e9) return 'Rp ' + (val / 1e9).toFixed(1) + 'M';
    if (val >= 1e6) return 'Rp ' + (val / 1e6).toFixed(1) + 'jt';
    if (val >= 1e3) return 'Rp ' + (val / 1e3).toFixed(0) + 'rb';
    return 'Rp ' + val;
}

function getContainerWidth() {
    return chartRef.value?.clientWidth || 400;
}

function buildOption() {
    const d = props.data;
    if (!d) return {};
    const w = getContainerWidth();
    const isMobile = w < 480;
    const dateCount = d.dates?.length || 0;

    return {
        tooltip: {
            trigger: 'axis',
            backgroundColor: '#fff',
            borderColor: '#FFD0D6',
            borderWidth: 1,
            textStyle: { color: '#2C1929', fontSize: 12 },
            confine: true,
            formatter: (params) => {
                let s = `<div style="font-weight:600;margin-bottom:4px">${params[0].axisValue}</div>`;
                params.forEach(p => {
                    let c = typeof p.color === 'string' ? p.color : (p.seriesName === 'Pemasukan' ? '#10b981' : (p.seriesName === 'Pengeluaran' ? '#e11d48' : '#E8637A'));
                    s += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0">
                        <span style="width:8px;height:8px;border-radius:50%;background:${c}"></span>
                        <span>${p.seriesName}: <b>${formatRp(p.value)}</b></span></div>`;
                });
                return s;
            },
        },
        legend: {
            bottom: 0,
            textStyle: { color: '#8A7E70', fontSize: isMobile ? 10 : 11 },
            itemWidth: isMobile ? 10 : 12, itemHeight: isMobile ? 10 : 12, itemGap: isMobile ? 8 : 16,
        },
        grid: {
            left: isMobile ? 4 : 0,
            right: isMobile ? 4 : 8,
            top: 16,
            bottom: isMobile ? 50 : 40,
            containLabel: true,
        },
        xAxis: {
            type: 'category',
            data: d.dates,
            axisLine: { lineStyle: { color: '#DDD2C6' } },
            axisLabel: {
                color: '#8A7E70',
                fontSize: isMobile ? 9 : 10,
                rotate: isMobile ? 55 : (dateCount > 30 ? 45 : 0),
                interval: isMobile ? Math.max(0, Math.floor(dateCount / 6) - 1) : 'auto',
            },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            splitLine: { lineStyle: { color: '#EDE4DB', type: 'dashed' } },
            axisLabel: { color: '#8A7E70', fontSize: isMobile ? 9 : 10, formatter: (v) => formatRp(v) },
        },
        series: [
            {
                name: 'Pemasukan', type: 'bar', data: d.debitData,
                itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#34d399' }, { offset: 1, color: '#10b981' }
                ]), borderRadius: [4, 4, 0, 0] },
                barMaxWidth: isMobile ? 14 : 24,
            },
            {
                name: 'Pengeluaran', type: 'bar', data: d.creditData,
                itemStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#fb7185' }, { offset: 1, color: '#e11d48' }
                ]), borderRadius: [4, 4, 0, 0] },
                barMaxWidth: isMobile ? 14 : 24,
            },
            {
                name: 'Arus Bersih', type: 'line', data: d.netData,
                lineStyle: { color: '#E8637A', width: 2 },
                itemStyle: { color: '#E8637A' },
                symbol: isMobile ? 'none' : 'circle', symbolSize: 4,
                smooth: true,
            },
        ],
        animationDuration: 800,
        animationEasing: 'cubicInOut',
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
