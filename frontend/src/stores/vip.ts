import { defineStore } from "pinia";
import { ref, computed } from "vue";
import type { VipPlan, VipInfo, QuotaUsage } from "@/api/vip";
import * as vipApi from "@/api/vip";

export const useVipStore = defineStore("vip", () => {
  const plans = ref<VipPlan[]>([]);
  const info = ref<VipInfo | null>(null);
  const loading = ref<boolean>(false);

  const quotaUsage = computed<QuotaUsage | null>(() =>
    info.value ? info.value.quota_usage : null,
  );

  const isUnlimited = computed<boolean>(
    () => !!info.value && info.value.quota_total < 0,
  );

  const quotaPercent = computed<number>(() => {
    if (!info.value) return 0;
    if (info.value.quota_total <= 0) return 0;
    return Math.min(
      100,
      Math.round((info.value.quota_used / info.value.quota_total) * 100),
    );
  });

  async function loadPlans(): Promise<void> {
    plans.value = await vipApi.fetchPlans();
  }

  async function loadInfo(): Promise<void> {
    loading.value = true;
    try {
      info.value = await vipApi.fetchVipInfo();
    } finally {
      loading.value = false;
    }
  }

  async function refresh(): Promise<void> {
    await Promise.all([loadPlans(), loadInfo()]);
  }

  return {
    plans,
    info,
    loading,
    quotaUsage,
    isUnlimited,
    quotaPercent,
    loadPlans,
    loadInfo,
    refresh,
  };
});
