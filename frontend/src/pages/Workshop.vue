<script setup lang="ts">
import { computed, onBeforeUnmount } from "vue";
import { useWorkshopStore } from "@/stores/workshop";
import TopicForm from "@/components/workshop/TopicForm.vue";
import TitleSelector from "@/components/workshop/TitleSelector.vue";
import OutlineEditor from "@/components/workshop/OutlineEditor.vue";
import GenerationViewer from "@/components/workshop/GenerationViewer.vue";

const store = useWorkshopStore();

// 阶段映射（用于 Steps 指示器）
const stepMap: Record<string, number> = {
  idle: 0,
  draft: 0,
  topic_researching: 0,
  title_generating: 0,
  title_selecting: 1,
  outline_generating: 1,
  outline_editing: 2,
  content_generating: 3,
  image_analyzing: 3,
  image_generating: 3,
  completed: 4,
  failed: 4,
};
const currentStep = computed(() => stepMap[store.status] ?? 0);

function handleReset(): void {
  store.reset();
}

onBeforeUnmount(() => {
  store.closeStream();
});
</script>

<template>
  <div class="workshop-container">
    <a-page-header
      title="创作工坊"
      sub-title="多智能体协作：选题 → 选标题 → 编大纲 → 流式生成正文与配图"
    >
      <template #extra>
        <a-button v-if="store.status !== 'idle'" @click="handleReset"
          >重新开始</a-button
        >
      </template>
    </a-page-header>

    <a-steps
      :current="currentStep"
      :status="store.isFailed ? 'error' : 'process'"
      style="margin: 16px 0 24px"
    >
      <a-step title="选题" />
      <a-step title="选标题" />
      <a-step title="编大纲" />
      <a-step title="生成正文 / 配图" />
      <a-step title="完成" />
    </a-steps>

    <TopicForm
      v-if="
        store.status === 'idle' ||
        store.status === 'draft' ||
        store.status === 'topic_researching' ||
        store.status === 'title_generating'
      "
    />

    <TitleSelector
      v-else-if="
        store.status === 'title_selecting' ||
        store.status === 'outline_generating'
      "
    />

    <OutlineEditor v-else-if="store.status === 'outline_editing'" />

    <GenerationViewer
      v-else-if="
        store.status === 'content_generating' ||
        store.status === 'image_analyzing' ||
        store.status === 'image_generating' ||
        store.status === 'completed' ||
        store.status === 'failed'
      "
    />
  </div>
</template>

<style scoped>
.workshop-container {
  max-width: 1280px;
  margin: 0 auto;
}
</style>
