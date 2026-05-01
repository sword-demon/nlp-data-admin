<script setup lang="ts">
import { ref } from "vue";
import { message } from "ant-design-vue";
import { useWorkshopStore } from "@/stores/workshop";

const store = useWorkshopStore();
const topic = ref("");
const style = ref("通用");

const styleOptions = [
  { value: "通用", label: "通用" },
  { value: "科技评论", label: "科技评论" },
  { value: "生活随笔", label: "生活随笔" },
  { value: "产品推荐", label: "产品推荐" },
  { value: "新闻解读", label: "新闻解读" },
  { value: "教程指南", label: "教程指南" },
];

async function handleStart(): Promise<void> {
  if (!topic.value.trim()) {
    message.warning("请先输入选题");
    return;
  }
  try {
    const researchFallback = await store.createArticle(
      topic.value.trim(),
      style.value,
    );
    if (researchFallback) {
      message.info("选题研究资料暂不可用，已使用基础模式生成标题", 3);
    }
    message.success("候选标题已生成，请选择");
  } catch {
    message.error(store.errorMessage || "创建失败");
  }
}
</script>

<template>
  <a-card title="第 1 步：确定选题" :bordered="false">
    <a-form layout="vertical" :disabled="store.loading">
      <a-form-item label="选题 / 核心想法" required>
        <a-textarea
          v-model:value="topic"
          placeholder="例如：AI 编程助手会取代程序员吗？结合近半年行业变化谈谈"
          :rows="3"
          :maxlength="500"
          show-count
        />
      </a-form-item>

      <a-form-item label="写作风格">
        <a-select
          v-model:value="style"
          :options="styleOptions"
          style="width: 240px"
        />
      </a-form-item>

      <a-form-item>
        <a-button type="primary" :loading="store.loading" @click="handleStart">
          开始创作
        </a-button>
      </a-form-item>
    </a-form>
  </a-card>
</template>
