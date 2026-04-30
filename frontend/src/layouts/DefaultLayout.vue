<script setup lang="ts">
import { computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { message } from "ant-design-vue";
import dayjs from "dayjs";
import { useAuthStore } from "@/stores/auth";
import { useVipStore } from "@/stores/vip";

const router = useRouter();
const auth = useAuthStore();
const vip = useVipStore();

const menuItems = [
  { key: "home", label: "首页", path: "/" },
  { key: "workshop", label: "创作工坊", path: "/workshop" },
  { key: "dashboard", label: "数据看板", path: "/dashboard" },
  { key: "vip", label: "会员中心", path: "/vip" },
  { key: "orders", label: "我的订单", path: "/orders" },
];

const effectiveLevel = computed(() => vip.info?.effective_level || "free");

const tierTagColor = computed(() => {
  if (effectiveLevel.value === "yearly") return "gold";
  if (effectiveLevel.value === "monthly") return "blue";
  return "default";
});

const tierLabel = computed(() => {
  if (effectiveLevel.value === "yearly") return "年费 VIP";
  if (effectiveLevel.value === "monthly") return "月费 VIP";
  return "免费版";
});

const quotaText = computed(() => {
  if (!vip.info) return "";
  if (vip.info.quota_total < 0) return "配额不限";
  return `本月 ${vip.info.quota_used} / ${vip.info.quota_total}`;
});

const quotaLow = computed(() => {
  if (!vip.info || vip.info.quota_total < 0) return false;
  return vip.quotaPercent >= 80;
});

const expiredText = computed(() => {
  if (!vip.info?.vip_expired_at) return "";
  return `到期：${dayjs(vip.info.vip_expired_at).format("MM-DD")}`;
});

function handleMenuClick(path: string): void {
  router.push(path);
}

async function handleLogout(): Promise<void> {
  await auth.logout();
  message.success("已退出登录");
  await router.replace("/login");
}

function goVip(): void {
  router.push("/vip");
}

// 登录状态变化时重新加载 VIP 信息
watch(
  () => auth.isLoggedIn,
  async (v) => {
    if (v) await vip.refresh().catch(() => undefined);
  },
);

onMounted(async () => {
  if (auth.isLoggedIn) {
    await vip.refresh().catch(() => undefined);
  }
});
</script>

<template>
  <a-layout style="min-height: 100vh">
    <a-layout-sider breakpoint="lg" collapsed-width="64">
      <div class="logo">NLP 创作平台</div>
      <a-menu
        theme="dark"
        mode="inline"
        @click="
          ({ key }: { key: string }) => {
            const item = menuItems.find((m) => m.key === key);
            if (item) handleMenuClick(item.path);
          }
        "
      >
        <a-menu-item v-for="item in menuItems" :key="item.key">
          {{ item.label }}
        </a-menu-item>
      </a-menu>
    </a-layout-sider>

    <a-layout>
      <a-layout-header class="app-header">
        <h3 class="app-title">AI 多智能体内容创作平台</h3>
        <a-space v-if="auth.user" :size="12">
          <a-tag
            :color="tierTagColor"
            style="cursor: pointer; font-weight: 500"
            @click="goVip"
          >
            {{ tierLabel }}
          </a-tag>
          <span v-if="vip.info" class="quota" :class="{ warn: quotaLow }">
            {{ quotaText }}
          </span>
          <span v-if="expiredText" class="expire-hint">{{ expiredText }}</span>
          <a-button
            v-if="effectiveLevel === 'free'"
            size="small"
            type="primary"
            @click="goVip"
          >
            升级会员
          </a-button>
          <a-dropdown>
            <a class="user-trigger" @click.prevent>
              <a-avatar size="small" style="background-color: #1677ff">
                {{ auth.user.username.charAt(0).toUpperCase() }}
              </a-avatar>
              <span class="user-name">{{ auth.user.username }}</span>
            </a>
            <template #overlay>
              <a-menu>
                <a-menu-item key="vip" @click="goVip">会员中心</a-menu-item>
                <a-menu-item key="orders" @click="router.push('/orders')">
                  我的订单
                </a-menu-item>
                <a-menu-divider />
                <a-menu-item key="logout" @click="handleLogout">
                  退出登录
                </a-menu-item>
              </a-menu>
            </template>
          </a-dropdown>
        </a-space>
      </a-layout-header>
      <a-layout-content style="margin: 24px">
        <router-view />
      </a-layout-content>
      <a-layout-footer style="text-align: center">
        NLP Data Admin ©2026
      </a-layout-footer>
    </a-layout>
  </a-layout>
</template>

<style scoped>
.logo {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  background: rgba(255, 255, 255, 0.1);
}
.app-header {
  background: #fff;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.app-title {
  margin: 0;
}
.quota {
  color: rgba(0, 0, 0, 0.55);
  font-size: 13px;
}
.quota.warn {
  color: #fa541c;
  font-weight: 500;
}
.expire-hint {
  color: rgba(0, 0, 0, 0.45);
  font-size: 12px;
}
.user-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: rgba(0, 0, 0, 0.85);
  cursor: pointer;
}
.user-name {
  font-size: 14px;
}
</style>
