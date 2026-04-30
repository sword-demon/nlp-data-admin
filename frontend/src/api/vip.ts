import { http } from "./client";
import type { ApiResponse } from "./client";

export type VipLevel = "free" | "monthly" | "yearly";

export interface VipPlan {
  id: number;
  name: string;
  level: VipLevel;
  price: number;
  duration_days: number;
  quota_monthly: number;
  allowed_image_strategies: string[] | null;
  description: string | null;
  is_active: boolean;
  sort_order: number;
}

export interface QuotaUsage {
  total: number;
  used: number;
  remaining: number;
  reset_at: string;
}

export interface VipInfo {
  user_id: number;
  vip_level: VipLevel;
  effective_level: VipLevel;
  vip_expired_at: string | null;
  is_active: boolean;
  quota_total: number;
  quota_used: number;
  quota_remaining: number;
  allowed_strategies: string[];
  quota_usage: QuotaUsage;
}

export async function fetchPlans(): Promise<VipPlan[]> {
  const { data } = await http.get<ApiResponse<{ plans: VipPlan[] }>>(
    "/vip/plans",
  );
  return data.data.plans;
}

export async function fetchVipInfo(): Promise<VipInfo> {
  const { data } = await http.get<ApiResponse<VipInfo>>("/vip/info");
  return data.data;
}

export async function fetchAllowedStrategies(): Promise<string[]> {
  const { data } = await http.get<ApiResponse<{ strategies: string[] }>>(
    "/vip/strategies",
  );
  return data.data.strategies;
}

export async function fetchQuota(): Promise<QuotaUsage> {
  const { data } = await http.get<ApiResponse<QuotaUsage>>("/vip/quota");
  return data.data;
}
