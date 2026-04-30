import { http } from "./client";
import type { ApiResponse } from "./client";
import type { VipLevel } from "./vip";

export type PayType = "wxpay" | "alipay";
export type OrderStatus = "pending" | "paid" | "failed" | "refunded";
export type PlanType = "monthly" | "yearly";

export interface CreateOrderResult {
  order_id: number;
  out_trade_no: string;
  pay_url: string;
  qrcode: string;
  mode: "api" | "submit";
  plan_type: PlanType;
  pay_type: PayType;
  amount: number;
  subject: string;
  status: OrderStatus;
}

export interface OrderStatusResult {
  order_id: number;
  out_trade_no: string;
  status: OrderStatus;
  plan_type: PlanType;
  pay_type: PayType;
  amount: number;
  paid_at: string | null;
}

export interface OrderItem {
  order_id: number;
  out_trade_no: string;
  plan_type: PlanType;
  pay_type: PayType;
  amount: number;
  status: OrderStatus;
  subject: string;
  paid_at: string | null;
  created_at: string | null;
}

export interface OrderListResult {
  list: OrderItem[];
  total: number;
  page: number;
  limit: number;
}

export async function createOrder(
  planType: PlanType,
  payType: PayType,
): Promise<CreateOrderResult> {
  const { data } = await http.post<ApiResponse<CreateOrderResult>>(
    "/pay/create",
    {
      plan_type: planType,
      pay_type: payType,
    },
  );
  return data.data;
}

export async function queryOrderStatus(
  outTradeNo: string,
): Promise<OrderStatusResult> {
  const { data } = await http.get<ApiResponse<OrderStatusResult>>(
    "/pay/status",
    { params: { out_trade_no: outTradeNo } },
  );
  return data.data;
}

export async function fetchOrders(
  page = 1,
  limit = 20,
): Promise<OrderListResult> {
  const { data } = await http.get<ApiResponse<OrderListResult>>("/pay/orders", {
    params: { page, limit },
  });
  return data.data;
}

// 同等级名称映射，便于前端展示
export const VIP_LEVEL_LABEL: Record<VipLevel, string> = {
  free: "免费版",
  monthly: "月费版",
  yearly: "年费版",
};

export const ORDER_STATUS_LABEL: Record<OrderStatus, string> = {
  pending: "待支付",
  paid: "已支付",
  failed: "支付失败",
  refunded: "已退款",
};

export const ORDER_STATUS_COLOR: Record<OrderStatus, string> = {
  pending: "orange",
  paid: "green",
  failed: "red",
  refunded: "default",
};

export const PAY_TYPE_LABEL: Record<PayType, string> = {
  wxpay: "微信支付",
  alipay: "支付宝",
};
