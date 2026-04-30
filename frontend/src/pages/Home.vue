<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

function goToWorkshop(): void {
  router.push('/workshop')
}
</script>

<template>
  <div class="home-page">
    <a-card>
      <a-typography>
        <a-typography-title>
          欢迎使用 AI 多智能体内容创作平台
        </a-typography-title>
        <a-typography-paragraph>
          通过 5 个专业 AI 智能体的协作，从选题到配图，一站式完成高质量内容创作。
        </a-typography-paragraph>
      </a-typography>

      <a-space direction="vertical" size="large" style="width: 100%; margin-top: 32px">
        <a-card hoverable>
          <a-typography-title :level="4">
            三阶段创作流程
          </a-typography-title>
          <a-typography-paragraph>
            标题生成 → 大纲编辑 → 正文+配图，Human-in-the-loop 设计让你全程掌控创作方向。
          </a-typography-paragraph>
        </a-card>

        <a-card hoverable>
          <a-typography-title :level="4">
            多种配图方式
          </a-typography-title>
          <a-typography-paragraph>
            支持 Pexels 图库、Mermaid 流程图、Iconify 图标、表情包、SVG 示意图和 AI 生图。
          </a-typography-paragraph>
        </a-card>
      </a-space>

      <a-button
        type="primary"
        size="large"
        style="margin-top: 32px"
        @click="goToWorkshop"
      >
        开始创作
      </a-button>
    </a-card>
  </div>
</template>

<style scoped>
.home-page {
  max-width: 800px;
  margin: 0 auto;
}
</style>
