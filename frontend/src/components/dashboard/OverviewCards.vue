<script setup lang="ts">
import type { ObservabilityOverview } from "@/api/observability";

defineProps<{ data: ObservabilityOverview | null; loading?: boolean }>();

function formatMs(ms: number): string {
  if (!ms) return "-";
  if (ms >= 1000) return `${(ms / 1000).toFixed(2)} s`;
  return `${ms} ms`;
}
</script>

<template>
  <a-row :gutter="16">
    <a-col :xs="12" :md="6">
      <a-card :bordered="false" class="stat-card" :loading="loading">
        <a-statistic
          title="总调用数"
          :value="data?.total ?? 0"
          :value-style="{ color: '#1677ff' }"
        />
        <div class="stat-sub">
          成功 {{ data?.success ?? 0 }} · 失败 {{ data?.failed ?? 0 }}
        </div>
      </a-card>
    </a-col>
    <a-col :xs="12" :md="6">
      <a-card :bordered="false" class="stat-card" :loading="loading">
        <a-statistic
          title="成功率"
          :value="data?.success_rate ?? 0"
          :precision="2"
          suffix="%"
          :value-style="{
            color: (data?.success_rate ?? 0) >= 95 ? '#52c41a' : '#faad14',
          }"
        />
        <div class="stat-sub">
          窗口：{{ data?.start_date }} → {{ data?.end_date }}
        </div>
      </a-card>
    </a-col>
    <a-col :xs="12" :md="6">
      <a-card :bordered="false" class="stat-card" :loading="loading">
        <a-statistic
          title="平均耗时"
          :value="formatMs(data?.avg_duration_ms ?? 0)"
          :value-style="{ color: '#722ed1' }"
        />
        <div class="stat-sub">
          P95：{{ formatMs(data?.p95_duration_ms ?? 0) }}
        </div>
      </a-card>
    </a-col>
    <a-col :xs="12" :md="6">
      <a-card :bordered="false" class="stat-card" :loading="loading">
        <a-statistic
          title="最慢 Agent"
          :value="data?.slowest_agent?.name || '-'"
          :value-style="{ fontSize: '18px' }"
        />
        <div class="stat-sub">
          平均 {{ formatMs(data?.slowest_agent?.avg_duration_ms ?? 0) }}
        </div>
      </a-card>
    </a-col>
  </a-row>
</template>

<style scoped>
.stat-card {
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.stat-sub {
  margin-top: 6px;
  color: rgba(0, 0, 0, 0.45);
  font-size: 12px;
}
</style>
