<script setup lang="ts">
import { onMounted, ref } from "vue";
import { message } from "ant-design-vue";
import dayjs, { type Dayjs } from "dayjs";
import { useAuthStore } from "@/stores/auth";
import {
  fetchOverview,
  fetchAgentStats,
  fetchTrend,
} from "@/api/observability";
import type {
  ObservabilityOverview,
  AgentStatRow,
  DailyTrendRow,
} from "@/api/observability";
import OverviewCards from "@/components/dashboard/OverviewCards.vue";
import AgentChart from "@/components/dashboard/AgentChart.vue";
import TrendChart from "@/components/dashboard/TrendChart.vue";
import LogTable from "@/components/dashboard/LogTable.vue";

const auth = useAuthStore();

const loading = ref(false);
const overview = ref<ObservabilityOverview | null>(null);
const agents = ref<AgentStatRow[]>([]);
const trend = ref<DailyTrendRow[]>([]);

// 默认最近 7 天
const range = ref<[Dayjs, Dayjs]>([dayjs().subtract(6, "day"), dayjs()]);

async function load(): Promise<void> {
  if (auth.user?.role !== "admin") {
    message.warning("仅管理员可查看数据看板");
    return;
  }
  loading.value = true;
  const params = {
    start_date: range.value[0].format("YYYY-MM-DD"),
    end_date: range.value[1].format("YYYY-MM-DD"),
  };
  try {
    const [ov, ag, tr] = await Promise.all([
      fetchOverview(params),
      fetchAgentStats(params),
      fetchTrend(params),
    ]);
    overview.value = ov;
    agents.value = ag;
    trend.value = tr;
  } catch (e) {
    const anyE = e as {
      response?: { status?: number; data?: { message?: string } };
    };
    if (anyE.response?.status === 403) {
      message.error("无权限访问（需 admin 角色）");
    } else {
      message.error(anyE.response?.data?.message || "加载失败");
    }
  } finally {
    loading.value = false;
  }
}

function handleRangeChange(): void {
  load();
}

onMounted(load);
</script>

<template>
  <div class="dashboard-page">
    <a-page-header title="数据看板" sub-title="AI Agent 调用可观测性" />

    <a-card :bordered="false" style="margin-bottom: 12px">
      <a-space :size="12" wrap>
        <span>日期范围：</span>
        <a-range-picker
          v-model:value="range"
          :allow-clear="false"
          @change="handleRangeChange"
        />
        <a-button :loading="loading" type="primary" @click="load">
          刷新
        </a-button>
      </a-space>
    </a-card>

    <OverviewCards :data="overview" :loading="loading" />

    <div style="margin-top: 16px">
      <AgentChart :data="agents" />
    </div>

    <div style="margin-top: 16px">
      <TrendChart :data="trend" />
    </div>

    <div style="margin-top: 16px">
      <LogTable />
    </div>
  </div>
</template>

<style scoped>
.dashboard-page {
  max-width: 1400px;
  margin: 0 auto;
}
</style>
