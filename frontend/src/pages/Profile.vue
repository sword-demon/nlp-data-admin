<script setup lang="ts">
import { onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import dayjs from "dayjs";
import { useAuthStore } from "@/stores/auth";
import { useVipStore } from "@/stores/vip";

const auth = useAuthStore();
const vip = useVipStore();
const router = useRouter();

const levelLabel = computed(() => {
  const lv = vip.info?.effective_level ?? "free";
  if (lv === "yearly") return "年费 VIP";
  if (lv === "monthly") return "月费 VIP";
  return "免费版";
});

const expireText = computed(() => {
  if (!vip.info?.vip_expired_at) return "永久免费版";
  return `到期：${dayjs(vip.info.vip_expired_at).format("YYYY-MM-DD")}`;
});

const quotaText = computed(() => {
  if (!vip.info) return "加载中…";
  if (vip.info.quota_total < 0) return "不限（Unlimited）";
  return `${vip.info.quota_used} / ${vip.info.quota_total}`;
});

onMounted(async () => {
  await Promise.all([
    auth.fetchMe().catch(() => undefined),
    vip.refresh().catch(() => undefined),
  ]);
});
</script>

<template>
  <div class="profile-page">
    <a-page-header
      title="个人中心"
      sub-title="账号信息 · 会员状态 · 配额使用"
    />

    <a-row :gutter="16">
      <a-col :xs="24" :md="12">
        <a-card title="账号信息" :bordered="false" class="card">
          <a-descriptions :column="1" size="small">
            <a-descriptions-item label="用户名">
              {{ auth.user?.username }}
            </a-descriptions-item>
            <a-descriptions-item label="邮箱">
              {{ auth.user?.email }}
            </a-descriptions-item>
            <a-descriptions-item label="角色">
              <a-tag :color="auth.user?.role === 'admin' ? 'gold' : 'blue'">
                {{ auth.user?.role || "user" }}
              </a-tag>
            </a-descriptions-item>
            <a-descriptions-item label="用户 ID">
              #{{ auth.user?.id }}
            </a-descriptions-item>
          </a-descriptions>
        </a-card>
      </a-col>

      <a-col :xs="24" :md="12">
        <a-card title="会员与配额" :bordered="false" class="card">
          <a-descriptions :column="1" size="small">
            <a-descriptions-item label="会员等级">
              <a-tag
                :color="
                  vip.info?.effective_level === 'yearly'
                    ? 'gold'
                    : vip.info?.effective_level === 'monthly'
                      ? 'blue'
                      : 'default'
                "
              >
                {{ levelLabel }}
              </a-tag>
            </a-descriptions-item>
            <a-descriptions-item label="有效期">
              {{ expireText }}
            </a-descriptions-item>
            <a-descriptions-item label="本月配额">
              {{ quotaText }}
            </a-descriptions-item>
          </a-descriptions>

          <a-progress
            v-if="vip.info && vip.info.quota_total > 0"
            :percent="vip.quotaPercent"
            :status="vip.quotaPercent >= 80 ? 'exception' : 'normal'"
            style="margin-top: 12px"
          />

          <a-space style="margin-top: 12px">
            <a-button type="primary" @click="router.push('/vip')">
              会员中心
            </a-button>
            <a-button @click="router.push('/orders')">订单记录</a-button>
          </a-space>
        </a-card>
      </a-col>
    </a-row>

    <a-card
      title="快捷入口"
      :bordered="false"
      class="card"
      style="margin-top: 16px"
    >
      <a-space :size="12">
        <a-button @click="router.push('/workshop')">开始创作</a-button>
        <a-button @click="router.push('/articles')">我的文章</a-button>
        <a-button
          v-if="auth.user?.role === 'admin'"
          @click="router.push('/dashboard')"
        >
          数据看板
        </a-button>
      </a-space>
    </a-card>
  </div>
</template>

<style scoped>
.profile-page {
  max-width: 1100px;
  margin: 0 auto;
}
.card {
  height: 100%;
}
</style>
