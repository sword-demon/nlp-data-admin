<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import dayjs from "dayjs";
import { useVipStore } from "@/stores/vip";
import { useAuthStore } from "@/stores/auth";
import PayModal from "@/components/PayModal.vue";
import type { PlanType } from "@/api/payment";
import type { VipLevel } from "@/api/vip";

const vip = useVipStore();
const auth = useAuthStore();

const payOpen = ref<boolean>(false);
const selectedPlan = ref<PlanType | null>(null);
const selectedAmount = ref<number>(0);

const currentLevel = computed<VipLevel>(
  () => vip.info?.effective_level || "free",
);

const expiredText = computed<string>(() => {
  if (!vip.info?.vip_expired_at) return "";
  return dayjs(vip.info.vip_expired_at).format("YYYY-MM-DD HH:mm");
});

function tierColor(level: VipLevel): string {
  if (level === "yearly") return "#faad14";
  if (level === "monthly") return "#1677ff";
  return "#8c8c8c";
}

function isCurrent(level: VipLevel): boolean {
  return level === currentLevel.value;
}

function openPay(level: VipLevel, price: number): void {
  if (level === "free") return;
  selectedPlan.value = level as PlanType;
  selectedAmount.value = price;
  payOpen.value = true;
}

async function handlePaid(): Promise<void> {
  // 支付成功 → 刷新 VIP 信息 + 个人资料
  await vip.refresh();
  try {
    await auth.fetchMe();
  } catch {
    // 忽略
  }
}

onMounted(async () => {
  await vip.refresh();
});
</script>

<template>
  <div class="vip-center">
    <!-- 当前状态 -->
    <a-card class="status-card" :loading="vip.loading">
      <a-row :gutter="24" align="middle">
        <a-col :span="12">
          <a-space direction="vertical" :size="4">
            <span class="label">当前等级</span>
            <a-tag
              :color="tierColor(currentLevel)"
              style="font-size: 14px; padding: 4px 12px"
            >
              {{ currentLevel === "free" ? "免费版"
              : currentLevel === "monthly" ? "月费版"
              : "年费版" }}
            </a-tag>
            <span v-if="vip.info?.vip_expired_at" class="hint">
              到期时间：{{ expiredText }}
            </span>
          </a-space>
        </a-col>
        <a-col :span="12">
          <div class="quota-block">
            <div class="quota-label">
              本月配额
              <span v-if="vip.isUnlimited" class="unlimited">不限</span>
            </div>
            <a-progress
              v-if="!vip.isUnlimited && vip.info"
              :percent="vip.quotaPercent"
              :stroke-color="vip.quotaPercent >= 80 ? '#ff4d4f' : '#1677ff'"
            />
            <div v-if="vip.info" class="quota-detail">
              已用 {{ vip.info.quota_used }} /
              {{ vip.isUnlimited ? "∞" : vip.info.quota_total }}
            </div>
          </div>
        </a-col>
      </a-row>
    </a-card>

    <!-- 套餐卡片 -->
    <a-row :gutter="16" class="plans">
      <a-col
        v-for="p in vip.plans"
        :key="p.id"
        :xs="24"
        :sm="24"
        :md="8"
      >
        <a-card
          class="plan-card"
          :class="{ active: isCurrent(p.level), featured: p.level === 'monthly' }"
          hoverable
        >
          <div class="plan-header">
            <h3 class="plan-name">{{ p.name }}</h3>
            <a-tag v-if="isCurrent(p.level)" color="green">当前套餐</a-tag>
          </div>
          <div class="plan-price">
            <span class="currency">¥</span>
            <span class="amount">{{ p.price.toFixed(0) }}</span>
            <span class="period">
              /{{ p.level === "yearly" ? "年" : p.level === "monthly" ? "月" : "" }}
            </span>
          </div>
          <a-divider style="margin: 12px 0" />
          <ul class="features">
            <li>
              每月 <b>{{ p.quota_monthly < 0 ? "不限" : p.quota_monthly }}</b> 篇文章
            </li>
            <li v-if="p.allowed_image_strategies">
              支持 <b>{{ p.allowed_image_strategies.length }}</b> 种配图策略
            </li>
            <li v-if="p.allowed_image_strategies?.includes('nanobanana')">
              AI 生图（Nano Banana）
            </li>
            <li v-if="p.allowed_image_strategies?.includes('svg')">
              AI 概念图（SVG）
            </li>
          </ul>

          <a-button
            v-if="p.level === 'free'"
            block
            disabled
            style="margin-top: 16px"
          >
            免费版
          </a-button>
          <a-button
            v-else
            type="primary"
            block
            :ghost="isCurrent(p.level)"
            style="margin-top: 16px"
            @click="openPay(p.level, Number(p.price))"
          >
            {{ isCurrent(p.level) ? "立即续费" : "立即开通" }}
          </a-button>
        </a-card>
      </a-col>
    </a-row>

    <PayModal
      v-model:open="payOpen"
      :plan-type="selectedPlan"
      :amount="selectedAmount"
      @paid="handlePaid"
    />
  </div>
</template>

<style scoped>
.vip-center {
  max-width: 1100px;
  margin: 0 auto;
}
.status-card {
  margin-bottom: 24px;
}
.label {
  color: rgba(0, 0, 0, 0.45);
  font-size: 13px;
}
.hint {
  color: rgba(0, 0, 0, 0.45);
  font-size: 12px;
}
.quota-block {
  padding: 0 8px;
}
.quota-label {
  font-size: 13px;
  color: rgba(0, 0, 0, 0.65);
  margin-bottom: 6px;
}
.unlimited {
  color: #faad14;
  margin-left: 8px;
}
.quota-detail {
  text-align: right;
  font-size: 12px;
  color: rgba(0, 0, 0, 0.45);
  margin-top: 4px;
}
.plans {
  margin-top: 8px;
}
.plan-card {
  height: 100%;
}
.plan-card.active {
  border-color: #52c41a;
}
.plan-card.featured {
  border-color: #1677ff;
  transform: scale(1.02);
}
.plan-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.plan-name {
  margin: 0;
  font-size: 18px;
}
.plan-price {
  margin-top: 12px;
  color: #1677ff;
}
.plan-price .currency {
  font-size: 16px;
  margin-right: 2px;
}
.plan-price .amount {
  font-size: 32px;
  font-weight: 600;
}
.plan-price .period {
  font-size: 13px;
  color: rgba(0, 0, 0, 0.45);
  margin-left: 4px;
}
.features {
  list-style: none;
  padding: 0;
  margin: 0;
  color: rgba(0, 0, 0, 0.75);
  font-size: 14px;
}
.features li {
  padding: 6px 0;
}
</style>
