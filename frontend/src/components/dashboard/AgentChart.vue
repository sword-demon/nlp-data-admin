<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from "vue";
import * as echarts from "echarts/core";
import { BarChart, PieChart } from "echarts/charts";
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
} from "echarts/components";
import { CanvasRenderer } from "echarts/renderers";
import { AGENT_LABEL, type AgentStatRow } from "@/api/observability";

echarts.use([
  BarChart,
  PieChart,
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
  CanvasRenderer,
]);

const props = defineProps<{ data: AgentStatRow[] }>();

const barEl = ref<HTMLDivElement | null>(null);
const pieEl = ref<HTMLDivElement | null>(null);
let barInst: echarts.ECharts | null = null;
let pieInst: echarts.ECharts | null = null;

function label(name: string): string {
  return AGENT_LABEL[name] || name;
}

function renderBar(): void {
  if (!barEl.value) return;
  barInst ??= echarts.init(barEl.value);
  const names = props.data.map((r) => label(r.name));
  barInst.setOption({
    tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
    legend: { data: ["调用次数", "平均耗时(s)"], bottom: 0 },
    grid: { left: 60, right: 60, top: 30, bottom: 80, containLabel: true },
    xAxis: {
      type: "category",
      data: names,
      axisLabel: { rotate: 20, interval: 0, margin: 12 },
    },
    yAxis: [
      { type: "value", name: "次数" },
      { type: "value", name: "耗时(s)" },
    ],
    series: [
      {
        name: "调用次数",
        type: "bar",
        data: props.data.map((r) => r.total),
        itemStyle: { color: "#1677ff" },
      },
      {
        name: "平均耗时(s)",
        type: "bar",
        yAxisIndex: 1,
        data: props.data.map((r) => +(r.avg_duration_ms / 1000).toFixed(2)),
        itemStyle: { color: "#722ed1" },
      },
    ],
  });
  barInst.resize();
}

function renderPie(): void {
  if (!pieEl.value) return;
  pieInst ??= echarts.init(pieEl.value);
  const totalAll = props.data.reduce((s, r) => s + r.total, 0);
  const totalSuccess = props.data.reduce((s, r) => s + r.success, 0);
  const totalFailed = props.data.reduce((s, r) => s + r.failed, 0);
  pieInst.setOption({
    tooltip: { trigger: "item", formatter: "{b}: {c} ({d}%)" },
    legend: { bottom: 0 },
    series: [
      {
        name: "调用结果",
        type: "pie",
        radius: ["45%", "70%"],
        label: { formatter: "{b}\n{d}%" },
        data: [
          {
            value: totalSuccess,
            name: "成功",
            itemStyle: { color: "#52c41a" },
          },
          { value: totalFailed, name: "失败", itemStyle: { color: "#f5222d" } },
          {
            value: Math.max(0, totalAll - totalSuccess - totalFailed),
            name: "运行中",
            itemStyle: { color: "#faad14" },
          },
        ],
      },
    ],
  });
  pieInst.resize();
}

function handleResize(): void {
  barInst?.resize();
  pieInst?.resize();
}

onMounted(() => {
  renderBar();
  renderPie();
  window.addEventListener("resize", handleResize);
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", handleResize);
  barInst?.dispose();
  pieInst?.dispose();
  barInst = null;
  pieInst = null;
});

watch(
  () => props.data,
  () => {
    renderBar();
    renderPie();
  },
  { deep: true },
);
</script>

<template>
  <a-row :gutter="16">
    <a-col :xs="24" :md="16">
      <a-card title="各 Agent 调用量 / 平均耗时" :bordered="false">
        <div ref="barEl" class="chart-bar" />
      </a-card>
    </a-col>
    <a-col :xs="24" :md="8">
      <a-card title="成功 / 失败分布" :bordered="false">
        <div ref="pieEl" class="chart-pie" />
      </a-card>
    </a-col>
  </a-row>
</template>

<style scoped>
.chart-bar {
  width: 100%;
  height: 360px;
}
.chart-pie {
  width: 100%;
  height: 320px;
}
</style>
