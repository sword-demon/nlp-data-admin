<script setup lang="ts">
import { onMounted, reactive, computed } from "vue";
import { useRouter } from "vue-router";
import { Modal, message } from "ant-design-vue";
import dayjs from "dayjs";
import type { TablePaginationConfig } from "ant-design-vue";
import { ARTICLE_STATUS_LABEL, ARTICLE_STATUS_COLOR } from "@/api/article";
import { useArticleStore } from "@/stores/article";

const router = useRouter();
const store = useArticleStore();

const filters = reactive({
  status: "" as string | undefined,
  keyword: "" as string,
});

const statusOptions = [
  { label: "全部", value: "" },
  { label: "已完成", value: "completed" },
  { label: "草稿", value: "draft" },
  { label: "生成中", value: "content_generating" },
  { label: "等待编辑大纲", value: "outline_editing" },
  { label: "等待选标题", value: "title_selecting" },
  { label: "失败", value: "failed" },
];

const columns = [
  { title: "标题", dataIndex: "title", key: "title", ellipsis: true },
  {
    title: "选题",
    dataIndex: "topic",
    key: "topic",
    ellipsis: true,
    width: 200,
  },
  { title: "状态", dataIndex: "status", key: "status", width: 120 },
  { title: "字数", dataIndex: "word_count", key: "word_count", width: 80 },
  {
    title: "创建时间",
    dataIndex: "created_at",
    key: "created_at",
    width: 170,
  },
  { title: "操作", key: "actions", width: 180, fixed: "right" },
];

const pagination = computed<TablePaginationConfig>(() => ({
  current: store.page,
  pageSize: store.limit,
  total: store.total,
  showSizeChanger: false,
  showTotal: (t: number) => `共 ${t} 篇`,
}));

function load(page = 1): void {
  store.fetchList({
    page,
    limit: 20,
    status: filters.status || undefined,
    keyword: filters.keyword || undefined,
  });
}

function handleTableChange(pag: TablePaginationConfig): void {
  load(pag.current ?? 1);
}

function handleSearch(): void {
  load(1);
}

function handleReset(): void {
  filters.status = "";
  filters.keyword = "";
  load(1);
}

function formatTime(t: string | null): string {
  return t ? dayjs(t).format("YYYY-MM-DD HH:mm") : "-";
}

function goDetail(id: number): void {
  router.push(`/articles/${id}`);
}

function goContinue(record: { id: number; status: string }): void {
  // 未完成的文章跳回创作工坊（未来可做 resume；当前仅前端跳转）
  if (record.status === "completed") {
    router.push(`/articles/${record.id}`);
  } else {
    router.push(`/workshop`);
  }
}

function handleDelete(id: number): void {
  Modal.confirm({
    title: "确认删除这篇文章？",
    content: "删除后不可恢复。",
    okType: "danger",
    okText: "删除",
    cancelText: "取消",
    async onOk() {
      try {
        await store.remove(id);
        message.success("已删除");
      } catch {
        message.error("删除失败");
      }
    },
  });
}

onMounted(() => load(1));
</script>

<template>
  <div class="article-list-page">
    <a-page-header title="我的文章" sub-title="全部创作记录管理" />

    <a-card :bordered="false" style="margin-bottom: 12px">
      <a-form layout="inline" @submit.prevent="handleSearch">
        <a-form-item label="状态">
          <a-select
            v-model:value="filters.status"
            :options="statusOptions"
            style="width: 180px"
            allow-clear
          />
        </a-form-item>
        <a-form-item label="关键词">
          <a-input
            v-model:value="filters.keyword"
            placeholder="标题 / 选题"
            style="width: 240px"
            allow-clear
            @press-enter="handleSearch"
          />
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="handleSearch">搜索</a-button>
            <a-button @click="handleReset">重置</a-button>
            <a-button type="dashed" @click="router.push('/workshop')">
              新建创作
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>
    </a-card>

    <a-card :bordered="false">
      <a-table
        :data-source="store.list"
        :columns="columns"
        :loading="store.loading"
        :pagination="pagination"
        :row-key="(r: { id: number }) => r.id"
        size="middle"
        @change="handleTableChange"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'title'">
            <a @click="goDetail(record.id)" class="title-link">
              {{ record.title || "（未命名）" }}
            </a>
          </template>
          <template v-else-if="column.key === 'status'">
            <a-tag :color="ARTICLE_STATUS_COLOR[record.status] || 'default'">
              {{ ARTICLE_STATUS_LABEL[record.status] || record.status }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'created_at'">
            {{ formatTime(record.created_at) }}
          </template>
          <template v-else-if="column.key === 'actions'">
            <a-space :size="6">
              <a-button size="small" type="link" @click="goDetail(record.id)">
                查看
              </a-button>
              <a-button
                v-if="record.status !== 'completed'"
                size="small"
                type="link"
                @click="goContinue(record)"
              >
                继续
              </a-button>
              <a-button
                size="small"
                type="link"
                danger
                @click="handleDelete(record.id)"
              >
                删除
              </a-button>
            </a-space>
          </template>
        </template>
      </a-table>
    </a-card>
  </div>
</template>

<style scoped>
.article-list-page {
  max-width: 1280px;
  margin: 0 auto;
}
.title-link {
  color: #1677ff;
  font-weight: 500;
}
.title-link:hover {
  text-decoration: underline;
}
</style>
