<script setup lang="ts">
import { message } from "ant-design-vue";
import { useWorkshopStore } from "@/stores/workshop";

const store = useWorkshopStore();

async function handleConfirm(): Promise<void> {
  if (store.selectedTitleIndex < 0) {
    message.warning("请选择一个标题");
    return;
  }
  try {
    await store.confirmTitle();
    message.success("大纲已生成");
  } catch {
    message.error(store.errorMessage || "生成大纲失败");
  }
}
</script>

<template>
  <a-card title="第 2 步：选择标题" :bordered="false">
    <a-alert
      type="info"
      show-icon
      :message="`选题：${store.topic}  |  风格：${store.style}`"
      style="margin-bottom: 16px"
    />

    <a-radio-group v-model:value="store.selectedTitleIndex" style="width: 100%">
      <a-space direction="vertical" style="width: 100%">
        <a-radio
          v-for="(t, idx) in store.titles"
          :key="idx"
          :value="idx"
          class="title-radio"
        >
          <div class="title-card">
            <div class="title-text">{{ t.title }}</div>
            <div class="title-meta">
              <a-tag color="blue">{{ t.analysis }}</a-tag>
              <a-tag color="gold">推荐分：{{ t.score }}</a-tag>
            </div>
          </div>
        </a-radio>
      </a-space>
    </a-radio-group>

    <a-divider />

    <a-form layout="vertical" :disabled="store.loading">
      <a-form-item label="补充说明（可选，给大纲生成更具体的方向）">
        <a-textarea
          v-model:value="store.supplement"
          placeholder="例如：重点讨论国内工程师的工作方式变化，不要涉及具体公司"
          :rows="3"
          :maxlength="300"
          show-count
        />
      </a-form-item>

      <a-form-item>
        <a-button
          type="primary"
          :loading="store.loading"
          @click="handleConfirm"
        >
          确认标题并生成大纲
        </a-button>
      </a-form-item>
    </a-form>
  </a-card>
</template>

<style scoped>
.title-radio {
  display: flex;
  align-items: flex-start;
  padding: 12px;
  border: 1px solid #f0f0f0;
  border-radius: 6px;
  margin-bottom: 0;
  width: 100%;
}
.title-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-left: 4px;
}
.title-text {
  font-weight: 500;
  font-size: 15px;
  line-height: 1.5;
}
.title-meta {
  display: flex;
  gap: 4px;
}
</style>
