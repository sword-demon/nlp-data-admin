<script setup lang="ts">
import { ref, watch } from "vue";
import { message } from "ant-design-vue";
import { useWorkshopStore } from "@/stores/workshop";
import type { OutlineNode } from "@/api/workshop";

const store = useWorkshopStore();

// 可编辑的本地 markdown 副本；store.outline 变化时同步
const markdown = ref<string>(store.outline?.markdown ?? "");
const nodes = ref<OutlineNode[]>(store.outline?.nodes ?? []);

watch(
  () => store.outline,
  (next) => {
    if (next) {
      markdown.value = next.markdown;
      nodes.value = next.nodes;
    }
  },
  { immediate: true },
);

async function handleSave(): Promise<void> {
  if (!markdown.value.trim()) {
    message.warning("大纲内容不能为空");
    return;
  }
  try {
    // nodes 沿用后端原始结构；如需真正可视化编辑可后续升级
    await store.saveOutline({ markdown: markdown.value, nodes: nodes.value });
    message.success("大纲已保存");
  } catch {
    message.error(store.errorMessage || "保存失败");
  }
}

function handleStartGeneration(): void {
  if (!markdown.value.trim()) {
    message.warning("请先完成大纲");
    return;
  }
  store.startGeneration();
}
</script>

<template>
  <a-card title="第 3 步：编辑大纲" :bordered="false">
    <a-alert
      type="success"
      show-icon
      :message="`已选标题：${store.selectedTitle}`"
      style="margin-bottom: 16px"
    />

    <a-row :gutter="16">
      <a-col :span="14">
        <div class="section-title">大纲 Markdown（可编辑）</div>
        <a-textarea
          v-model:value="markdown"
          :rows="18"
          class="markdown-input"
          :disabled="store.loading"
        />
      </a-col>

      <a-col :span="10">
        <div class="section-title">节点结构预览</div>
        <div class="node-preview">
          <a-empty v-if="nodes.length === 0" description="暂无节点" />
          <ul v-else class="node-list">
            <li v-for="n in nodes" :key="n.id" :class="`node-l${n.level}`">
              <span class="node-id">#{{ n.id }}</span>
              <span class="node-text">{{ n.text }}</span>
            </li>
          </ul>
        </div>
      </a-col>
    </a-row>

    <a-space style="margin-top: 16px" :size="12">
      <a-button :loading="store.loading" @click="handleSave">保存大纲</a-button>
      <a-button
        type="primary"
        :loading="store.loading"
        @click="handleStartGeneration"
      >
        开始生成正文
      </a-button>
    </a-space>
  </a-card>
</template>

<style scoped>
.section-title {
  font-weight: 500;
  margin-bottom: 8px;
  color: rgba(0, 0, 0, 0.75);
}
.markdown-input {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 13px;
  line-height: 1.6;
}
.node-preview {
  border: 1px solid #f0f0f0;
  border-radius: 6px;
  padding: 12px;
  min-height: 420px;
  max-height: 440px;
  overflow: auto;
  background: #fafafa;
}
.node-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.node-list li {
  padding: 4px 0;
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 13px;
  line-height: 1.6;
}
.node-l1 {
  font-weight: 600;
  color: #1677ff;
}
.node-l2 {
  padding-left: 16px;
  color: rgba(0, 0, 0, 0.85);
}
.node-l3 {
  padding-left: 32px;
  color: rgba(0, 0, 0, 0.65);
}
.node-id {
  color: rgba(0, 0, 0, 0.4);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
  flex-shrink: 0;
  min-width: 28px;
}
.node-text {
  flex: 1;
}
</style>
