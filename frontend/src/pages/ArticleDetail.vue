<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { message } from "ant-design-vue";
import dayjs from "dayjs";
import {
  fetchArticle,
  ARTICLE_STATUS_LABEL,
  ARTICLE_STATUS_COLOR,
} from "@/api/article";
import type { ArticleDetail } from "@/api/article";
import MarkdownRenderer from "@/components/common/MarkdownRenderer.vue";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const data = ref<ArticleDetail | null>(null);
const errorMsg = ref("");

const articleId = computed(() => Number(route.params.id));

async function load(): Promise<void> {
  loading.value = true;
  errorMsg.value = "";
  try {
    data.value = await fetchArticle(articleId.value);
  } catch (e) {
    const anyE = e as { response?: { data?: { message?: string } } };
    errorMsg.value = anyE.response?.data?.message ?? "加载失败";
  } finally {
    loading.value = false;
  }
}

async function handleCopy(): Promise<void> {
  if (!data.value?.content) {
    message.warning("尚无正文");
    return;
  }
  try {
    await navigator.clipboard.writeText(data.value.content);
    message.success("已复制");
  } catch {
    message.error("复制失败");
  }
}

function handleDownload(): void {
  if (!data.value?.content) {
    message.warning("尚无正文");
    return;
  }
  const date = dayjs(data.value.updated_at || undefined).format("YYYY-MM-DD");
  const safeTitle = (data.value.title || `article-${data.value.id}`)
    .replace(/[\\/:*?"<>|\s]+/g, "_")
    .slice(0, 40);
  const filename = `${safeTitle}_${date}.md`;
  const blob = new Blob([data.value.content], {
    type: "text/markdown;charset=utf-8",
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

onMounted(load);
watch(articleId, () => load());
</script>

<template>
  <div class="article-detail-page">
    <a-page-header
      :title="data?.title || '文章详情'"
      sub-title="创作成果"
      @back="() => router.push('/articles')"
    >
      <template #extra>
        <a-space>
          <a-button :disabled="!data?.content" @click="handleCopy">
            复制 Markdown
          </a-button>
          <a-button
            type="primary"
            :disabled="!data?.content"
            @click="handleDownload"
          >
            下载 .md
          </a-button>
        </a-space>
      </template>
    </a-page-header>

    <a-spin :spinning="loading">
      <a-alert
        v-if="errorMsg"
        :message="errorMsg"
        type="error"
        show-icon
        style="margin-bottom: 12px"
      />

      <template v-if="data">
        <a-card :bordered="false" style="margin-bottom: 12px">
          <a-descriptions :column="2" size="small" bordered>
            <a-descriptions-item label="状态">
              <a-tag :color="ARTICLE_STATUS_COLOR[data.status] || 'default'">
                {{ ARTICLE_STATUS_LABEL[data.status] || data.status }}
              </a-tag>
            </a-descriptions-item>
            <a-descriptions-item label="字数">
              {{ data.word_count }}
            </a-descriptions-item>
            <a-descriptions-item label="风格">
              {{ data.style || "-" }}
            </a-descriptions-item>
            <a-descriptions-item label="模型">
              {{ data.ai_model || "-" }}
            </a-descriptions-item>
            <a-descriptions-item label="选题" :span="2">
              {{ data.topic }}
            </a-descriptions-item>
            <a-descriptions-item label="创建时间">
              {{
                data.created_at
                  ? dayjs(data.created_at).format("YYYY-MM-DD HH:mm")
                  : "-"
              }}
            </a-descriptions-item>
            <a-descriptions-item label="更新时间">
              {{
                data.updated_at
                  ? dayjs(data.updated_at).format("YYYY-MM-DD HH:mm")
                  : "-"
              }}
            </a-descriptions-item>
          </a-descriptions>
        </a-card>

        <a-card title="正文" :bordered="false">
          <MarkdownRenderer :content="data.content" empty="该文章尚无正文" />
        </a-card>

        <a-card
          v-if="data.images && data.images.length > 0"
          title="配图"
          :bordered="false"
          style="margin-top: 12px"
        >
          <div class="image-grid">
            <div
              v-for="img in data.images"
              :key="img.placeholder_id"
              class="image-item"
            >
              <img :src="img.url" :alt="img.alt || img.keyword" />
              <div class="image-caption">
                <a-tag color="geekblue">{{ img.placeholder_id }}</a-tag>
                <a-tag color="purple">{{ img.source || img.type }}</a-tag>
                <div class="caption-text">{{ img.alt || img.keyword }}</div>
              </div>
            </div>
          </div>
        </a-card>
      </template>
    </a-spin>
  </div>
</template>

<style scoped>
.article-detail-page {
  max-width: 1100px;
  margin: 0 auto;
}
.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}
.image-item {
  border: 1px solid #f0f0f0;
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
}
.image-item img {
  width: 100%;
  display: block;
  aspect-ratio: 1;
  object-fit: cover;
}
.image-caption {
  padding: 8px;
  font-size: 12px;
  color: rgba(0, 0, 0, 0.65);
}
.caption-text {
  margin-top: 4px;
  line-height: 1.5;
}
</style>
