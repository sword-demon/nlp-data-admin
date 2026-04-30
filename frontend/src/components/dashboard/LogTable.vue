<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import dayjs from "dayjs";
import { fetchLogs, AGENT_LABEL, LOG_STATUS_COLOR } from "@/api/observability";
import type { AgentLogRow } from "@/api/observability";

const list = ref<AgentLogRow[]>([]);
const loading = ref(false);

const filters = reactive({
  agent_name: "",
  status: "",
  limit: 50,
});

const columns = [
  { title: "ID", dataIndex: "id", key: "id", width: 70 },
  { title: "Agent", dataIndex: "agent_name", key: "agent_name", width: 160 },
  { title: "状态", dataIndex: "status", key: "status", width: 90 },
  { title: "耗时", dataIndex: "duration_ms", key: "duration_ms", width: 90 },
  { title: "用户", dataIndex: "user_id", key: "user_id", width: 70 },
  { title: "文章", dataIndex: "article_id", key: "article_id", width: 70 },
  { title: "输入摘要", dataIndex: "input_summary", key: "input_summary" },
  {
    title: "时间",
    dataIndex: "created_at",
    key: "created_at",
    width: 170,
  },
];

const statusOptions = [
  { label: "全部", value: "" },
  { label: "成功", value: "success" },
  { label: "失败", value: "failed" },
  { label: "运行中", value: "running" },
];

const agentOptions = [
  { label: "全部 Agent", value: "" },
  ...Object.entries(AGENT_LABEL).map(([v, l]) => ({ label: l, value: v })),
];

async function load(): Promise<void> {
  loading.value = true;
  try {
    list.value = await fetchLogs({
      agent_name: filters.agent_name || undefined,
      status: filters.status || undefined,
      limit: filters.limit,
    });
  } finally {
    loading.value = false;
  }
}

function format(t: string | null): string {
  return t ? dayjs(t).format("MM-DD HH:mm:ss") : "-";
}

function formatMs(ms: number): string {
  if (ms >= 1000) return `${(ms / 1000).toFixed(2)}s`;
  return `${ms}ms`;
}

onMounted(load);
defineExpose({ reload: load });
</script>

<template>
  <a-card title="最近日志" :bordered="false">
    <template #extra>
      <a-space>
        <a-select
          v-model:value="filters.agent_name"
          :options="agentOptions"
          style="width: 180px"
          size="small"
        />
        <a-select
          v-model:value="filters.status"
          :options="statusOptions"
          style="width: 120px"
          size="small"
        />
        <a-button size="small" type="primary" @click="load">刷新</a-button>
      </a-space>
    </template>

    <a-table
      :data-source="list"
      :columns="columns"
      :loading="loading"
      :pagination="false"
      :row-key="(r: AgentLogRow) => r.id"
      size="small"
      :scroll="{ x: 1100 }"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'agent_name'">
          {{ AGENT_LABEL[record.agent_name] || record.agent_name }}
        </template>
        <template v-else-if="column.key === 'status'">
          <a-tag :color="LOG_STATUS_COLOR[record.status] || 'default'">
            {{ record.status }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'duration_ms'">
          {{ formatMs(record.duration_ms) }}
        </template>
        <template v-else-if="column.key === 'input_summary'">
          <a-tooltip :title="record.input_summary">
            <span class="summary">{{ record.input_summary }}</span>
          </a-tooltip>
        </template>
        <template v-else-if="column.key === 'created_at'">
          {{ format(record.created_at) }}
        </template>
      </template>
    </a-table>
  </a-card>
</template>

<style scoped>
.summary {
  display: inline-block;
  max-width: 360px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: bottom;
  color: rgba(0, 0, 0, 0.65);
  font-size: 12px;
}
</style>
