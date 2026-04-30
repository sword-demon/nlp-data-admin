<script setup lang="ts">
import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useVipStore } from "@/stores/vip";

const router = useRouter();
const auth = useAuthStore();
const vip = useVipStore();

const welcomeName = computed(() => auth.user?.username || "创作者");

const levelLabel = computed(() => {
  const lv = vip.info?.effective_level ?? "free";
  if (lv === "yearly") return "年费 VIP";
  if (lv === "monthly") return "月费 VIP";
  return "免费版";
});

const features = [
  {
    icon: "🧠",
    title: "多智能体协作",
    desc: "标题 / 大纲 / 正文 / 配图分析 / 并行配图，5 个专业 Agent 流水线协作。",
  },
  {
    icon: "✍️",
    title: "Human-in-the-loop",
    desc: "三阶段交互：AI 生成标题你来选；大纲你可编辑；正文流式生成可实时预览。",
  },
  {
    icon: "🖼️",
    title: "6 种智能配图",
    desc: "Pexels 真实图库 / Mermaid 流程图 / Iconify 图标 / 表情包 / SVG 示意 / AI 生图。",
  },
  {
    icon: "⚡",
    title: "实时流式输出",
    desc: "SSE 流式推送，AI 边写边看；Markdown 实时高亮渲染，带代码块语法着色。",
  },
  {
    icon: "📦",
    title: "一键导出",
    desc: "Markdown 复制、下载 .md 文件，完整保留正文结构与配图占位。",
  },
  {
    icon: "👑",
    title: "会员 & 配额",
    desc: "免费体验，月费 / 年费会员享受更高配额与高级模型，实时显示使用进度。",
  },
];

const steps = [
  { title: "选题输入", desc: "输入主题和风格" },
  { title: "AI 生成标题", desc: "从 5 个候选中选一个" },
  { title: "编辑大纲", desc: "调整章节和要点" },
  { title: "流式正文", desc: "实时生成可预览" },
  { title: "智能配图", desc: "多策略并行生图" },
  { title: "完成导出", desc: "下载 / 复制 Markdown" },
];

function goWorkshop(): void {
  router.push("/workshop");
}

function goArticles(): void {
  router.push("/articles");
}

function goVip(): void {
  router.push("/vip");
}

onMounted(async () => {
  if (auth.isLoggedIn) {
    await vip.refresh().catch(() => undefined);
  }
});
</script>

<template>
  <div class="home-page">
    <div class="hero">
      <div class="hero-content">
        <a-tag color="blue" class="welcome-tag">
          {{ levelLabel }} · 你好，{{ welcomeName }}
        </a-tag>
        <h1 class="hero-title">AI 多智能体内容创作平台</h1>
        <p class="hero-sub">
          从选题到配图一站式完成，让 5 个专业 AI Agent 为你协作，
          把创作者从"想题目"、"写正文"、"配图片"的重复劳动中解放出来。
        </p>
        <a-space :size="12" wrap>
          <a-button type="primary" size="large" @click="goWorkshop">
            开始创作
          </a-button>
          <a-button size="large" @click="goArticles">我的文章</a-button>
          <a-button
            v-if="vip.info?.effective_level === 'free'"
            size="large"
            @click="goVip"
          >
            升级会员
          </a-button>
        </a-space>
      </div>
    </div>

    <a-card title="6 步完成一篇高质量文章" :bordered="false" class="section">
      <a-steps :current="-1" progress-dot size="small">
        <a-step
          v-for="s in steps"
          :key="s.title"
          :title="s.title"
          :description="s.desc"
        />
      </a-steps>
    </a-card>

    <div class="section">
      <a-row :gutter="[16, 16]">
        <a-col v-for="f in features" :key="f.title" :xs="24" :sm="12" :md="8">
          <a-card hoverable class="feature-card">
            <div class="feature-icon">{{ f.icon }}</div>
            <div class="feature-title">{{ f.title }}</div>
            <div class="feature-desc">{{ f.desc }}</div>
          </a-card>
        </a-col>
      </a-row>
    </div>

    <a-card :bordered="false" class="section cta-card">
      <div class="cta-row">
        <div>
          <h3 style="margin: 0">准备好了吗？</h3>
          <p style="margin: 4px 0 0; color: rgba(0, 0, 0, 0.55)">
            一杯咖啡的时间，产出一篇配图完整的公众号文章。
          </p>
        </div>
        <a-button type="primary" size="large" @click="goWorkshop">
          立即进入创作工坊
        </a-button>
      </div>
    </a-card>
  </div>
</template>

<style scoped>
.home-page {
  max-width: 1200px;
  margin: 0 auto;
}
.hero {
  background: linear-gradient(135deg, #1677ff 0%, #722ed1 100%);
  border-radius: 10px;
  padding: 48px 40px;
  color: #fff;
  margin-bottom: 24px;
}
.hero-content {
  max-width: 720px;
}
.welcome-tag {
  margin-bottom: 16px;
}
.hero-title {
  color: #fff;
  font-size: 32px;
  margin: 0 0 12px;
  font-weight: 600;
  letter-spacing: 0.5px;
}
.hero-sub {
  color: rgba(255, 255, 255, 0.85);
  font-size: 15px;
  line-height: 1.8;
  margin: 0 0 24px;
}
.section {
  margin-bottom: 24px;
}
.feature-card {
  height: 100%;
}
.feature-icon {
  font-size: 32px;
  margin-bottom: 8px;
}
.feature-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 6px;
}
.feature-desc {
  color: rgba(0, 0, 0, 0.55);
  font-size: 13px;
  line-height: 1.7;
}
.cta-card {
  background: #fafafa;
}
.cta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
</style>
