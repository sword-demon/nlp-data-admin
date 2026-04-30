<script setup lang="ts">
import { computed } from "vue";
import MarkdownIt from "markdown-it";
import { useWorkshopStore } from "@/stores/workshop";

const store = useWorkshopStore();

const md = new MarkdownIt({ html: false, linkify: true, breaks: true });

const renderedContent = computed(() =>
  store.contentBuffer ? md.render(store.contentBuffer) : "",
);

const statusLabel: Record<string, string> = {
  content_generating: "正在生成正文…",
  image_analyzing: "正在分析配图需求…",
  image_generating: "正在生成配图…",
  completed: "创作完成",
  failed: "生成失败",
};

const statusText = computed(() => statusLabel[store.status] ?? store.status);

const progressPercent = computed(() => {
  switch (store.status) {
    case "content_generating":
      return 30;
    case "image_analyzing":
      return 60;
    case "image_generating":
      return 85;
    case "completed":
      return 100;
    case "failed":
      return 100;
    default:
      return 0;
  }
});
</script>

<template>
  <a-card title="第 4 步：实时生成" :bordered="false">
    <a-progress
      :percent="progressPercent"
      :status="
        store.isFailed ? 'exception' : store.isCompleted ? 'success' : 'active'
      "
      style="margin-bottom: 16px"
    />
    <a-alert
      :type="store.isFailed ? 'error' : store.isCompleted ? 'success' : 'info'"
      :message="statusText"
      :description="store.errorMessage || undefined"
      show-icon
      style="margin-bottom: 16px"
    />

    <a-row :gutter="16">
      <a-col :span="16">
        <div class="section-title">
          正文（字数：{{ store.wordCount || store.contentBuffer.length }}）
        </div>
        <div class="content-viewer markdown-body" v-html="renderedContent" />
      </a-col>

      <a-col :span="8">
        <div class="section-title">配图分析</div>
        <div class="sidebar">
          <a-empty
            v-if="store.imageAnalyses.length === 0"
            description="尚未分析"
            :image-style="{ height: '60px' }"
          />
          <div v-else class="analysis-list">
            <a-card
              v-for="a in store.imageAnalyses"
              :key="a.placeholder_id"
              size="small"
              class="analysis-card"
            >
              <div class="analysis-head">
                <a-tag color="geekblue">{{ a.placeholder_id }}</a-tag>
                <a-tag color="purple">{{ a.suggested_type }}</a-tag>
              </div>
              <div class="analysis-prompt">
                <strong>关键词：</strong>{{ a.keywords.join(" / ") }}
              </div>
              <div class="analysis-prompt">{{ a.reasoning }}</div>
            </a-card>
          </div>
        </div>

        <div class="section-title" style="margin-top: 16px">生成配图</div>
        <div class="sidebar">
          <a-empty
            v-if="store.images.length === 0"
            description="尚未生成"
            :image-style="{ height: '60px' }"
          />
          <div v-else class="image-grid">
            <div
              v-for="img in store.images"
              :key="img.placeholder_id"
              class="image-item"
            >
              <img :src="img.url" :alt="img.alt || img.keyword" />
              <div class="image-caption">
                <div class="caption-row">
                  <a-tag color="geekblue">{{ img.placeholder_id }}</a-tag>
                  <a-tag color="purple">{{ img.source || img.type }}</a-tag>
                  <a-tag v-if="img.is_placeholder" color="orange">占位</a-tag>
                </div>
                <div class="caption-alt">{{ img.alt || img.keyword }}</div>
                <div v-if="img.attribution" class="caption-attr">
                  {{ img.attribution }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </a-col>
    </a-row>
  </a-card>
</template>

<style scoped>
.section-title {
  font-weight: 500;
  margin-bottom: 8px;
  color: rgba(0, 0, 0, 0.75);
}
.content-viewer {
  border: 1px solid #f0f0f0;
  border-radius: 6px;
  padding: 16px 20px;
  min-height: 520px;
  max-height: 640px;
  overflow: auto;
  background: #fff;
  line-height: 1.75;
  font-size: 14px;
}
.content-viewer :deep(h1),
.content-viewer :deep(h2),
.content-viewer :deep(h3) {
  margin-top: 18px;
  margin-bottom: 10px;
}
.content-viewer :deep(p) {
  margin: 8px 0;
}
.sidebar {
  border: 1px solid #f0f0f0;
  border-radius: 6px;
  padding: 12px;
  background: #fafafa;
  min-height: 120px;
  max-height: 300px;
  overflow: auto;
}
.analysis-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.analysis-card {
  background: #fff;
}
.analysis-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}
.keyword {
  font-weight: 500;
}
.analysis-prompt {
  font-size: 12px;
  color: rgba(0, 0, 0, 0.6);
  line-height: 1.5;
  margin-bottom: 6px;
}
.image-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}
.image-item {
  border: 1px solid #eee;
  border-radius: 4px;
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
  font-size: 12px;
  padding: 6px;
  color: rgba(0, 0, 0, 0.65);
}
.caption-row {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-bottom: 4px;
}
.caption-alt {
  font-weight: 500;
  color: rgba(0, 0, 0, 0.75);
  line-height: 1.4;
  margin-bottom: 2px;
}
.caption-attr {
  font-size: 11px;
  color: rgba(0, 0, 0, 0.45);
  line-height: 1.3;
}
</style>
