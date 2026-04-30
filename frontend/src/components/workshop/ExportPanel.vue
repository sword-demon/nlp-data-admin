<script setup lang="ts">
import { computed, ref } from "vue";
import { message } from "ant-design-vue";
import { useRouter } from "vue-router";
import { useWorkshopStore } from "@/stores/workshop";

const store = useWorkshopStore();
const router = useRouter();
const copying = ref(false);

const title = computed(
  () => store.selectedTitle || store.topic || "未命名文章",
);

const plainMarkdown = computed(() => store.contentBuffer);

const stats = computed(() => ({
  words: store.wordCount || store.contentBuffer.length,
  images: store.images.length,
  placeholders: store.placeholderCount,
}));

async function handleCopy(): Promise<void> {
  if (!plainMarkdown.value) {
    message.warning("尚无正文可复制");
    return;
  }
  copying.value = true;
  try {
    await navigator.clipboard.writeText(plainMarkdown.value);
    message.success("已复制到剪贴板");
  } catch {
    message.error("复制失败，请手动选择文本");
  } finally {
    copying.value = false;
  }
}

function handleDownload(): void {
  if (!plainMarkdown.value) {
    message.warning("尚无正文可下载");
    return;
  }
  const date = new Date().toISOString().slice(0, 10);
  const safeTitle = title.value.replace(/[\\/:*?"<>|\s]+/g, "_").slice(0, 40);
  const filename = `${safeTitle}_${date}.md`;

  const blob = new Blob([plainMarkdown.value], {
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
  message.success("已下载 " + filename);
}

function handleNewCreation(): void {
  store.reset();
}

function handleGoList(): void {
  router.push("/articles");
}
</script>

<template>
  <a-card title="导出与下一步" size="small" class="export-panel">
    <a-row :gutter="16" align="middle">
      <a-col :span="14">
        <a-space :size="8" wrap>
          <a-statistic
            title="字数"
            :value="stats.words"
            :value-style="{ fontSize: '18px' }"
          />
          <a-statistic
            title="配图"
            :value="stats.images"
            :value-style="{ fontSize: '18px' }"
            style="margin-left: 24px"
          />
          <a-statistic
            v-if="stats.placeholders > 0"
            title="占位符"
            :value="stats.placeholders"
            :value-style="{ fontSize: '18px' }"
            style="margin-left: 24px"
          />
        </a-space>
      </a-col>

      <a-col :span="10" style="text-align: right">
        <a-space :size="8" wrap>
          <a-button :loading="copying" @click="handleCopy">
            复制 Markdown
          </a-button>
          <a-button type="primary" @click="handleDownload"> 下载 .md </a-button>
          <a-button @click="handleGoList">我的文章</a-button>
          <a-button type="link" @click="handleNewCreation">
            开始新创作
          </a-button>
        </a-space>
      </a-col>
    </a-row>
  </a-card>
</template>

<style scoped>
.export-panel {
  background: #fff;
  border: 1px solid #e8f4ff;
}
</style>
