<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from "vue";
import * as echarts from "echarts/core";
import { LineChart } from "echarts/charts";
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
} from "echarts/components";
import { CanvasRenderer } from "echarts/renderers";
import type { DailyTrendRow } from "@/api/observability";

echarts.use([
  LineChart,
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
  CanvasRenderer,
]);

const props = defineProps<{ data: DailyTrendRow[] }>();

const el = ref<HTMLDivElement | null>(null);
let inst: echarts.ECharts | null = null;

function render(): void {
  if (!el.value) return;
  inst ??= echarts.init(el.value);
  const dates = props.data.map((r) => r.date);
  inst.setOption({
    tooltip: { trigger: "axis" },
    legend: { data: ["调用次数", "成功率 %", "平均耗时(s)"], bottom: 0 },
    grid: { left: 60, right: 60, top: 30, bottom: 60, containLabel: true },
    xAxis: { type: "category", data: dates, boundaryGap: false },
    yAxis: [
      { type: "value", name: "次数" },
      { type: "value", name: "成功率%", max: 100 },
    ],
    series: [
      {
        name: "调用次数",
        type: "line",
        smooth: true,
        data: props.data.map((r) => r.total),
        itemStyle: { color: "#1677ff" },
        areaStyle: { opacity: 0.15 },
      },
      {
        name: "成功率 %",
        type: "line",
        smooth: true,
        yAxisIndex: 1,
        data: props.data.map((r) => r.success_rate),
        itemStyle: { color: "#52c41a" },
      },
      {
        name: "平均耗时(s)",
        type: "line",
        smooth: true,
        data: props.data.map((r) => +(r.avg_duration_ms / 1000).toFixed(2)),
        itemStyle: { color: "#722ed1" },
      },
    ],
  });
  inst.resize();
}

function handleResize(): void {
  inst?.resize();
}

onMounted(() => {
  render();
  window.addEventListener("resize", handleResize);
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", handleResize);
  inst?.dispose();
  inst = null;
});

watch(() => props.data, render, { deep: true });
</script>

<template>
  <a-card title="近期趋势" :bordered="false">
    <div ref="el" class="chart" />
    <a-empty
      v-if="!data.length"
      description="暂无趋势数据"
      :image-style="{ height: '60px' }"
      style="margin-top: -280px"
    />
  </a-card>
</template>

<style scoped>
.chart {
  width: 100%;
  height: 320px;
}
</style>
