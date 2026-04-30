<script setup lang="ts">
import { computed } from "vue";
import { md } from "@/utils/markdown";
import "highlight.js/styles/github.css";

const props = withDefaults(defineProps<{ content: string; empty?: string }>(), {
  empty: "暂无内容",
});

const html = computed(() => (props.content ? md.render(props.content) : ""));
</script>

<template>
  <div v-if="content" class="markdown-body" v-html="html" />
  <a-empty v-else :description="empty" :image-style="{ height: '60px' }" />
</template>

<style scoped>
.markdown-body {
  line-height: 1.75;
  font-size: 14px;
  color: rgba(0, 0, 0, 0.85);
  word-break: break-word;
}
.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4) {
  margin-top: 18px;
  margin-bottom: 10px;
  line-height: 1.3;
  font-weight: 600;
}
.markdown-body :deep(p) {
  margin: 8px 0;
}
.markdown-body :deep(ul),
.markdown-body :deep(ol) {
  padding-left: 24px;
  margin: 8px 0;
}
.markdown-body :deep(blockquote) {
  margin: 10px 0;
  padding: 4px 16px;
  border-left: 4px solid #e0e0e0;
  color: rgba(0, 0, 0, 0.55);
  background: #fafafa;
}
.markdown-body :deep(img) {
  max-width: 100%;
  border-radius: 4px;
  margin: 8px 0;
}
.markdown-body :deep(a) {
  color: #1677ff;
  text-decoration: none;
}
.markdown-body :deep(a:hover) {
  text-decoration: underline;
}
.markdown-body :deep(code) {
  background: rgba(175, 184, 193, 0.2);
  padding: 2px 6px;
  border-radius: 4px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.9em;
}
.markdown-body :deep(pre.hljs) {
  padding: 12px 16px;
  border-radius: 6px;
  overflow: auto;
  font-size: 13px;
  line-height: 1.55;
}
.markdown-body :deep(pre.hljs code) {
  background: transparent;
  padding: 0;
  border-radius: 0;
  font-size: inherit;
}
.markdown-body :deep(table) {
  border-collapse: collapse;
  margin: 10px 0;
  width: 100%;
}
.markdown-body :deep(th),
.markdown-body :deep(td) {
  border: 1px solid #f0f0f0;
  padding: 6px 10px;
  text-align: left;
  font-size: 13px;
}
.markdown-body :deep(th) {
  background: #fafafa;
}
</style>
